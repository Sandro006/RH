<?php

declare(strict_types=1);

namespace App\Services\Common;

use App\Libraries\Common\Roles;
use App\Libraries\Common\SessionUtilisateur;
use CodeIgniter\Database\BaseConnection;

class DashboardService
{
    private const DEMANDES_EN_ATTENTE = [
        'En cours',
        'En contrôle RH',
        'En validation budgétaire',
        'En validation hiérarchique',
        'En consultation interne',
        'À corriger',
    ];

    private const CANDIDATURES_ACTIVES_EXCLUES = ['Refusée', 'Retirée'];
    private const ENTRETIENS_A_VENIR           = ['Planifié', 'Confirmé'];
    private const PROPOSITIONS_EN_COURS        = ['À envoyer', 'Envoyée', 'En négociation'];
    private const TACHES_ONBOARDING_ACTIVES    = ['À faire', 'En cours', 'Bloquée'];
    private const ONBOARDINGS_ACTIFS           = ['À démarrer', 'En cours', 'En attente'];

    public function __construct(
        private readonly BaseConnection $db,
    ) {
    }

    public function indicateurs(SessionUtilisateur $utilisateur): array
    {
        return [
            'demandesEnAttente'  => $this->compterSecurise(fn (): int => $this->compterDemandesEnAttente()),
            'offresPubliees'     => $this->compterSecurise(fn (): int => $this->compterOffresPubliees()),
            'candidatures'       => $this->compterSecurise(fn (): int => $this->compterCandidaturesActives()),
            'entretiensAVenir'   => $this->compterSecurise(fn (): int => $this->compterEntretiensAVenir()),
            'propositionsEnCours' => $this->compterSecurise(fn (): int => $this->compterPropositionsEnCours()),
            'tachesOnboarding'   => $this->compterSecurise(fn (): int => $this->compterTachesOnboarding()),
        ];
    }

    public function actionsEnAttente(SessionUtilisateur $utilisateur): array
    {
        return match ($utilisateur->role) {
            Roles::ADMIN             => $this->actionsAdmin(),
            Roles::MANAGER           => $this->actionsManager($utilisateur),
            Roles::CONTROLE_GESTION  => $this->actionsControleGestion(),
            Roles::DIRECTION         => $this->actionsDirection(),
            Roles::RH                => $this->actionsRh(),
            Roles::RECRUTEUR         => $this->actionsRecruteur(),
            Roles::SALARIE           => $this->actionsSalarie($utilisateur),
            default                  => [],
        };
    }

    private function compterDemandesEnAttente(): int
    {
        return $this->db->table('demande_recrutement d')
            ->join('status s', 's.id = d.statut')
            ->whereIn('s.designation', self::DEMANDES_EN_ATTENTE)
            ->countAllResults();
    }

    private function compterOffresPubliees(): int
    {
        return $this->db->table('offre_emploi o')
            ->join('status_offre_emploi s', 's.id = o.statut')
            ->where('s.designation', 'Publiée')
            ->groupStart()
                ->where('o.date_cloture', null)
                ->orWhere('o.date_cloture >=', date('Y-m-d'))
            ->groupEnd()
            ->countAllResults();
    }

    private function compterCandidaturesActives(): int
    {
        return $this->db->table('candidature c')
            ->join('status_candidature s', 's.id = c.statut')
            ->whereNotIn('s.designation', self::CANDIDATURES_ACTIVES_EXCLUES)
            ->countAllResults();
    }

    private function compterEntretiensAVenir(): int
    {
        return $this->db->table('entretien e')
            ->join('status_entretien s', 's.id = e.statut')
            ->where('e.date_entretien >=', date('Y-m-d H:i:s'))
            ->whereIn('s.designation', self::ENTRETIENS_A_VENIR)
            ->countAllResults();
    }

    private function compterPropositionsEnCours(): int
    {
        return $this->db->table('proposition_embauche p')
            ->join('status_proposition_embauche s', 's.id = p.statut')
            ->whereIn('s.designation', self::PROPOSITIONS_EN_COURS)
            ->countAllResults();
    }

    private function compterTachesOnboarding(): int
    {
        return $this->db->table('onboarding_tache t')
            ->join('status_onboarding_tache st', 'st.id = t.statut')
            ->join('onboarding o', 'o.id_onboarding = t.id_onboarding')
            ->join('status_onboarding so', 'so.id = o.statut')
            ->whereIn('st.designation', self::TACHES_ONBOARDING_ACTIVES)
            ->whereIn('so.designation', self::ONBOARDINGS_ACTIFS)
            ->countAllResults();
    }

    private function actionsAdmin(): array
    {
        $nombre = $this->compterSecurise(fn (): int => $this->db->table('utilisateur')
            ->where('actif', false)
            ->countAllResults());

        return $this->ligne('comptesInactifs', 'Comptes désactivés', $nombre, 'pages/admin/utilisateurs.html?actif=0');
    }

    private function actionsManager(SessionUtilisateur $utilisateur): array
    {
        $nombre = $this->compterSecurise(fn (): int => $this->compterDemandesParStatuts(
            ['En cours', 'À corriger'],
            $utilisateur->idEmploye
        ));

        $lien = $utilisateur->idEmploye !== null
            ? '/recrutement/demandes?id_demandeur=' . $utilisateur->idEmploye
            : '/recrutement/demandes';

        return $this->ligne('demandesAModifier', 'Mes demandes à compléter', $nombre, $lien);
    }

    private function actionsControleGestion(): array
    {
        $nombre = $this->compterSecurise(fn (): int => $this->compterDemandesParStatuts(
            ['En validation budgétaire']
        ));

        return $this->ligne('validationsBudget', 'Demandes à valider (budget)', $nombre, '/recrutement/demandes?statut=En%20validation%20budg%C3%A9taire');
    }

    private function actionsDirection(): array
    {
        $nombre = $this->compterSecurise(fn (): int => $this->compterDemandesParStatuts(
            ['En validation hiérarchique']
        ));

        return $this->ligne('validationsHierarchiques', 'Demandes à valider (hiérarchie)', $nombre, '/recrutement/demandes?statut=En%20validation%20hi%C3%A9rarchique');
    }

    private function actionsRh(): array
    {
        $demandes = $this->compterSecurise(fn (): int => $this->compterDemandesParStatuts(['En contrôle RH']));
        $propositions = $this->compterSecurise(fn (): int => $this->compterPropositionsParStatuts(['À envoyer']));
        $onboarding = $this->compterSecurise(fn (): int => $this->compterTachesOnboardingEnRetard());

        return [
            ...$this->ligne('controlesRh', 'Demandes à contrôler', $demandes, '/recrutement/demandes?statut=En%20contr%C3%B4le%20RH'),
            ...$this->ligne('propositionsAEnvoyer', 'Propositions à envoyer', $propositions, 'pages/recrutement/propositions.html'),
            ...$this->ligne('onboardingEnRetard', 'Tâches d’onboarding en retard', $onboarding, 'pages/personnel/onboarding.html'),
        ];
    }

    private function actionsRecruteur(): array
    {
        $candidatures = $this->compterSecurise(fn (): int => $this->compterCandidaturesParStatuts(['Reçue', 'En présélection']));
        $entretiens   = $this->compterSecurise(fn (): int => $this->compterEntretiensAVenir());

        return [
            ...$this->ligne('candidaturesATraiter', 'Candidatures à traiter', $candidatures, 'pages/recrutement/candidatures.html'),
            ...$this->ligne('entretiensAVenir', 'Entretiens à venir', $entretiens, 'pages/recrutement/entretiens.html'),
        ];
    }

    private function actionsSalarie(SessionUtilisateur $utilisateur): array
    {
        $idSalarie = $this->idSalarieDepuisEmploye($utilisateur->idEmploye);
        $nombre = $this->compterSecurise(fn (): int => $this->compterTachesSalarie($idSalarie));

        return $this->ligne('mesTachesOnboarding', 'Mes tâches d’onboarding', $nombre, 'pages/personnel/onboarding.html');
    }

    private function compterDemandesParStatuts(array $statuts, ?int $idDemandeur = null): int
    {
        $builder = $this->db->table('demande_recrutement d')
            ->join('status s', 's.id = d.statut')
            ->whereIn('s.designation', $statuts);

        if ($idDemandeur !== null) {
            $builder->where('d.id_demandeur', $idDemandeur);
        }

        return $builder->countAllResults();
    }

    private function compterCandidaturesParStatuts(array $statuts): int
    {
        return $this->db->table('candidature c')
            ->join('status_candidature s', 's.id = c.statut')
            ->whereIn('s.designation', $statuts)
            ->countAllResults();
    }

    private function compterPropositionsParStatuts(array $statuts): int
    {
        return $this->db->table('proposition_embauche p')
            ->join('status_proposition_embauche s', 's.id = p.statut')
            ->whereIn('s.designation', $statuts)
            ->countAllResults();
    }

    private function compterTachesOnboardingEnRetard(): int
    {
        return $this->db->table('onboarding_tache t')
            ->join('status_onboarding_tache st', 'st.id = t.statut')
            ->join('onboarding o', 'o.id_onboarding = t.id_onboarding')
            ->join('status_onboarding so', 'so.id = o.statut')
            ->whereIn('st.designation', self::TACHES_ONBOARDING_ACTIVES)
            ->whereIn('so.designation', self::ONBOARDINGS_ACTIFS)
            ->where('t.date_echeance <', date('Y-m-d'))
            ->countAllResults();
    }

    private function compterTachesSalarie(?int $idSalarie): int
    {
        if ($idSalarie === null) {
            return 0;
        }

        return $this->db->table('onboarding_tache t')
            ->join('status_onboarding_tache st', 'st.id = t.statut')
            ->join('onboarding o', 'o.id_onboarding = t.id_onboarding')
            ->where('o.id_salarie', $idSalarie)
            ->whereIn('st.designation', self::TACHES_ONBOARDING_ACTIVES)
            ->countAllResults();
    }

    private function idSalarieDepuisEmploye(?int $idEmploye): ?int
    {
        if ($idEmploye === null) {
            return null;
        }

        $ligne = $this->db->table('employe')
            ->select('id_salarie')
            ->where('id', $idEmploye)
            ->get()
            ->getRow();

        return $ligne !== null ? (int) $ligne->id_salarie : null;
    }

    private function compterSecurise(callable $compteur): int
    {
        try {
            return $compteur();
        } catch (\Throwable $e) {
            log_message('error', 'Compteur dashboard indisponible : ' . $e->getMessage());

            return 0;
        }
    }

    private function ligne(string $code, string $libelle, int $nombre, string $lien): array
    {
        return [[
            'code'    => $code,
            'libelle' => $libelle,
            'nombre'  => $nombre,
            'lien'    => $lien,
        ]];
    }
}
