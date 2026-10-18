<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\ContratModel;
use App\Exceptions\DonneesIncoherentesException;

/**
 * Règles de la période d'essai (RH.md §III + Contrat.md §5).
 */
class PeriodeEssaiService
{
    private const TYPE_CDI     = 1;
    private const TYPE_CDD     = 2;
    private const TYPE_STAGE   = 3;
    private const TYPE_INTERIM = 4;

    public const CATEGORIE_OUVRIER_EMPLOYE = 'OUVRIER_EMPLOYE';
    public const CATEGORIE_AGENT_MAITRISE  = 'AGENT_MAITRISE';
    public const CATEGORIE_CADRE           = 'CADRE';

    public function __construct(
        private readonly ContratModel $contratModel,
    ) {}

    public function categorieDepuisPoste(int $idPoste): string
    {
        $rang = strtolower(trim($this->contratModel->rangDuPoste($idPoste) ?? ''));

        if (in_array($rang, ['manager', 'responsable', 'directeur', 'vice-président', 'président'], true)) {
            return self::CATEGORIE_CADRE;
        }

        if (in_array($rang, ["chef d'équipe", 'senior'], true)) {
            return self::CATEGORIE_AGENT_MAITRISE;
        }

        return self::CATEGORIE_OUVRIER_EMPLOYE;
    }

    /**
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

        return ['debut' => $dateDebut, 'fin' => $fin, 'dureeJours' => $duree];
    }

    public function dureeMaxJours(int $typeContratId, ?string $dateFin, string $categorie): int
    {
        if (in_array($typeContratId, [self::TYPE_CDD, self::TYPE_STAGE, self::TYPE_INTERIM], true)) {
            $dureeContratJours = $this->dureeContratEnJours($dateFin);
            if ($dureeContratJours === null || $dureeContratJours <= 183) {
                return 14;
            }
            return 30;
        }

        if ($typeContratId === self::TYPE_CDI) {
            return match ($categorie) {
                self::CATEGORIE_CADRE          => 120,
                self::CATEGORIE_AGENT_MAITRISE => 90,
                default                        => 60,
            };
        }

        return 30;
    }

    public function validerDuree(int $typeContratId, int $dureeJours, string $categorie): bool
    {
        $max = $this->dureeMaxJours($typeContratId, null, $categorie);
        return $dureeJours > 0 && $dureeJours <= $max;
    }

    public function deduireStages(int $idSalarie, int $dureeEssaiJours): int
    {
        // TODO: nécessite une table `stage` ou une colonne sur `salarie`.
        return 0;
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