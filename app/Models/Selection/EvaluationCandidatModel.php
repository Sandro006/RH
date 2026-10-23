<?php

declare(strict_types=1);

namespace App\Models\Selection;

use CodeIgniter\Model;

class EvaluationCandidatModel extends Model
{
    protected $table         = 'evaluation_candidat';
    protected $primaryKey    = 'id_evaluation';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id_candidature',
        'id_evaluateur',
        'criteres',
        'note',
        'commentaire',
        'date_evaluation',
    ];
}
