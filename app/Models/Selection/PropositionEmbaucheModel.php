<?php

declare(strict_types=1);

namespace App\Models\Selection;

use CodeIgniter\Model;

class PropositionEmbaucheModel extends Model
{
    protected $table         = 'proposition_embauche';
    protected $primaryKey    = 'id_proposition';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'statut',
        'id_candidature',
        'date_proposition',
        'remuneration_proposee',
        'date_embauche_proposee',
        'date_reponse',
        'reponse',
    ];
}
