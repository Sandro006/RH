<?php

declare(strict_types=1);

namespace App\Entities\Personnel;

use CodeIgniter\Entity\Entity;

class Document extends Entity
{
    protected $dates = ['date_depot'];

    protected $casts = [
        'id_document'   => 'integer',
        'id_salarie'    => 'integer',
        'type_document' => 'integer',
    ];
}