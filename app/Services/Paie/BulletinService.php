<?php

declare(strict_types=1);

namespace App\Services\Paie;

use App\Exceptions\CongeException;
use App\Libraries\Common\SessionUtilisateur;
use App\Models\Paie\BulletinPaieModel;
use App\Models\Paie\ElementPaieModel;
use App\Models\Personnel\ContratModel;
use App\Services\Conges\CalendrierService;

/**
 * BulletinService : calcule et fige le bulletin de paie par salarié et par mois.
 *
 * Formule (montants en unités monétaires) :
 *   base_proratée = contrat.salaire_base × couverture / jours du mois
 *   taux_journalier = base_proratée / jours_ouvrés_mois
 *   retenue_conges  = taux_journalier × (jours_sans_solde + jours_injustifies)
 *   brut            = base_proratée + Σ éléments_paie − retenue_conges
 *   cotisations     = brut × taux_employe/total/100
 *   net             = brut − cotisations
 *   patronal        = brut × taux_patronal/100   (coût employeur, non déduit)
 *
 * Limites documentées : demi-journées comptent 1 jour dans
 * v_variables_paie_conges (vue Bloc 7) ; prorata contrat élémentaire
 * (présence en début/fin de mois, sans suspension) ; taux unique somme
 * des cotisations actives (CNPS seul à ce jour).
 */
class BulletinService
{
    public function __construct(
        private readonly BulletinPaieModel $bulletinModel,
        private readonly ElementPaieModel $elementModel,
        private readonly ContratModel $contratModel,
        private readonly CotisationService $cotisationService,
        private readonly CalendrierService $calendrier,
    ) {
    }

    public function trouver(int $idSalarie, string $mois): ?object
    {
        return $this->bulletinModel->trouver($idSalarie, $this->normaliserMois($mois));
    }

    public function trouverParId(int $id): ?object
    {
        return $this->bulletinModel->find($id);
    }

    /**
     * Détail d'un bulletin pour l'aperçu : bulletin figé + identité du
     * salarié + éléments variables actuels + synthèse gains/retenues
     * (mêmes formules que calculer(), montants arrondis identiques).
     *
     * Note : les éléments listés sont ceux en base au moment de l'appel ;
     * si un élément a été saisi/modifié après le calcul, leur somme peut
     * différer de bulletin.elements_paie (snapshot) — le front le signale.
     *
     * @return array{bulletin: object, salarie: ?object, elements: array, synthese: array}
     */
    public function detail(int $idBulletin): array
    {
        $bulletin = $this->bulletinModel->find($idBulletin);
        if ($bulletin === null) {
            throw new CongeException("Bulletin #{$idBulletin} introuvable", CongeException::OBJET_INTROUVABLE);
        }

        $salarie = $this->bulletinModel->db->table('salarie')
            ->select('matricule, nom, prenom')
            ->where('id_salarie', (int) $bulletin->id_salarie)
            ->get()
            ->getRow();

        $elements = $this->elementModel->listerParSalarieAndMois((int) $bulletin->id_salarie, (string) $bulletin->mois);

        $tauxJournalier = (int) $bulletin->jours_ouvres_mois > 0
            ? (float) $bulletin->salaire_base / (int) $bulletin->jours_ouvres_mois
            : 0.0;

        $totalGains = 0.0;
        $totalRetenuesManuelles = 0.0;
        foreach ($elements as $element) {
            $montant = (float) $element->montant;
            if ($montant >= 0) {
                $totalGains += $montant;
            } else {
                $totalRetenuesManuelles += $montant;
            }
        }

        return [
            'bulletin' => $bulletin,
            'salarie' => $salarie,
            'elements' => $elements,
            'synthese' => [
                'taux_journalier' => round($tauxJournalier, 2),
                'retenue_sans_solde' => round($tauxJournalier * (float) $bulletin->jours_sans_solde, 2),
                'retenue_injustifies' => round($tauxJournalier * (float) $bulletin->jours_injustifies, 2),
                'total_gains_elements' => round($totalGains, 2),
                'total_retenues_manuelles' => round($totalRetenuesManuelles, 2),
            ],
        ];
    }

    public function lister(string $mois): array
    {
        return $this->bulletinModel->listerParMois($this->normaliserMois($mois));
    }

    /**
     * Génère ou recalcule le bulletin (refusé si déjà validé).
     *
     * @return object bulletin créé/mis à jour
     */
    public function generer(int $idSalarie, string $mois): object
    {
        $mois = $this->normaliserMois($mois);
        $existant = $this->bulletinModel->trouver($idSalarie, $mois);

        if ($existant !== null && $existant->date_validation !== null) {
            throw new CongeException(
                "Bulletin {$mois} déjà validé (le {$existant->date_validation})",
                CongeException::STATUT_INVALIDE,
            );
        }

        $donnees = $this->calculer($idSalarie, $mois);

        if ($existant === null) {
            $id = (int) $this->bulletinModel->insert($donnees, true);
            $bulletin = $this->bulletinModel->find($id);
            if ($bulletin === null) {
                throw new \RuntimeException('Bulletin introuvable après création');
            }

            return $bulletin;
        }

        $this->bulletinModel->update($existant->id_bulletin, $donnees + [
            'id_valideur' => null,
            'date_validation' => null,
        ]);

        $bulletin = $this->bulletinModel->find($existant->id_bulletin);
        if ($bulletin === null) {
            throw new \RuntimeException('Bulletin introuvable après recalcul');
        }

        return $bulletin;
    }

    public function valider(int $idBulletin, SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new CongeException('Rôle insuffisant pour valider un bulletin', CongeException::NON_AUTORISE);
        }

        $bulletin = $this->bulletinModel->find($idBulletin);
        if ($bulletin === null) {
            throw new CongeException("Bulletin #{$idBulletin} introuvable", CongeException::OBJET_INTROUVABLE);
        }
        if ($bulletin->date_validation !== null) {
            throw new CongeException('Bulletin déjà validé', CongeException::STATUT_INVALIDE);
        }

        $this->bulletinModel->update($idBulletin, [
            'id_valideur' => $u->idUtilisateur,
            'date_validation' => date('Y-m-d H:i:s'),
        ]);
    }

    private function calculer(int $idSalarie, string $mois): array
    {
        [$debutMois, $finMois] = $this->joursDuMois($mois);
        $joursOuvres = $this->joursOuvresDuMois($debutMois, $finMois);

        $contrat = $this->contratDuMois($idSalarie, $debutMois, $finMois);
        if ($contrat === null || $contrat->salaire_base === null) {
            throw new CongeException(
                "Aucun contrat avec salaire pour le salarié #{$idSalarie} ce mois-là",
                CongeException::CONTRAT_INACTIF,
            );
        }

        $base = $this->baseProratee((float) $contrat->salaire_base, $contrat, $debutMois, $finMois);

        [$joursPaye, $joursSansSolde, $joursInjustifies] = $this->variablesConges($idSalarie, $mois);
        $tauxJournalier = $joursOuvres > 0 ? $base / $joursOuvres : 0.0;
        $retenueConges = round($tauxJournalier * ($joursSansSolde + $joursInjustifies), 2);

        $elements = $this->elementModel->sommeParSalarieAndMois($idSalarie, $mois);
        $brut = max(0.0, round($base + $elements - $retenueConges, 2));

        $taux = $this->cotisationService->tauxApplique();
        $cotisations = round($brut * $taux['tauxEmploye'] / 100, 2);
        $net = max(0.0, round($brut - $cotisations, 2));
        $patronal = round($brut * $taux['tauxPatronal'] / 100, 2);

        return [
            'id_salarie'             => $idSalarie,
            'mois'                   => $mois,
            'salaire_base'           => $base,
            'jours_ouvres_mois'      => $joursOuvres,
            'jours_paye'             => $joursPaye,
            'jours_sans_solde'       => $joursSansSolde,
            'jours_injustifies'      => $joursInjustifies,
            'retenue_conges'         => $retenueConges,
            'elements_paie'          => round($elements, 2),
            'brut'                   => $brut,
            'cotisations_employe'    => $cotisations,
            'net'                    => $net,
            'cotisations_patronal'   => $patronal,
            'taux_employe_applique'  => $taux['tauxEmploye'],
            'taux_patronal_applique' => $taux['tauxPatronal'],
            'date_calcul'            => date('Y-m-d H:i:s'),
        ];
    }

    private function contratDuMois(int $idSalarie, string $debutMois, string $finMois): ?object
    {
        return $this->contratModel
            ->where('id_salarie', $idSalarie)
            ->where('date_debut <=', $finMois)
            ->groupStart()
                ->where('date_fin', null)
                ->orWhere('date_fin >=', $debutMois)
            ->groupEnd()
            ->orderBy('date_debut', 'DESC')
            ->first();
    }

    private function baseProratee(float $salaireBase, object $contrat, string $debutMois, string $finMois): float
    {
        $debut = new \DateTimeImmutable($debutMois);
        $fin = new \DateTimeImmutable($finMois);
        $total = (int) $debut->diff($fin)->format('%a') + 1;

        $couvertureDebut = new \DateTimeImmutable((string) $contrat->date_debut);
        $couvertureFin = $contrat->date_fin !== null ? new \DateTimeImmutable((string) $contrat->date_fin) : $fin;

        $debutCouvert = $couvertureDebut > $debut ? $couvertureDebut : $debut;
        $finCouvert = $couvertureFin < $fin ? $couvertureFin : $fin;
        $couverture = (int) $debutCouvert->diff($finCouvert)->format('%a') + 1;

        $couverture = max(0, min($couverture, $total));

        return round($salaireBase * $couverture / $total, 2);
    }

    private function variablesConges(int $idSalarie, string $mois): array
    {
        $ligne = $this->bulletinModel->db->table('v_variables_paie_conges')
            ->where('id_salarie', $idSalarie)
            ->where('mois', $mois)
            ->get()
            ->getRow();

        if ($ligne === null) {
            return [0.0, 0.0, 0.0];
        }

        return [(float) $ligne->jours_payes, (float) $ligne->jours_sans_solde, (float) $ligne->jours_injustifies];
    }

    private function joursDuMois(string $mois): array
    {
        // $mois arrive déjà au premier jour ('YYYY-MM-01')
        $debut = new \DateTimeImmutable($mois);
        $fin = $debut->modify('last day of this month');

        return [$debut->format('Y-m-d'), $fin->format('Y-m-d')];
    }

    private function joursOuvresDuMois(string $debut, string $fin): int
    {
        $jours = 0;
        $courant = new \DateTimeImmutable($debut);
        $dernier = new \DateTimeImmutable($fin);

        while ($courant <= $dernier) {
            if ($this->calendrier->estJourOuvre($courant)) {
                $jours++;
            }
            $courant = $courant->modify('+1 day');
        }

        return $jours;
    }

    private function normaliserMois(string $mois): string
    {
        if (preg_match('/^(\d{4})-(\d{2})(?:-(\d{2}))?$/', trim($mois), $m) !== 1) {
            throw new \InvalidArgumentException('mois invalide (attendu YYYY-MM ou YYYY-MM-DD)');
        }

        return sprintf('%04d-%02d-01', (int) $m[1], (int) $m[2]);
    }
}
