<?php

declare(strict_types=1);

namespace App\Services\Conges;

use App\Exceptions\CongeException;
use App\Models\Conges\DemandeCongeModel;
use App\Models\Conges\DroitCongeModel;
use App\Models\Conges\MouvementSoldeModel;
use App\Models\Conges\SoldeCongeModel;
use App\Models\Conges\TypeCongeModel;
use App\Models\Personnel\SalarieModel;

/**
 * Convention mouvement_solde.nb_jours (journal lisible, signe en-tête SQL) :
 *   ACQUISITION +n (droit_acquis += n), RESERVATION −n (en_attente += n),
 *   DEBIT −n (en_attente −= n, pris += n), RECREDIT +n (rendre),
 *   REPORT +n, EXPIRATION −n, AJUSTEMENT ±n.
 */
class SoldeService
{
    private const REPORT_LIMITE_JOURS = 5.0;

    public function __construct(
        private readonly SoldeCongeModel $soldeModel,
        private readonly MouvementSoldeModel $mouvementModel,
        private readonly TypeCongeModel $typeCongeModel,
        private readonly DroitCongeModel $droitCongeModel,
        private readonly DemandeCongeModel $demandeModel,
        private readonly SalarieModel $salarieModel,
        private readonly CalendrierService $calendrier,
        private readonly AuditService $audit,
    ) {
    }

    public function soldeDisponible(int $idSalarie, int $idType, int $annee): float
    {
        $solde = $this->soldeModel->trouver($idSalarie, $idType, $annee);

        return $solde === null ? 0.0 : $this->soldeModel->disponible($solde);
    }

    /**
     * Réserve les jours (RG10) : en_attente += nb_jours par part annuelle (RG13).
     * Lancé DANS une transaction par CongeService. Type sans déduction = no-op.
     */
    public function reserver(int $idSalarie, int $idType, string $debut, string $fin, int $idDemande): void
    {
        if (! $this->deduitSolde($idType)) {
            return;
        }

        $demande = $this->demandeModel->find($idDemande);
        $parts = $this->calendrier->repartirParAnnee(
            $debut,
            $fin,
            (bool) ($demande->demi_journee_debut ?? false),
            (bool) ($demande->demi_journee_fin ?? false),
        );

        if ($parts === []) {
            return;
        }

        foreach ($parts as $annee => $nbJours) {
            $this->soldeModel->assurer($idSalarie, $idType, (int) $annee);
            /** @var object $solde */
            $solde = $this->soldeModel->verrouiller($idSalarie, $idType, (int) $annee);
            $disponible = $this->soldeModel->disponible($solde);

            if ($nbJours > $disponible) {
                throw new CongeException(
                    "Solde insuffisant pour {$annee} (disponible : {$disponible}, demandé : {$nbJours})",
                    CongeException::SOLDE_INSUFFISANT
                );
            }

            $this->soldeModel->update($solde->id_solde_conge, [
                'en_attente' => round((float) $solde->en_attente + $nbJours, 2),
            ]);

            $this->mouvementModel->insert([
                'id_solde_conge'   => $solde->id_solde_conge,
                'id_demande_conge' => $idDemande,
                'type_mouvement'   => 'RESERVATION',
                'nb_jours'         => -$nbJours,
                'date_mouvement'   => date('Y-m-d H:i:s'),
                'commentaire'      => "Réservation demande #{$idDemande} ({$annee})",
            ], true);
        }
    }

    /**
     * Passe les jours réservés en « pris » (RG10) : en_attente → pris.
     * Lancé DANS une transaction par CongeService::validerRh.
     */
    public function debiter(int $idDemande): void
    {
        $demande = $this->demandeModel->find($idDemande);
        if ($demande === null) {
            throw new CongeException("Demande #{$idDemande} introuvable", CongeException::OBJET_INTROUVABLE);
        }
        if (! $this->deduitSolde((int) $demande->id_type_conge)) {
            return;
        }

        $parts = $this->calendrier->repartirParAnnee(
            (string) $demande->date_debut,
            (string) $demande->date_fin,
            (bool) $demande->demi_journee_debut,
            (bool) $demande->demi_journee_fin,
        );

        foreach ($parts as $annee => $nbJours) {
            $this->soldeModel->assurer((int) $demande->id_salarie, (int) $demande->id_type_conge, (int) $annee);
            /** @var object $solde */
            $solde = $this->soldeModel->verrouiller((int) $demande->id_salarie, (int) $demande->id_type_conge, (int) $annee);

            $enAttente = max(0.0, round((float) $solde->en_attente - $nbJours, 2));
            $this->soldeModel->update($solde->id_solde_conge, [
                'en_attente' => $enAttente,
                'pris'       => round((float) $solde->pris + $nbJours, 2),
            ]);

            $this->mouvementModel->insert([
                'id_solde_conge'   => $solde->id_solde_conge,
                'id_demande_conge' => $idDemande,
                'type_mouvement'   => 'DEBIT',
                'nb_jours'         => -$nbJours,
                'date_mouvement'   => date('Y-m-d H:i:s'),
                'commentaire'      => "Débit demande #{$idDemande} ({$annee})",
            ], true);
        }
    }

    /**
     * Rend les jours (refus, annulation) : en_attente ou pris selon $dejaDebite.
     */
    public function recrediter(int $idDemande, bool $dejaDebite): void
    {
        $demande = $this->demandeModel->find($idDemande);
        if ($demande === null) {
            throw new CongeException("Demande #{$idDemande} introuvable", CongeException::OBJET_INTROUVABLE);
        }
        if (! $this->deduitSolde((int) $demande->id_type_conge)) {
            return;
        }

        $parts = $this->calendrier->repartirParAnnee(
            (string) $demande->date_debut,
            (string) $demande->date_fin,
            (bool) $demande->demi_journee_debut,
            (bool) $demande->demi_journee_fin,
        );

        foreach ($parts as $annee => $nbJours) {
            $solde = $this->soldeModel->trouver((int) $demande->id_salarie, (int) $demande->id_type_conge, (int) $annee);
            if ($solde === null) {
                continue;
            }

            $bucket = $dejaDebite ? 'pris' : 'en_attente';
            $this->soldeModel->update($solde->id_solde_conge, [
                $bucket => max(0.0, round((float) $solde->$bucket - $nbJours, 2)),
            ]);

            $this->mouvementModel->insert([
                'id_solde_conge'   => $solde->id_solde_conge,
                'id_demande_conge' => $idDemande,
                'type_mouvement'   => 'RECREDIT',
                'nb_jours'         => $nbJours,
                'date_mouvement'   => date('Y-m-d H:i:s'),
                'commentaire'      => "Recrédit demande #{$idDemande} ({$annee})",
            ], true);
        }
    }

    /**
     * Acquisition mensuelle pour tous les salariés actifs (commande conges:acquisition).
     * Proratisée si l'embauche tombe dans le mois visé. Idempotent : un
     * mouvement ACQUISITION déjà présent pour (solde, mois) bloque le doublon.
     *
     * @return int nombre de lignes de solde créditées
     */
    public function acquerirMensuel(string $mois): int
    {
        [$annee, $moisNum] = array_map('intval', explode('-', $mois));
        $premier = sprintf('%04d-%02d-01', $annee, $moisNum);
        $dernier = date('Y-m-t', strtotime($premier));
        $nbJoursMois = (int) date('t', strtotime($premier));

        /** @var array<int> $salaries */
        $salaries = $this->salarieModel->db->table('salarie')
            ->where('date_embauche <=', $dernier)
            ->groupStart()
                ->where('date_sortie', null)
                ->orWhere('date_sortie >=', $dernier)
            ->groupEnd()
            ->get()
            ->getResultArray();

        $types = $this->typeCongeModel->listerActifs();
        if ($types === []) {
            return 0;
        }

        $traites = 0;

        foreach ($salaries as $ligne) {
            foreach ($types as $type) {
                if (! (bool) $type->deduit_solde) {
                    continue;
                }

                $idSalarie = (int) $ligne['id_salarie'];
                $idType = (int) $type->id_type_conge;
                $droit = $this->droitCongeModel->droitActif($idType, null, $dernier);
                if ($droit === null) {
                    continue;
                }

                $acquis = (float) $droit->acquisition_mensuelle;
                if ($acquis <= 0) {
                    continue;
                }

                // Proratisation : embauche dans le mois visé.
                if (substr((string) $ligne['date_embauche'], 0, 7) === $mois) {
                    $jour = (int) substr((string) $ligne['date_embauche'], 8, 2);
                    $acquis = round($acquis * ($nbJoursMois - $jour + 1) / $nbJoursMois, 2);
                }

                $solde = $this->soldeModel->assurer($idSalarie, $idType, $annee);

                $deja = $this->mouvementModel->db->table('mouvement_solde')
                    ->where('id_solde_conge', $solde->id_solde_conge)
                    ->where('type_mouvement', 'ACQUISITION')
                    ->where('commentaire', "Acq {$mois}")
                    ->countAllResults();

                if ($deja > 0) {
                    continue;
                }

                $this->soldeModel->update($solde->id_solde_conge, [
                    'droit_acquis' => round((float) $solde->droit_acquis + $acquis, 2),
                ]);

                $this->mouvementModel->insert([
                    'id_solde_conge'   => $solde->id_solde_conge,
                    'id_demande_conge' => null,
                    'type_mouvement'   => 'ACQUISITION',
                    'nb_jours'         => $acquis,
                    'date_mouvement'   => date('Y-m-d H:i:s'),
                    'commentaire'      => "Acq {$mois}",
                ], true);

                $traites++;
            }
        }

        return $traites;
    }

    /**
     * Clôture d'année : report limité à REPORT_LIMITE_JOURS, reste expiré (RG12).
     * Crée la ligne N+1 avec le report transféré (sans solder la ligne N).
     */
    public function cloturerAnnee(int $annee): void
    {
        $soldes = $this->soldeModel->where('annee', $annee)->findAll();

        foreach ($soldes as $solde) {
            $disponible = $this->soldeModel->disponible($solde);
            $transfere = min(max(0.0, $disponible), self::REPORT_LIMITE_JOURS);
            $expire = max(0.0, round($disponible - $transfere, 2));

            if ($transfere > 0) {
                $suivant = $this->soldeModel->assurer((int) $solde->id_salarie, (int) $solde->id_type_conge, $annee + 1);
                $this->soldeModel->update($suivant->id_solde_conge, [
                    'report' => round((float) $suivant->report + $transfere, 2),
                ]);

                $this->mouvementModel->insert([
                    'id_solde_conge'   => $solde->id_solde_conge,
                    'id_demande_conge' => null,
                    'type_mouvement'   => 'REPORT',
                    'nb_jours'         => $transfere,
                    'date_mouvement'   => date('Y-m-d H:i:s'),
                    'commentaire'      => "Report clôture {$annee} vers " . ($annee + 1),
                ], true);
            }

            if ($expire > 0) {
                $this->mouvementModel->insert([
                    'id_solde_conge'   => $solde->id_solde_conge,
                    'id_demande_conge' => null,
                    'type_mouvement'   => 'EXPIRATION',
                    'nb_jours'         => -$expire,
                    'date_mouvement'   => date('Y-m-d H:i:s'),
                    'commentaire'      => "Expiration clôture {$annee}",
                ], true);
            }
        }
    }

    public function ajuster(int $idSalarie, int $idType, int $annee, float $nbJours, string $commentaire, int $idRh): void
    {
        $this->soldeModel->assurer($idSalarie, $idType, $annee);
        /** @var object $solde */
        $solde = $this->soldeModel->verrouiller($idSalarie, $idType, $annee);

        if ($nbJours < 0 && (float) $solde->droit_acquis + $nbJours < 0) {
            throw new \RuntimeException('Ajustement négatif impossible : droit_acquis serait négatif');
        }

        $this->soldeModel->update($solde->id_solde_conge, [
            'droit_acquis' => round((float) $solde->droit_acquis + $nbJours, 2),
        ]);

        $this->mouvementModel->insert([
            'id_solde_conge'   => $solde->id_solde_conge,
            'id_demande_conge' => null,
            'type_mouvement'   => 'AJUSTEMENT',
            'nb_jours'         => $nbJours,
            'date_mouvement'   => date('Y-m-d H:i:s'),
            'commentaire'      => $commentaire !== '' ? $commentaire : "Ajustement RH #{$idRh}",
        ], true);

        $this->audit->journaliser($idRh, 'AJUSTEMENT', 'solde_conge', $solde->id_solde_conge, [
            'id_salarie' => $idSalarie,
            'nb_jours'   => $nbJours,
            'commentaire' => $commentaire,
        ]);
    }

    /**
     * Jours restants à indemniser au départ (soldes des types déduits, année courante).
     */
    public function soldeToutCompte(int $idSalarie): float
    {
        $annee = (int) date('Y');
        $types = $this->typeCongeModel->listerActifs();
        $total = 0.0;

        foreach ($types as $type) {
            if (! (bool) $type->deduit_solde) {
                continue;
            }
            $total += $this->soldeDisponible($idSalarie, (int) $type->id_type_conge, $annee);
        }

        return round($total, 2);
    }

    /**
     * Soldes via la vue v_solde_conge_salarie (disponible calculé) — GET /soldes.
     */
    public function listerSoldes(int $idSalarie, ?int $annee = null): array
    {
        $builder = $this->soldeModel->db->table('v_solde_conge_salarie')
            ->where('id_salarie', $idSalarie)
            ->orderBy('code_type_conge', 'ASC');

        if ($annee !== null) {
            $builder->where('annee', $annee);
        }

        return $builder->get()->getResult();
    }

    /**
     * Tous les soldes (RH) via v_solde_conge_salarie, avec filtres optionnels.
     */
    public function listerTousSoldes(?int $annee = null, ?string $recherche = null, ?int $idType = null): array
    {
        $builder = $this->soldeModel->db->table('v_solde_conge_salarie')
            ->orderBy('nom', 'ASC')
            ->orderBy('prenom', 'ASC')
            ->orderBy('code_type_conge', 'ASC');

        if ($annee !== null) {
            $builder->where('annee', $annee);
        }
        if ($idType !== null) {
            $builder->where('id_type_conge', $idType);
        }
        if ($recherche !== null && trim($recherche) !== '') {
            $builder->groupStart()
                ->like('matricule', trim($recherche))
                ->orLike('nom', trim($recherche))
                ->orLike('prenom', trim($recherche))
            ->groupEnd();
        }

        return $builder->get()->getResult();
    }

    /**
     * Alertes de solde (RH) via v_alertes_solde.
     */
    public function listerAlertes(): array
    {
        return $this->soldeModel->db->table('v_alertes_solde')
            ->orderBy('type_alerte', 'DESC')
            ->orderBy('nom', 'ASC')
            ->get()
            ->getResult();
    }

    private function deduitSolde(int $idType): bool
    {
        $type = $this->typeCongeModel->find($idType);

        return $type !== null && (bool) $type->deduit_solde;
    }
}
