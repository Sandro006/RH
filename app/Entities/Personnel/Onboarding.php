<?php

declare(strict_types=1);

namespace App\Entities\Personnel;

use CodeIgniter\Entity\Entity;

class Onboarding extends Entity
{
    protected $dates = ['date_debut', 'date_fin'];

    protected $casts = [
        'id_onboarding' => 'integer',
        'id_salarie'    => 'integer',
        'statut'        => 'integer',
    ];
}