<?php

declare(strict_types=1);

namespace App\Entities\Personnel;

use CodeIgniter\Entity\Entity;

class TacheOnboarding extends Entity
{
    protected $dates = ['date_echeance', 'date_realisation'];

    protected $casts = [
        'id_tache'      => 'integer',
        'id_onboarding' => 'integer',
        'statut'        => 'integer',
    ];
}