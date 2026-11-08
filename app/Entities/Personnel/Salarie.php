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
    protected $dates = ['date_naissance', 'date_embauche', 'date_sortie'];

    protected $casts = [
        'id_salarie'          => 'integer',
        'id_candidat'         => '?integer',
        'matricule'           => 'string',
        'nom'                 => 'string',
        'prenom'              => 'string',
        'email'               => '?string',
        'telephone'           => '?string',
        'lieu_naissance'      => '?string',
        'nationalite'         => '?string',
        'situation_familiale' => '?string',
        'adresse'             => '?string',
        'ville'               => '?string',
        'pays'                => '?string',
        'statut'              => 'integer',
    ];
}