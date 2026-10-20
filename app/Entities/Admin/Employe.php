<?php

declare(strict_types=1);

namespace App\Entities\Admin;

use CodeIgniter\Entity\Entity;

class Employe extends Entity
{
    protected $casts = [
        'id'         => 'integer',
        'poste_id'   => 'integer',
        'id_salarie' => 'integer',
    ];
}
