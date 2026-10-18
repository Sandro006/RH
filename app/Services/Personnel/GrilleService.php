<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\ContratModel;
use App\Exceptions\DonneesIncoherentesException;

/**
 * Contrôle la rémunération par rapport à la grille salariale du poste.
 */
class GrilleService
{
    public function __construct(
        private readonly ContratModel $contratModel,
    ) {}

    /**
     * @return array{min: float, max: float}
     */
    public function fourchettePoste(int $idPoste): array
    {
        $f = $this->contratModel->fourchetteSalariale($idPoste);
        if ($f === null) {
            throw new DonneesIncoherentesException(
                "Aucune grille salariale active pour le poste #{$idPoste}"
            );
        }
        return $f;
    }

    public function remunerationValide(int $idPoste, float $montant): bool
    {
        $f = $this->fourchettePoste($idPoste);
        return $montant >= $f['min'] && $montant <= $f['max'];
    }
}