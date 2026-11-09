<?php

declare(strict_types=1);

namespace App\Services\Paie;

use App\Models\Paie\TauxCotisationModel;

/**
 * CotisationService : lit le paramétrage des taux employé / patronal (en %).
 * On fait la somme sur toutes les lignes actives (ex. CNPS seul aujourd’hui).
 */
class CotisationService
{
    public function __construct(
        private readonly TauxCotisationModel $model,
    ) {
    }

    /**
     * @return array{tauxEmploye: float, tauxPatronal: float}
     */
    public function tauxApplique(): array
    {
        $tauxEmploye = 0.0;
        $tauxPatronal = 0.0;

        foreach ($this->model->listerActifs() as $taux) {
            $tauxEmploye += (float) $taux->taux_employe;
            $tauxPatronal += (float) $taux->taux_patronal;
        }

        return [
            'tauxEmploye'  => round($tauxEmploye, 3),
            'tauxPatronal' => round($tauxPatronal, 3),
        ];
    }

    public function lister(): array
    {
        return $this->model->listerActifs();
    }
}
