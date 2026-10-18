<?php

declare(strict_types=1);

namespace App\Entities\Personnel;

use CodeIgniter\Entity\Entity;

/**
 * Affectation d'un salarié à un poste (historique).
 * Table : affectation
 */
class Affectation extends Entity
{
    protected $dates = ['date_debut', 'date_fin'];

    protected $casts = [
        'id_affectation' => 'integer',
        'id_salarie'     => 'integer',
        'id_poste'       => 'integer',
        'motif'          => '?string',
    ];
}