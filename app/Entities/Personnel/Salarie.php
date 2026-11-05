<?php

declare(strict_types=1);

namespace App\Entities\Personnel;

use CodeIgniter\Entity\Entity;

/**
 * Représente un salarié.
 * Table : salarie
 */
class Salarie extends Entity
{
    protected $dates = ['date_embauche'];

    protected $casts = [
        'id_salarie'   => 'integer',
        'id_candidat'  => '?integer',
        'matricule'    => 'string',
        'nom'          => 'string',
        'prenom'       => 'string',
        'email'        => '?string',
        'telephone'    => '?string',
        'statut'       => 'integer',
    ];
}