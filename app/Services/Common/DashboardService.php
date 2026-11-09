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
        if ($utilisateur->role === Roles::SALARIE) {
            return $this->indicateursSalarie($utilisateur);
        }

        return [
            'demandesEnAttente'  => $this->compterSecurise(fn (): int => $this->compterDemandesEnAttente()),
            'offresPubliees'     => $this->compterSecurise(fn (): int => $this->compterOffresPubliees()),
            'candidatures'       => $this->compterSecurise(fn (): int => $this->compterCandidaturesActives()),
            'entretiensAVenir'   => $this->compterSecurise(fn (): int => $this->compterEntretiensAVenir()),
            'propositionsEnCours' => $this->compterSecurise(fn (): int => $this->compterPropositionsEnCours()),
            'tachesOnboarding'   => $this->compterSecurise(fn (): int => $this->compterTachesOnboarding()),
            'documentsExpirant'  => $this->compterSecurise(fn (): int => $this->compterDocumentsExpirant($utilisateur)),
            'documentsExpires'   => $this->compterSecurise(fn (): int => $this->compterDocumentsExpires($utilisateur)),
        ];
    }

    /**
     * Informations d'affichage de l'utilisateur connecté (en-tête du tableau de bord) :
     * identifiant + rôle, et si lié à un employé : identité complète, poste, service, département.
     */
    public function infosUtilisateur(SessionUtilisateur $utilisateur): array
    {
        $infos = [
            'identifiant' => $utilisateur->identifiant,
            'role'        => $utilisateur->role,
        ];

        if ($utilisateur->idEmploye === null) {
            return $infos;
        }

        try {
            $ligne = $this->db->table('employe e')
                ->select('s.matricule, s.nom, s.prenom, s.email, s.telephone, p.intitule AS poste, sv.nom AS service, d.nom AS departement')
                ->join('salarie s', 's.id_salarie = e.id_salarie', 'left')
                ->join('poste p', 'p.id_poste = e.poste_id', 'left')
                ->join('service sv', 'sv.id_service = p.id_service', 'left')
                ->join('departement d', 'd.id_departement = sv.id_departement', 'left')
                ->where('e.id', $utilisateur->idEmploye)
                ->get()
                ->getRow();

            if ($ligne !== null) {
                $nomComplet = trim(($ligne->prenom ?? '') . ' ' . ($ligne->nom ?? ''));
                if ($nomComplet === '') {
                    $nomComplet = (string) ($ligne->nom ?? $utilisateur->identifiant);
                }
                $infos += [
                    'matricule'    => $ligne->matricule,
                    'nom'          => $ligne->nom,
                    'prenom'       => $ligne->prenom,
                    'nomComplet'   => $nomComplet,
                    'email'        => $ligne->email,
                    'telephone'    => $ligne->telephone,
                    'poste'        => $ligne->poste,
                    'service'      => $ligne->service,
                    'departement'  => $ligne->departement,
                ];
            }
        } catch (\Throwable $e) {
            log_message('error', 'Infos utilisateur dashboard indisponibles : ' . $e->getMessage());
        }

        return $infos;
    }

    /**
     * Tableau de bord salarié « Mon service » : son propre solde, ses demandes
     * et la présence de son service aujourd'hui (voir ecran Congés Bloc 7).
     * Les clés sont mappées par dashboard.js vers les cartes existantes.
     */
    private function indicateursSalarie(SessionUtilisateur $utilisateur): array
    {
        $idSalarie = $this->idSalarieDepuisEmploye($utilisateur->idEmploye);

        return [
            'mesDemandesEnAttente' => $this->compterSecurise(fn (): int => $this->compterMesDemandesEnAttente($idSalarie)),
            // Réutilise les clés historiques de dashboard.js (libellés remplacés côté JS).
            'demandesEnAttente'   => $this->compterSecurise(fn (): int => $this->compterMesDemandesEnAttente($idSalarie)),
            'offresPubliees'      => $this->compterSecurise(fn (): float => $this->soldesDisponibleSalarie($idSalarie)),
            'candidatures'        => $this->compterSecurise(fn (): int => $this->compterAbsentsServiceAujourdHui($idSalarie)),
            'tachesOnboarding'    => $this->compterSecurise(fn (): int => $this->compterTachesSalarie($idSalarie)),
            'service'             => $this->compterSecurise(fn () => $this->serviceDuSalarie($idSalarie)),
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

    /**
     * Pièces actives qui expirent dans les 30 prochains jours (RH / admin).
     */
    private function compterDocumentsExpirant(SessionUtilisateur $utilisateur): int
    {
        if (!$utilisateur->aLeRole(['admin', 'rh'])) {
            return 0;
        }

        return $this->db->table('document')
            ->where('archive', false)
            ->where('date_expiration IS NOT NULL', null, false)
            ->where('date_expiration >=', date('Y-m-d'))
            ->where('date_expiration <=', date('Y-m-d', strtotime('+30 days')))
            ->countAllResults();
    }

    /**
     * Pièces actives déjà expirées (RH / admin).
     */
    private function compterDocumentsExpires(SessionUtilisateur $utilisateur): int
    {
        if (!$utilisateur->aLeRole(['admin', 'rh'])) {
            return 0;
        }

        return $this->db->table('document')
            ->where('archive', false)
            ->where('date_expiration IS NOT NULL', null, false)
            ->where('date_expiration <', date('Y-m-d'))
            ->countAllResults();
    }

    private function actionsAdmin(): array
    {
        $nombre = $this->compterSecurise(fn (): int => $this->db->table('utilisateur')
            ->where('actif', false)
            ->countAllResults());

        return $this->ligne('comptesInactifs', 'Comptes désactivés', $nombre, '/securite/acces');
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
            ...$this->ligne('propositionsAEnvoyer', 'Propositions à envoyer', $propositions, '/selection/propositions'),
            ...$this->ligne('onboardingEnRetard', 'Tâches d’onboarding en retard', $onboarding, '/personnel/onboardings'),
        ];
    }

    private function actionsRecruteur(): array
    {
        $candidatures = $this->compterSecurise(fn (): int => $this->compterCandidaturesParStatuts(['Reçue', 'En présélection']));
        $entretiens   = $this->compterSecurise(fn (): int => $this->compterEntretiensAVenir());

        return [
            ...$this->ligne('candidaturesATraiter', 'Candidatures à traiter', $candidatures, '/recrutement/candidatures'),
            ...$this->ligne('entretiensAVenir', 'Entretiens à venir', $entretiens, '/selection/entretiens'),
        ];
    }

    private function actionsSalarie(SessionUtilisateur $utilisateur): array
    {
        $idSalarie = $this->idSalarieDepuisEmploye($utilisateur->idEmploye);

        $taches = $this->compterSecurise(fn (): int => $this->compterTachesSalarie($idSalarie));
        $demandes = $this->compterSecurise(fn (): int => $this->compterMesDemandesEnAttente($idSalarie));
        $absents = $this->compterSecurise(fn (): int => $this->compterAbsentsServiceAujourdHui($idSalarie));

        return [
            ...$this->ligne('mesTachesOnboarding', 'Mes tâches d’onboarding', $taches, '/personnel/mes-taches'),
            ...$this->ligne('mesDemandesEnAttente', 'Mes demandes de congé en attente', $demandes, '/personnel/mes-conges'),
            ...$this->ligne('servicePlanning', 'Planning de mon service (absents aujourd’hui)', $absents, '/personnel/conges-planning'),
        ];
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

    private function compterMesDemandesEnAttente(?int $idSalarie): int
    {
        if ($idSalarie === null) {
            return 0;
        }

        return $this->db->table('demande_conge dc')
            ->join('statut_demande_conge sdc', 'sdc.id_statut = dc.id_statut')
            ->where('dc.id_salarie', $idSalarie)
            ->whereIn('sdc.code', ['EN_ATTENTE_MANAGER', 'EN_ATTENTE_RH'])
            ->countAllResults();
    }

    /**
     * Total des jours disponibles sur l'année courante, tous types déduits.
     */
    private function soldesDisponibleSalarie(?int $idSalarie): float
    {
        if ($idSalarie === null) {
            return 0.0;
        }

        $ligne = $this->db->table('solde_conge')
            ->select('COALESCE(SUM(droit_acquis + report - pris - en_attente), 0) AS total')
            ->where('id_salarie', $idSalarie)
            ->where('annee', (int) date('Y'))
            ->get()
            ->getRow();

        return $ligne !== null ? (float) $ligne->total : 0.0;
    }

    /**
     * Nombre de salariés absents aujourd'hui dans le service du salarié courant.
     */
    private function compterAbsentsServiceAujourdHui(?int $idSalarie): int
    {
        if ($idSalarie === null) {
            return 0;
        }

        $ligne = $this->db->table('employe e')
            ->select('poste.id_service')
            ->join('poste', 'poste.id_poste = e.poste_id')
            ->where('e.id_salarie', $idSalarie)
            ->get()
            ->getRow();

        if ($ligne === null || $ligne->id_service === null) {
            return 0;
        }

        return $this->db->table('absence a')
            ->join('employe e2', 'e2.id_salarie = a.id_salarie')
            ->join('poste p2', 'p2.id_poste = e2.poste_id')
            ->where('p2.id_service', $ligne->id_service)
            ->where('a.date_debut <=', date('Y-m-d'))
            ->where('a.date_fin >=', date('Y-m-d'))
            ->countAllResults('a.id_salarie', true);
    }

    private function serviceDuSalarie(?int $idSalarie): ?string
    {
        if ($idSalarie === null) {
            return null;
        }

        $ligne = $this->db->table('employe e')
            ->select('service.nom AS service')
            ->join('poste', 'poste.id_poste = e.poste_id')
            ->join('service', 'service.id_service = poste.id_service')
            ->where('e.id_salarie', $idSalarie)
            ->get()
            ->getRow();

        return $ligne !== null ? (string) $ligne->service : null;
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
