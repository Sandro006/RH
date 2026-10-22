<?php

namespace App\Models\Selection;

use CodeIgniter\Model;

class EntretienModel extends Model
{
    protected $table            = 'entretien';
    protected $primaryKey       = 'id_entretien';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;

    protected $allowedFields = [
        'id_candidature',
        'id_recruteur',
        'date_entretien',
        'type_entretien',
        'compte_rendu',
        'statut',
    ];

    protected $useTimestamps = false;
}
