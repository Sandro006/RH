<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\GrilleModel;
use App\Exceptions\DonneesIncoherentesException;

/**
 * Contrôle la rémunération par rapport à la grille salariale du poste.
 * Consigne Contrat.md §6 : "salaire_base doit rester dans la grille_salariale".
 */
class GrilleService
{
    public function __construct(
        private readonly GrilleModel $grilleModel,
    ) {}

    /**
     * @return array{min: float, max: float}
     * @throws DonneesIncoherentesException si aucune grille active
     */
    public function fourchettePoste(int $idPoste): array
    {
        $fourchette = $this->grilleModel->fourchettePoste($idPoste);
        if ($fourchette === null) {
            throw new DonneesIncoherentesException(
                "Aucune grille salariale active pour le poste #{$idPoste}"
            );
        }
        return $fourchette;
    }

    public function remunerationValide(int $idPoste, float $montant): bool
    {
        $f = $this->fourchettePoste($idPoste);
        return $montant >= $f['min'] && $montant <= $f['max'];
    }
}