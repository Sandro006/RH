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
use App\Entities\Personnel\Affectation;
use App\Exceptions\StatutInvalideException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\ConflitException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;

/**
 * Service métier du module Salarié .
 *
 * Responsabilités :
 *  - créer un salarié à partir d'une proposition acceptée
 *  - modifier ses informations
 *  - l'affecter à un poste (historique)
 *  - changer son statut (appelé par le module Contrat)
 *  - consulter, lister, historiser
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
    ) {}

    // creerDepuisCandidature
    public function creerDepuisCandidature(int $idCandidature, SessionUtilisateur $u): int
    {
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
        $dateEmbauche   = $proposition->date_embauche_proposee ?? date('Y-m-d');
        $matricule      = $this->matriculeService->genererMatricule($dateEmbauche);
        $statutAttente  = $this->statusModel->idParDesignation("En attente d'embauche");

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

            $this->historiqueModel->insert([
                'id_salarie'      => $idSalarie,
                'statut_ancien'   => $statutAttente, // pas d'antérieur => on répète
                'statut_nouveau'  => $statutAttente,
                'date_changement' => date('c'),
                'id_utilisateur'  => $u->idUtilisateur,
                'commentaire'     => 'Création du salarié depuis proposition acceptée',
            ]);

            $this->auditService->journaliser(
                $u->idUtilisateur,
                'CREATE',
                'salarie',
                $idSalarie,
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
        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        // Champs modifiables uniquement (pas le matricule, pas le statut)
        $autorises = ['nom', 'prenom', 'email', 'telephone', 'date_embauche'];
        $payload = array_intersect_key($modifications, array_flip($autorises));

        if (empty($payload)) {
            return;
        }

        $this->salarieModel->update($idSalarie, $payload);

        $this->auditService->journaliser(
            $u->idUtilisateur, 'UPDATE', 'salarie', $idSalarie,
            json_encode($payload), $u->adresseIp
        );
    }

    // affecter
    public function affecter(
        int $idSalarie,
        int $idPoste,
        string $dateDebut,
        ?string $motif,
        SessionUtilisateur $u
    ): int {
        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
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
                $u->idUtilisateur, 'UPDATE', 'affectation', $idAffectation,
                null, $u->adresseIp
            );

            $db->transComplete();
            return (int) $idAffectation;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // listerAffectations
    public function listerAffectations(int $idSalarie): array
    {
        return $this->affectationModel->listerParSalarie($idSalarie);
    }

    // changer status
    public function changerStatut(
        int $idSalarie,
        string $statutCible,
        SessionUtilisateur $u,
        ?string $commentaire = null
    ): void {
        $salarie = $this->salarieModel->find($idSalarie);
        if ($salarie === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        $ancienId  = (int) $salarie->statut;
        $nouveauId = $this->statusModel->idParDesignation($statutCible);

        if ($ancienId === $nouveauId) {
            return;
        }

        $db = $this->salarieModel->db;
        $db->transStart();

        try {
            $this->salarieModel->update($idSalarie, ['statut' => $nouveauId]);

            $this->historiqueModel->insert([
                'id_salarie'      => $idSalarie,
                'statut_ancien'   => $ancienId,
                'statut_nouveau'  => $nouveauId,
                'date_changement' => date('c'),
                'id_utilisateur'  => $u->idUtilisateur,
                'commentaire'     => $commentaire,
            ]);

            $this->auditService->journaliser(
                $u->idUtilisateur, 'UPDATE', 'salarie', $idSalarie,
                $commentaire, $u->adresseIp
            );

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // consulter
    public function consulter(int $idSalarie): ?Salarie
    {
        return $this->salarieModel->find($idSalarie);
    }

    // liste
    public function lister(array $filtre = []): array
    {
        if (!empty($filtre['statut'])) {
            $this->salarieModel->where('statut', $filtre['statut']);
        }
        if (!empty($filtre['id_poste'])) {
            // filtre via affectation en cours (jointure simple côté service)
            $ids = array_column(
                $this->affectationModel
                     ->select('id_salarie')
                     ->where('id_poste', $filtre['id_poste'])
                     ->where('date_fin', null)
                     ->findAll(),
                'id_salarie'
            );
            if (empty($ids)) {
                return [];
            }
            $this->salarieModel->whereIn('id_salarie', $ids);
        }
        return $this->salarieModel->findAll();
    }

    // historique
    public function historique(int $idSalarie): array
    {
        return $this->historiqueModel->listerParSalarie($idSalarie);
    }
}