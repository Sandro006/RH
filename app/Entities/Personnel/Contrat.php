<?php

declare(strict_types=1);

namespace App\Entities\Personnel;

use CodeIgniter\Entity\Entity;

class Contrat extends Entity
{
    protected $dates = [
        'date_debut', 'date_fin',
        'periode_essai_debut', 'periode_essai_fin',
        'date_signature',
    ];

    protected $casts = [
        'id_contrat'      => 'integer',
        'id_salarie'      => 'integer',
        'id_poste'        => 'integer',
        'type_contrat_id' => 'integer',
        'salaire_base'    => '?float',
        'statut'          => 'integer',
    ];
}