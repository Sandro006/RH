<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\PeriodeEssaiModel;
use App\Exceptions\DonneesIncoherentesException;

/**
 * Règles de la période d'essai (RH.md §III + Contrat.md §5).
 *
 * Plafonds légaux :
 *  - CDD ≤ 6 mois : 2 semaines (14 jours)
 *  - CDD > 6 mois : 1 mois (30 jours)
 *  - CDI ouvrier/employé : 2 mois (60 jours)
 *  - CDI agent de maîtrise : 3 mois (90 jours)
 *  - CDI cadre : 4 mois (120 jours)
 *  - CDI sans mention écrite : aucune période d'essai (géré par ContratService)
 *
 * Déduction des stages antérieurs (RH.md §III) :
 *  - Les jours de stage dans la même entreprise sont déduits de la durée d'essai.
 */
class PeriodeEssaiService
{
    // IDs des types de contrat (table type_contrat)
    private const TYPE_CDI     = 1;
    private const TYPE_CDD     = 2;
    private const TYPE_STAGE   = 3;
    private const TYPE_INTERIM = 4;

    // Catégories
    public const CATEGORIE_OUVRIER_EMPLOYE = 'OUVRIER_EMPLOYE';
    public const CATEGORIE_AGENT_MAITRISE  = 'AGENT_MAITRISE';
    public const CATEGORIE_CADRE           = 'CADRE';

    public function __construct(
        private readonly PeriodeEssaiModel $periodeEssaiModel,
    ) {}

    /**
     * Déduit la catégorie d'essai depuis le rang du poste.
     */
    public function categorieDepuisPoste(int $idPoste): string
    {
        $rang = strtolower(trim($this->periodeEssaiModel->rangDuPoste($idPoste) ?? ''));

        // Cadres : Manager, Responsable, Directeur, VP, Président
        if (in_array($rang, [
            'manager', 'responsable', 'directeur',
            'vice-président', 'président',
        ], true)) {
            return self::CATEGORIE_CADRE;
        }

        // Agents de maîtrise : Chef d'équipe, Senior
        if (in_array($rang, ["chef d'équipe", 'senior'], true)) {
            return self::CATEGORIE_AGENT_MAITRISE;
        }

        // Ouvriers/employés : Stagiaire, Junior, Intermédiaire
        return self::CATEGORIE_OUVRIER_EMPLOYE;
    }

    /**
     * Calcule la période d'essai maximale légale (sans déduction de stage).
     * @return array{debut: string, fin: string, dureeJours: int}
     */
    public function calculer(
        int $typeContratId,
        string $dateDebut,
        ?string $dateFin,
        string $categorie
    ): array {
        $duree = $this->dureeMaxJours($typeContratId, $dateFin, $categorie);
        $fin   = date('Y-m-d', strtotime($dateDebut . ' +' . $duree . ' days'));

        return [
            'debut'      => $dateDebut,
            'fin'        => $fin,
            'dureeJours' => $duree,
        ];
    }

    /**
     * Plafond légal en jours.
     */
    public function dureeMaxJours(int $typeContratId, ?string $dateFin, string $categorie): int
    {
        // CDD / Stage / Interim
        if (in_array($typeContratId, [self::TYPE_CDD, self::TYPE_STAGE, self::TYPE_INTERIM], true)) {
            $dureeContratJours = $this->dureeContratEnJours($dateFin);
            if ($dureeContratJours === null || $dureeContratJours <= 183) {
                return 14; // 2 semaines
            }
            return 30; // 1 mois
        }

        // CDI
        if ($typeContratId === self::TYPE_CDI) {
            return match ($categorie) {
                self::CATEGORIE_CADRE          => 120, // 4 mois
                self::CATEGORIE_AGENT_MAITRISE => 90,  // 3 mois
                default                        => 60,  // 2 mois
            };
        }

        // Autres (Consultant, Essai, Alternance) : pas de plafond strict
        return 30;
    }

    /**
     * Vérifie que la durée demandée respecte le plafond.
     */
    public function validerDuree(int $typeContratId, int $dureeJours, string $categorie): bool
    {
        $max = $this->dureeMaxJours($typeContratId, null, $categorie);
        return $dureeJours > 0 && $dureeJours <= $max;
    }

    // deduireStages
	public function deduireStages(int $idSalarie, int $dureeEssaiJours, ?string $dateDebutContratActuel = null): int
	{
		$joursStage = $this->periodeEssaiModel->joursStageAnterieurs($idSalarie, $dateDebutContratActuel);

		return min($dureeEssaiJours, max(0, $joursStage));
	}

    /**
     * Calcule la durée d'essai effective après déduction des stages.
     */
	public function dureeEffective(
		int $idSalarie,
		int $typeContratId,
		?string $dateFin,
		string $categorie,
		?int $dureeDemandeeJours = null,
		?string $dateDebutContratActuel = null
	): int {
		$dureeInitiale = $dureeDemandeeJours
			?? $this->dureeMaxJours($typeContratId, $dateFin, $categorie);

		$deduction = $this->deduireStages($idSalarie, $dureeInitiale, $dateDebutContratActuel);

		return max(0, $dureeInitiale - $deduction);
	}

    private function dureeContratEnJours(?string $dateFin): ?int
    {
        if ($dateFin === null) {
            return null;
        }
        $debut = new \DateTimeImmutable(date('Y-m-d'));
        $fin   = new \DateTimeImmutable($dateFin);
        return (int) $debut->diff($fin)->days;
    }
}