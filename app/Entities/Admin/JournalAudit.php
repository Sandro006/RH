<?php

declare(strict_types=1);

namespace App\Entities\Admin;

use CodeIgniter\Entity\Entity;

class JournalAudit extends Entity
{
    protected $casts = [
        'id_audit'       => 'integer',
        'id_utilisateur' => 'integer',
        'action'         => 'integer',
    ];
}
