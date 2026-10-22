<?php

declare(strict_types=1);

namespace App\Services\Common;

use App\Models\Common\ReferentielModel;

class ReferentielService
{
    public function __construct(
        private readonly ReferentielModel $referentielModel,
    ) {
    }

    public function lister(string $ressource, array $filtres = []): array
    {
        return $this->referentielModel->lister($ressource, $filtres);
    }

    public function statuts(string $domaine): array
    {
        return $this->referentielModel->statuts($domaine);
    }

    public function grilleActive(int $idPoste): ?array
    {
        return $this->referentielModel->grilleActive($idPoste);
    }
}
