<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\SalarieModel;
use App\Models\Personnel\StatusSalarieModel;
use App\Models\Personnel\SalarieHistoriqueModel;
use App\Models\Personnel\AffectationModel;
use App\Models\Personnel\ReadModels\PropositionEmbaucheReadModel;
use App\Models\Personnel\ReadModels\StatusPropositionReadModel;
use App\Models\Personnel\ReadModels\CandidatureReadModel;
use App\Models\Personnel\ReadModels\CandidatReadModel;
use App\Entities\Personnel\Salarie;
use App\Exceptions\StatutInvalideException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\ConflitException;
use App\Exceptions\AccesRefuseException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Common\Tri;
use App\Libraries\Common\WorkflowService;

/**
 * Service métier du module Salarié (D1).
 *
 * Consignes respectées :
 *  - Architecture Service → Models (pas de HTTP, pas de SQL direct hors référentiels)
 *  - Pas d'appel aux services des autres pistes (lecture directe via ReadModels)
 *  - WorkflowService centralisé (Iavo) pour tous les changements de statut
 *  - AuditService (Iavo) pour la traçabilité
 *  - Historique dans salarie_status_historique
 *  - Un salarié par candidat (pas de doublon)
 *  - Proposition Acceptée obligatoire
 *  - Format matricule MAT-AAAA-00001 (via MatriculeService)
 *  - PostgreSQL (NUMERIC, DATE, TIMESTAMPTZ)
 */
class SalarieService
{
    public function __construct(
        private readonly SalarieModel $salarieModel,
        private readonly StatusSalarieModel $statusModel,
        private readonly SalarieHistoriqueModel $historiqueModel,
        private readonly AffectationModel $affectationModel,
        private readonly MatriculeService $matriculeService,
        private readonly PropositionEmbaucheReadModel $propositionReadModel,
        private readonly StatusPropositionReadModel $statusPropositionReadModel,
        private readonly CandidatureReadModel $candidatureReadModel,
        private readonly CandidatReadModel $candidatReadModel,
        private readonly AuditService $auditService,
        private readonly WorkflowService $workflowService,
    ) {}

    // creerDepuisCandidature
    public function creerDepuisCandidature(int $idCandidature, SessionUtilisateur $u): int
    {
        // 0. Rôle autorisé
        if (!$u->aLeRole(['rh', 'recruteur', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour créer un salarié');
        }

        // 1. La candidature doit exister
        $candidature = $this->candidatureReadModel->find($idCandidature);
        if ($candidature === null) {
            throw new ObjetIntrouvableException("Candidature #{$idCandidature} introuvable");
        }

        // 2. La proposition doit exister et être Acceptée
        $proposition = $this->propositionReadModel->trouverParCandidature($idCandidature);
        if ($proposition === null) {
            throw new ObjetIntrouvableException("Aucune proposition pour la candidature #{$idCandidature}");
        }

        $statutAcceptee = $this->statusPropositionReadModel->idParDesignation('Acceptée');
        if ((int) $proposition->statut !== $statutAcceptee) {
            throw new StatutInvalideException(
                "La proposition doit être Acceptée (statut actuel : {$proposition->statut})"
            );
        }

        // 3. Pas de doublon : un candidat = un salarié
        if ($this->salarieModel->trouverParCandidat((int) $candidature->id_candidat) !== null) {
            throw new ConflitException("Un salarié existe déjà pour la candidature #{$idCandidature}");
        }

        // 4. Récupérer le candidat
        $candidat = $this->candidatReadModel->find((int) $candidature->id_candidat);
        if ($candidat === null) {
            throw new ObjetIntrouvableException("Candidat introuvable pour la candidature #{$idCandidature}");
        }

        // 5. Préparer les données
        $dateEmbauche  = $proposition->date_embauche_proposee ?? date('Y-m-d');
        $matricule     = $this->matriculeService->genererMatricule($dateEmbauche);
        $statutAttente = $this->statusModel->idParDesignation("En attente d'embauche");

        // 6. Transaction
        $db = $this->salarieModel->db;
        $db->transStart();

        try {
            $idSalarie = $this->salarieModel->insert([
                'id_candidat'   => $candidat->id_candidat,
                'matricule'     => $matricule,
                'nom'           => $candidat->nom,
                'prenom'        => $candidat->prenom,
                'email'         => $candidat->email,
                'telephone'     => $candidat->telephone,
                'date_embauche' => $dateEmbauche,
                'statut'        => $statutAttente,
            ], true);

            if ($idSalarie === false) {
                throw new \RuntimeException(
                    'Échec insertion salarié : ' . json_encode($this->salarieModel->errors())
                );
            }

            
            $this->workflowService->changerStatut(
                'salarie',
                (int) $idSalarie,
                "En attente d'embauche",
                $u,
                'Création du salarié depuis proposition acceptée'
            );

            $this->auditService->journaliser(
                $u->idUtilisateur,
                'CREATE',
                'salarie',
                (int) $idSalarie,
                null,
                $u->adresseIp
            );

            $db->transComplete();
            return (int) $idSalarie;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

   // modifier
    public function modifier(int $idSalarie, array $modifications, SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'recruteur', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour modifier un salarié');
        }

        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        // Champs modifiables uniquement (pas matricule, pas statut, pas date_embauche)
        $autorises = [
            'nom', 'prenom', 'email', 'telephone',
            'date_naissance', 'lieu_naissance', 'nationalite', 'situation_familiale',
            'adresse', 'ville', 'pays',
        ];
        $payload   = array_intersect_key($modifications, array_flip($autorises));

        if (empty($payload)) {
            return;
        }

        $this->salarieModel->update($idSalarie, $payload);

        $this->auditService->journaliser(
            $u->idUtilisateur, 'UPDATE', 'salarie', $idSalarie,
            json_encode($payload), $u->adresseIp
        );
    }

	// afecter
    public function affecter(
        int $idSalarie,
        int $idPoste,
        string $dateDebut,
        ?string $motif,
        SessionUtilisateur $u
    ): int {
        if (!$u->aLeRole(['rh', 'recruteur', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour affecter un salarié');
        }

        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        // Vérifier que le poste existe (référentiel Iavo)
        $posteExiste = $this->salarieModel->db->table('poste')
                            ->where('id_poste', $idPoste)
                            ->countAllResults() > 0;
        if (!$posteExiste) {
            throw new ObjetIntrouvableException("Poste #{$idPoste} introuvable");
        }

        $db = $this->affectationModel->db;
        $db->transStart();

        try {
            // Clôture l'affectation en cours si elle existe
            $encours = $this->affectationModel->affectationEnCours($idSalarie);
            if ($encours !== null) {
                $this->affectationModel->update($encours->id_affectation, [
                    'date_fin' => $dateDebut,
                ]);
            }

            $idAffectation = $this->affectationModel->insert([
                'id_salarie' => $idSalarie,
                'id_poste'   => $idPoste,
                'date_debut' => $dateDebut,
                'date_fin'   => null,
                'motif'      => $motif,
            ], true);

            if ($idAffectation === false) {
                throw new \RuntimeException('Échec insertion affectation');
            }

            $this->auditService->journaliser(
                $u->idUtilisateur, 'UPDATE', 'affectation', (int) $idAffectation,
                null, $u->adresseIp
            );

            $db->transComplete();
            return (int) $idAffectation;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // lister affectation
    public function listerAffectations(int $idSalarie): array
    {
        return $this->affectationModel->listerParSalarie($idSalarie);
    }

    // changer status (delequer au WorkflowService)
    public function changerStatut(
        int $idSalarie,
        string $statutCible,
        SessionUtilisateur $u,
        ?string $commentaire = null
    ): void {
        if (!$u->aLeRole(['rh', 'recruteur', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour changer le statut d\'un salarié');
        }

        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        $this->workflowService->changerStatut(
            'salarie',
            $idSalarie,
            $statutCible,
            $u,
            $commentaire
        );
    }

    // consulter
    public function consulter(int $idSalarie): ?object
    {
        return $this->requeteSalaries()
            ->where('s.id_salarie', $idSalarie)
            ->get()
            ->getRow();
    }

    // lister
    public function lister(array $filtre = []): array
    {
        $requete = $this->requeteSalaries();

        if (!empty($filtre['statut'])) {
            $requete->where('s.statut', (int) $filtre['statut']);
        }
        if (!empty($filtre['id_poste'])) {
            $requete->where('p.id_poste', (int) $filtre['id_poste']);
        }
        if (!empty($filtre['id_departement'])) {
            $requete->where('d.id_departement', (int) $filtre['id_departement']);
        }
        if (!empty($filtre['recherche'])) {
            $recherche = $filtre['recherche'];
            $requete->groupStart()
                ->like('s.matricule', $recherche)
                ->orLike('s.nom', $recherche)
                ->orLike('s.prenom', $recherche)
                ->orLike("CONCAT(s.prenom, ' ', s.nom)", $recherche)
                ->groupEnd();
        }

        [$colonne, $ordre] = Tri::resoudre($filtre, [
            'id_salarie'    => 's.id_salarie',
            'matricule'     => 's.matricule',
            'nom'           => 's.nom',
            'email'         => 's.email',
            'date_embauche' => 's.date_embauche',
            'poste'         => 'p.intitule',
            'statut'        => 'ss.designation',
        ], 's.matricule', 'ASC');

        return $requete->orderBy($colonne, $ordre)->get()->getResultArray();
    }

    private function requeteSalaries()
    {
        return $this->salarieModel->db->table('salarie s')
            ->select('s.id_salarie, s.id_candidat, s.matricule, s.nom, s.prenom, s.email, s.telephone, '
                . 's.date_naissance, s.lieu_naissance, s.nationalite, s.situation_familiale, '
                . 's.adresse, s.ville, s.pays, s.date_embauche, s.date_sortie, s.statut, '
                . 'ss.designation AS statut_designation, p.id_poste, p.intitule AS poste')
            ->join('status_salarie ss', 'ss.id = s.statut', 'left')
            ->join('affectation a', 'a.id_salarie = s.id_salarie AND a.date_fin IS NULL', 'left')
            ->join('poste p', 'p.id_poste = a.id_poste', 'left')
            ->join('service sv', 'sv.id_service = p.id_service', 'left')
            ->join('departement d', 'd.id_departement = sv.id_departement', 'left');
    }

    // historique
    public function historique(int $idSalarie): array
    {
        return $this->historiqueModel->listerParSalarie($idSalarie);
    }
}
