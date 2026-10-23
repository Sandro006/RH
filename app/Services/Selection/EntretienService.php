<?php

declare(strict_types=1);

namespace App\Services\Selection;

use App\Models\Selection\EntretienModel;
use App\Models\Selection\StatusEntretienModel;
use App\Models\Selection\HistoriqueEntretienModel;
use App\Services\Recrutement\CandidatureService;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Common\Tri;
use App\Libraries\Common\WorkflowService;

/**
 * Service métier du module Entretien (RH.md §II.B.1).
 *
 * Un entretien est planifié pour une candidature Présélectionnée.
 * À la réalisation, la candidature passe à En évaluation.
 */
class EntretienService
{
    private const ROLES = ['rh', 'recruteur', 'direction', 'admin'];

    public function __construct(
        private readonly EntretienModel $entretienModel,
        private readonly StatusEntretienModel $statusModel,
        private readonly HistoriqueEntretienModel $historiqueModel,
        private readonly CandidatureService $candidatureService,
        private readonly AuditService $auditService,
        private readonly WorkflowService $workflowService,
    ) {}

    // planifier
    public function planifier(array $nouvel, SessionUtilisateur $u): int
    {
        $this->exigerRole($u);

        $idCandidature = (int) ($nouvel['idCandidature'] ?? 0);
        $idRecruteur   = (int) ($nouvel['idRecruteur'] ?? 0);
        $dateEntretien = $nouvel['dateEntretien'] ?? null;
        $typeEntretien = isset($nouvel['typeEntretien']) ? (int) $nouvel['typeEntretien'] : null;

        if ($idCandidature <= 0) {
            throw new DonneesIncoherentesException('idCandidature obligatoire');
        }
        if ($idRecruteur <= 0) {
            throw new DonneesIncoherentesException('idRecruteur obligatoire');
        }
        if ($dateEntretien === null || $dateEntretien === '') {
            throw new DonneesIncoherentesException('dateEntretien obligatoire');
        }

        if ($this->candidatureService->consulter($idCandidature) === null) {
            throw new ObjetIntrouvableException("Candidature #{$idCandidature} introuvable");
        }

        $statutCandidature = $this->designationCandidature($idCandidature);
        if ($statutCandidature !== 'Présélectionnée') {
            throw new StatutInvalideException(
                "La candidature doit être Présélectionnée (statut actuel : {$statutCandidature})"
            );
        }

        if ($this->employeExiste($idRecruteur) === false) {
            throw new ObjetIntrouvableException("Recruteur #{$idRecruteur} introuvable");
        }

        $statutPlanifie = $this->statusModel->idParDesignation('Planifié');

        $db = $this->entretienModel->db;
        $db->transStart();

        try {
            $idEntretien = $this->entretienModel->insert([
                'id_candidature' => $idCandidature,
                'id_recruteur'   => $idRecruteur,
                'date_entretien' => $dateEntretien,
                'type_entretien' => $typeEntretien,
                'compte_rendu'   => null,
                'statut'         => $statutPlanifie,
            ], true);

            if ($idEntretien === false) {
                throw new \RuntimeException(
                    'Échec insertion entretien : ' . json_encode($this->entretienModel->errors())
                );
            }

            $this->historiqueModel->insert([
                'id_entretien'    => (int) $idEntretien,
                'statut_ancien'   => $statutPlanifie,
                'statut_nouveau'  => $statutPlanifie,
                'date_changement' => date('Y-m-d H:i:s'),
                'id_utilisateur'  => $u->idUtilisateur,
                'commentaire'     => 'Planification de l\'entretien',
            ]);

            $this->candidatureService->planifierEntretien(
                $idCandidature,
                ['date_entretien' => (string) $dateEntretien],
                $u->toArray()
            );

            $this->auditService->journaliser(
                $u->idUtilisateur,
                'CREATE',
                'entretien',
                (int) $idEntretien,
                null,
                $u->adresseIp
            );

            $db->transComplete();

            return (int) $idEntretien;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // confirmer
    public function confirmer(int $idEntretien, SessionUtilisateur $u): void
    {
        $this->exigerRole($u);
        $this->changerStatut($idEntretien, 'Confirmé', $u, 'Entretien confirmé');
    }

    // reporter
    public function reporter(int $idEntretien, string $nouvelleDate, ?string $motif, SessionUtilisateur $u): void
    {
        $this->exigerRole($u);

        if ($nouvelleDate === '') {
            throw new DonneesIncoherentesException('nouvelleDate obligatoire');
        }

        $entretien = $this->trouver($idEntretien);
        $statutActuel = $this->statusModel->designationParId((int) $entretien->statut);

        if (!$this->statusModel->transitionAutorisee($statutActuel, 'Reporté')) {
            throw new StatutInvalideException("Un entretien {$statutActuel} ne peut pas être reporté");
        }

        $db = $this->entretienModel->db;
        $db->transStart();

        try {
            $this->entretienModel->update($idEntretien, ['date_entretien' => $nouvelleDate]);
            $this->changerStatut($idEntretien, 'Reporté', $u, $motif ?? 'Report de l\'entretien');
            $this->changerStatut($idEntretien, 'Planifié', $u, 'Nouvelle date : ' . $nouvelleDate);

            $this->auditService->journaliser(
                $u->idUtilisateur,
                'UPDATE',
                'entretien',
                $idEntretien,
                ['date_entretien' => $nouvelleDate],
                $u->adresseIp
            );

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // realiser
    public function realiser(int $idEntretien, string $compteRendu, SessionUtilisateur $u): void
    {
        $this->exigerRole($u);

        $entretien = $this->trouver($idEntretien);
        $statutActuel = $this->statusModel->designationParId((int) $entretien->statut);

        if (!$this->statusModel->transitionAutorisee($statutActuel, 'Réalisé')) {
            throw new StatutInvalideException("Un entretien {$statutActuel} ne peut pas être réalisé");
        }

        $idCandidature = (int) $entretien->id_candidature;
        $statutCandidature = $this->designationCandidature($idCandidature);

        $db = $this->entretienModel->db;
        $db->transStart();

        try {
            $this->entretienModel->update($idEntretien, ['compte_rendu' => $compteRendu]);
            $this->changerStatut($idEntretien, 'Réalisé', $u, 'Entretien réalisé');

            if ($statutCandidature === 'Entretien prévu') {
                $this->candidatureService->marquerEvaluee($idCandidature, $u->toArray());
            } elseif ($statutCandidature !== 'En évaluation') {
                throw new StatutInvalideException(
                    "La candidature n'est pas à l'étape de l'entretien (statut : {$statutCandidature})"
                );
            }

            $this->auditService->journaliser(
                $u->idUtilisateur,
                'UPDATE',
                'entretien',
                $idEntretien,
                null,
                $u->adresseIp
            );

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // annuler
    public function annuler(int $idEntretien, ?string $motif, SessionUtilisateur $u): void
    {
        $this->exigerRole($u);

        $entretien = $this->trouver($idEntretien);
        $statutActuel = $this->statusModel->designationParId((int) $entretien->statut);

        if (!$this->statusModel->transitionAutorisee($statutActuel, 'Annulé')) {
            throw new StatutInvalideException("Un entretien {$statutActuel} ne peut pas être annulé");
        }

        $idCandidature = (int) $entretien->id_candidature;

        $db = $this->entretienModel->db;
        $db->transStart();

        try {
            $this->changerStatut($idEntretien, 'Annulé', $u, $motif ?? 'Annulation de l\'entretien');

            if ($this->designationCandidature($idCandidature) === 'Entretien prévu') {
                $this->workflowService->changerStatut(
                    'candidature',
                    $idCandidature,
                    'Présélectionnée',
                    $u,
                    'Entretien annulé'
                );
            }

            $this->auditService->journaliser(
                $u->idUtilisateur,
                'UPDATE',
                'entretien',
                $idEntretien,
                $motif,
                $u->adresseIp
            );

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // consulter
    public function consulter(int $idEntretien): ?object
    {
        return $this->entretienModel->find($idEntretien);
    }

    // lister
    public function lister(array $filtre = []): array
    {
        $query = $this->entretienModel->db->table('entretien e')
            ->select([
                'e.*',
                'se.designation AS statut_designation',
                'te.designation AS type_entretien_designation',
                'c.id_offre',
                'ca.nom AS nom_candidat',
                'ca.prenom AS prenom_candidat',
                'oe.titre AS offre',
                'emp.nom AS recruteur',
            ])
            ->join('candidature c', 'c.id_candidature = e.id_candidature', 'left')
            ->join('candidat ca', 'ca.id_candidat = c.id_candidat', 'left')
            ->join('offre_emploi oe', 'oe.id_offre = c.id_offre', 'left')
            ->join('employe emp', 'emp.id = e.id_recruteur', 'left')
            ->join('status_entretien se', 'se.id = e.statut', 'left')
            ->join('type_entrentien te', 'te.id = e.type_entretien', 'left');

        if (!empty($filtre['statut'])) {
            $query->where('se.designation', $filtre['statut']);
        }
        if (!empty($filtre['idRecruteur'])) {
            $query->where('e.id_recruteur', (int) $filtre['idRecruteur']);
        }
        if (!empty($filtre['idOffre'])) {
            $query->where('c.id_offre', (int) $filtre['idOffre']);
        }
        if (!empty($filtre['idCandidature'])) {
            $query->where('e.id_candidature', (int) $filtre['idCandidature']);
        }
        if (!empty($filtre['dateDebut'])) {
            $query->where('e.date_entretien >=', $filtre['dateDebut'] . ' 00:00:00');
        }
        if (!empty($filtre['dateFin'])) {
            $query->where('e.date_entretien <=', $filtre['dateFin'] . ' 23:59:59');
        }

          [$colonne, $ordre] = Tri::resoudre($filtre, [
              'id_entretien'  => 'e.id_entretien',
              'date_entretien' => 'e.date_entretien',
              'candidat'      => 'ca.nom',
              'offre'         => 'oe.titre',
              'recruteur'     => 'emp.nom',
              'statut'        => 'se.designation',
              'type'          => 'te.designation',
          ], 'e.date_entretien', 'ASC');

          return $query->orderBy($colonne, $ordre)->get()->getResultArray();
    }

    public function listerParCandidature(int $idCandidature): array
    {
        return $this->lister(['idCandidature' => $idCandidature]);
    }

    // historique
    public function historique(int $idEntretien): array
    {
        return $this->historiqueModel
            ->where('id_entretien', $idEntretien)
            ->orderBy('date_changement', 'ASC')
            ->findAll();
    }

    // planifierPourOffre
    public function planifierPourOffre(int $idOffre, SessionUtilisateur $u): array
    {
        $this->exigerRole($u);

        if ($u->idEmploye === null) {
            throw new DonneesIncoherentesException('Aucun employé associé à l\'utilisateur connecté');
        }

        $typeEntretien = $this->typeEntretienId('Entretien d\'embauche');

        $candidatures = $this->entretienModel->db->table('candidature c')
            ->select('c.id_candidature')
            ->join('status_candidature sc', 'sc.id = c.statut', 'left')
            ->where('c.id_offre', $idOffre)
            ->where('sc.designation', 'Présélectionnée')
            ->get()
            ->getResultArray();

        $ids = [];

        foreach ($candidatures as $candidature) {
            $ids[] = $this->planifier([
                'idCandidature' => (int) $candidature['id_candidature'],
                'idRecruteur'   => $u->idEmploye,
                'dateEntretien' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'typeEntretien' => $typeEntretien,
            ], $u);
        }

        return $ids;
    }

    private function changerStatut(
        int $idEntretien,
        string $statutCible,
        SessionUtilisateur $u,
        ?string $commentaire = null
    ): void {
        $entretien = $this->trouver($idEntretien);
        $statutActuel = $this->statusModel->designationParId((int) $entretien->statut);

        if ($statutActuel === $statutCible) {
            return;
        }

        if (!$this->statusModel->transitionAutorisee($statutActuel, $statutCible)) {
            throw new StatutInvalideException(
                "Transition {$statutActuel} -> {$statutCible} interdite"
            );
        }

        $this->workflowService->changerStatut(
            'entretien',
            $idEntretien,
            $statutCible,
            $u,
            $commentaire
        );
    }

    private function trouver(int $idEntretien): object
    {
        $entretien = $this->entretienModel->find($idEntretien);

        if ($entretien === null) {
            throw new ObjetIntrouvableException("Entretien #{$idEntretien} introuvable");
        }

        return $entretien;
    }

    private function exigerRole(SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(self::ROLES)) {
            throw new AccesRefuseException('Rôle insuffisant pour gérer un entretien');
        }
    }

    private function designationCandidature(int $idCandidature): string
    {
        $ligne = $this->entretienModel->db->table('candidature c')
            ->select('sc.designation')
            ->join('status_candidature sc', 'sc.id = c.statut', 'left')
            ->where('c.id_candidature', $idCandidature)
            ->get()
            ->getRowArray();

        if ($ligne === null) {
            throw new ObjetIntrouvableException("Candidature #{$idCandidature} introuvable");
        }

        return (string) ($ligne['designation'] ?? '');
    }

    private function employeExiste(int $idEmploye): bool
    {
        return $this->entretienModel->db->table('employe')
            ->where('id', $idEmploye)
            ->countAllResults() > 0;
    }

    private function typeEntretienId(string $designation): ?int
    {
        $ligne = $this->entretienModel->db->table('type_entrentien')
            ->select('id')
            ->where('designation', $designation)
            ->get()
            ->getRowArray();

        return $ligne === null ? null : (int) $ligne['id'];
    }
}
