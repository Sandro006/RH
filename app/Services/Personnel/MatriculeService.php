<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\SalarieModel;

/**
 * Génère un matricule unique au format MAT-AAAA-00001.
 */
class MatriculeService
{
    public function __construct(
        private readonly SalarieModel $salarieModel,
    ) {}

    /**
     * @param string $dateEmbauche format YYYY-MM-DD
     */
    public function genererMatricule(string $dateEmbauche): string
    {
        $annee = (int) substr($dateEmbauche, 0, 4);
        $dernier = $this->salarieModel->dernierMatriculeDeLAnnee($annee);

        $numero = 1;
        if ($dernier !== null) {
            $numero = (int) substr($dernier, -5) + 1;
        }

        $matricule = sprintf('MAT-%04d-%05d', $annee, $numero);

        // Anti-collision (concurrence rare, mais possible)
        while ($this->salarieModel->trouverParMatricule($matricule) !== null) {
            $numero++;
            $matricule = sprintf('MAT-%04d-%05d', $annee, $numero);
        }

        return $matricule;
    }
}