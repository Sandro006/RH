<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\OnboardingModel;
use App\Models\Personnel\StatusOnboardingModel;
use App\Models\Personnel\OnboardingHistoriqueModel;
use App\Models\Personnel\TacheModel;
use App\Models\Personnel\StatusTacheModel;
use App\Models\Personnel\TacheHistoriqueModel;
use App\Models\Personnel\SalarieModel;
use App\Entities\Personnel\TacheOnboarding;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use App\Exceptions\ConflitException;
use App\Exceptions\AccesRefuseException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Common\Tri;
use App\Libraries\Common\WorkflowService;

class OnboardingService
{
    private const TACHES_PAR_DEFAUT = [
        'Contrat signé',
        'Badge',
        'Compte informatique',
        'Matériel',
        'Formation',
        'Inscription paie',
    ];

    public function __construct(
        private readonly OnboardingModel $onboardingModel,
        private readonly StatusOnboardingModel $statusModel,
        private readonly OnboardingHistoriqueModel $historiqueModel,
        private readonly TacheModel $tacheModel,
        private readonly StatusTacheModel $statusTacheModel,
        private readonly TacheHistoriqueModel $tacheHistoriqueModel,
        private readonly SalarieModel $salarieModel,
        private readonly AuditService $auditService,
        private readonly WorkflowService $workflowService,
    ) {}

    // demarrer
    public function demarrer(int $idSalarie, string $dateDebut, SessionUtilisateur $u): int
    {
        if (!$u->aLeRole(['rh', 'recruteur', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour démarrer un onboarding');
        }

        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        // Un seul onboarding actif par salarié
        if ($this->onboardingModel->onboardingActif($idSalarie) !== null) {
            throw new ConflitException("Un onboarding est déjà actif pour ce salarié");
        }

        $statutADemarrer = $this->statusModel->idParDesignation('À démarrer');

        $db = $this->onboardingModel->db;
        $db->transStart();

        try {
            $idOnboarding = $this->onboardingModel->insert([
                'id_salarie' => $idSalarie,
                'statut'     => $statutADemarrer,
                'date_debut' => $dateDebut,
                'date_fin'   => null,
            ], true);

            if ($idOnboarding === false) {
                throw new \RuntimeException('Échec insertion onboarding');
            }

            $this->workflowService->changerStatut(
                'onboarding',
                (int) $idOnboarding,
                'À démarrer',
                $u,
                'Démarrage de l\'onboarding'
            );

            $this->creerTachesParDefaut((int) $idOnboarding, $u);

            $this->auditService->journaliser(
                $u->idUtilisateur, 'CREATE', 'onboarding', (int) $idOnboarding,
                null, $u->adresseIp
            );

            $db->transComplete();
            return (int) $idOnboarding;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // creerTachesParDefaut
    public function creerTachesParDefaut(int $idOnboarding, SessionUtilisateur $u): array
    {
        $statutAFaire = $this->statusTacheModel->idParDesignation('À faire');
        $ids = [];

        foreach (self::TACHES_PAR_DEFAUT as $intitule) {
            $idTache = $this->tacheModel->insert([
                'id_onboarding'     => $idOnboarding,
                'intitule'          => $intitule,
                'description'       => null,
                'responsable'       => null,
                'date_echeance'     => null,
                'date_realisation'  => null,
                'statut'            => $statutAFaire,
            ], true);

            if ($idTache === false) {
                throw new \RuntimeException("Échec insertion tâche : {$intitule}");
            }

            $this->tacheHistoriqueModel->insert([
                'id_tache'         => $idTache,
                'statut_ancien'    => $statutAFaire,
                'statut_nouveau'   => $statutAFaire,
                'date_changement'  => date('Y-m-d H:i:s'),
                'id_utilisateur'   => $u->idUtilisateur,
                'commentaire'      => 'Création de la tâche',
            ]);

            $ids[] = (int) $idTache;
        }

        return $ids;
    }

    // onboardingActif (pour le salarié connecté)
    public function onboardingActif(int $idSalarie): ?\App\Entities\Personnel\Onboarding
    {
        return $this->onboardingModel->onboardingActif($idSalarie);
    }

    // creerTache
    public function creerTache(array $data, SessionUtilisateur $u): int
    {
        if (!$u->aLeRole(['rh', 'recruteur', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour créer une tâche');
        }

        $idOnboarding = (int) ($data['idOnboarding'] ?? 0);
        if ($this->onboardingModel->find($idOnboarding) === null) {
            throw new ObjetIntrouvableException("Onboarding #{$idOnboarding} introuvable");
        }

        $statutAFaire = $this->statusTacheModel->idParDesignation('À faire');

        $idTache = $this->tacheModel->insert([
            'id_onboarding'    => $idOnboarding,
            'intitule'         => $data['intitule'] ?? 'Tâche',
            'description'      => $data['description'] ?? null,
            'responsable'      => $data['responsable'] ?? null,
            'date_echeance'    => $data['dateEcheance'] ?? null,
            'date_realisation' => null,
            'statut'           => $statutAFaire,
        ], true);

        if ($idTache === false) {
            throw new \RuntimeException('Échec insertion tâche');
        }

        $this->auditService->journaliser(
            $u->idUtilisateur, 'CREATE', 'onboarding_tache', (int) $idTache,
            null, $u->adresseIp
        );

        return (int) $idTache;
    }

    // demarrerTache
    public function demarrerTache(int $idTache, SessionUtilisateur $u): void
    {
        $this->changerStatutTache($idTache, 'En cours', $u, 'Démarrage de la tâche');
    }

    // realiserTache
    public function realiserTache(int $idTache, SessionUtilisateur $u): void
    {
        $tache = $this->tacheModel->find($idTache);
        if ($tache === null) {
            throw new ObjetIntrouvableException("Tâche #{$idTache} introuvable");
        }

        $statutAFaire   = $this->statusTacheModel->idParDesignation('À faire');
        $statutEnCours  = $this->statusTacheModel->idParDesignation('En cours');

        if (!in_array((int) $tache->statut, [$statutAFaire, $statutEnCours], true)) {
            throw new StatutInvalideException('La tâche doit être À faire ou En cours');
        }

        $db = $this->tacheModel->db;
        $db->transStart();

        try {
            $this->tacheModel->update($idTache, ['date_realisation' => date('Y-m-d H:i:s')]);
            $this->changerStatutTache($idTache, 'Terminée', $u, 'Tâche réalisée');

            // Si progression == 100%, clôturer l'onboarding
            if ($this->progression((int) $tache->id_onboarding) >= 100.0) {
                $this->workflowService->changerStatut(
                    'onboarding',
                    (int) $tache->id_onboarding,
                    'Terminé',
                    $u,
                    'Intégration terminée'
                );
            }

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // bloquerTache
    public function bloquerTache(int $idTache, string $motif, SessionUtilisateur $u): void
    {
        $this->changerStatutTache($idTache, 'Bloquée', $u, $motif);
    }

    // annulerTache
    public function annulerTache(int $idTache, SessionUtilisateur $u): void
    {
        $this->changerStatutTache($idTache, 'Annulée', $u, 'Tâche annulée');
    }

    // progression
    public function progression(int $idOnboarding): float
    {
        $total = $this->tacheModel->compterTotal($idOnboarding);
        if ($total === 0) {
            return 0.0;
        }

        $statutTerminee = $this->statusTacheModel->idParDesignation('Terminée');
        $statutAnnulee  = $this->statusTacheModel->idParDesignation('Annulée');

        $terminees = $this->tacheModel->compterParStatut($idOnboarding, $statutTerminee)
                   + $this->tacheModel->compterParStatut($idOnboarding, $statutAnnulee);

        return round(($terminees / $total) * 100, 2);
    }

    // cloturer
    public function cloturer(int $idOnboarding, SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour clôturer un onboarding');
        }

        $onboarding = $this->onboardingModel->find($idOnboarding);
        if ($onboarding === null) {
            throw new ObjetIntrouvableException("Onboarding #{$idOnboarding} introuvable");
        }

        if ($this->progression($idOnboarding) < 100.0) {
            throw new StatutInvalideException('Toutes les tâches doivent être terminées ou annulées');
        }

        $db = $this->onboardingModel->db;
        $db->transStart();

        try {
            $this->onboardingModel->update($idOnboarding, ['date_fin' => date('Y-m-d')]);
            $this->workflowService->changerStatut(
                'onboarding', $idOnboarding, 'Terminé', $u, 'Clôture de l\'onboarding'
            );

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // annuler
    public function annuler(int $idOnboarding, string $motif, SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour annuler un onboarding');
        }

        $onboarding = $this->onboardingModel->find($idOnboarding);
        if ($onboarding === null) {
            throw new ObjetIntrouvableException("Onboarding #{$idOnboarding} introuvable");
        }

        $db = $this->onboardingModel->db;
        $db->transStart();

        try {
            $this->workflowService->changerStatut(
                'onboarding', $idOnboarding, 'Annulé', $u, $motif
            );

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // consulter
    public function consulter(int $idOnboarding): ?array
    {
        $row = $this->requeteOnboardings()
            ->where('o.id_onboarding', $idOnboarding)
            ->get()
            ->getRowArray();

        if ($row !== null) {
            $row['progression'] = $this->progression($idOnboarding);
        }

        return $row;
    }

    // lister
    public function lister(array $filtre = []): array
    {
        $requete = $this->requeteOnboardings();

        if (!empty($filtre['statut'])) {
            $statut = is_numeric($filtre['statut'])
                ? (int) $filtre['statut']
                : $this->statusModel->idParDesignation((string) $filtre['statut']);
            $requete->where('o.statut', $statut);
        }
        if (!empty($filtre['idSalarie'])) {
            $requete->where('o.id_salarie', $filtre['idSalarie']);
        }

        [$colonne, $ordre] = Tri::resoudre($filtre, [
            'id_onboarding' => 'o.id_onboarding',
            'id_salarie'    => 'o.id_salarie',
            'date_debut'    => 'o.date_debut',
            'date_fin'      => 'o.date_fin',
            'statut'        => 'so.designation',
        ], 'o.date_debut', 'DESC');

        return $requete->orderBy($colonne, $ordre)->get()->getResultArray();
    }

    private function requeteOnboardings()
    {
        $statutTerminee = $this->statusTacheModel->idParDesignation('Terminée');
        $statutAnnulee = $this->statusTacheModel->idParDesignation('Annulée');

        return $this->onboardingModel->db->table('onboarding o')
            ->select('o.*, so.designation AS statut_designation, s.nom AS salarie_nom, s.prenom AS salarie_prenom')
            ->select('(SELECT COUNT(*) FROM onboarding_tache WHERE id_onboarding = o.id_onboarding) AS taches_total')
            ->select("(SELECT COUNT(*) FROM onboarding_tache WHERE id_onboarding = o.id_onboarding AND statut IN ({$statutTerminee}, {$statutAnnulee})) AS taches_terminees")
            ->join('status_onboarding so', 'so.id = o.statut', 'left')
            ->join('salarie s', 's.id_salarie = o.id_salarie', 'left');
    }

    // historique
    public function historique(int $idOnboarding): array
    {
        return $this->historiqueModel->listerParOnboarding($idOnboarding);
    }

    // listerTaches
    public function listerTaches(int $idOnboarding): array
    {
        return $this->tacheModel->listerParOnboarding($idOnboarding);
    }

    // consulterTache
    public function consulterTache(int $idTache): ?array
    {
        $tache = $this->tacheModel->find($idTache);
        return $tache ? $tache->toArray() : null;
    }

    // modifierTache
    public function modifierTache(int $idTache, array $modifications, SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'recruteur', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour modifier une tâche');
        }

        $tache = $this->tacheModel->find($idTache);
        if ($tache === null) {
            throw new ObjetIntrouvableException("Tâche #{$idTache} introuvable");
        }

        // Champs modifiables uniquement
        $autorises = ['intitule', 'description', 'responsable', 'date_echeance'];
        $payload = array_intersect_key($modifications, array_flip($autorises));

        if (empty($payload)) {
            return;
        }

        $this->tacheModel->update($idTache, $payload);

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'UPDATE',
            'onboarding_tache',
            $idTache,
            json_encode($payload),
            $u->adresseIp
        );
    }

    // modifier
    public function modifier(int $idOnboarding, array $modifications, SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour modifier un onboarding');
        }

        $onboarding = $this->onboardingModel->find($idOnboarding);
        if ($onboarding === null) {
            throw new ObjetIntrouvableException("Onboarding #{$idOnboarding} introuvable");
        }

        // Champs modifiables uniquement
        $autorises = ['date_debut', 'date_fin'];
        $payload = array_intersect_key($modifications, array_flip($autorises));

        if (empty($payload)) {
            return;
        }

        $this->onboardingModel->update($idOnboarding, $payload);

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'UPDATE',
            'onboarding',
            $idOnboarding,
            json_encode($payload),
            $u->adresseIp
        );
    }

    // changerStatutTache
    private function changerStatutTache(int $idTache, string $statutCible, SessionUtilisateur $u, ?string $commentaire = null): void
    {
        $tache = $this->tacheModel->find($idTache);
        if ($tache === null) {
            throw new ObjetIntrouvableException("Tâche #{$idTache} introuvable");
        }

        $ancienId  = (int) $tache->statut;
        $nouveauId = $this->statusTacheModel->idParDesignation($statutCible);

        if ($ancienId === $nouveauId) {
            return;
        }

        $this->tacheModel->update($idTache, ['statut' => $nouveauId]);

        $this->tacheHistoriqueModel->insert([
            'id_tache'        => $idTache,
            'statut_ancien'   => $ancienId,
            'statut_nouveau'  => $nouveauId,
            'date_changement' => date('Y-m-d H:i:s'),
            'id_utilisateur'  => $u->idUtilisateur,
            'commentaire'     => $commentaire,
        ]);

        $this->auditService->journaliser(
            $u->idUtilisateur, 'UPDATE', 'onboarding_tache', $idTache,
            $commentaire, $u->adresseIp
        );
    }
}