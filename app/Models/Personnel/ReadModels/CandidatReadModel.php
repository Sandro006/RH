<?php

declare(strict_types=1);

namespace App\Models\Personnel\ReadModels;

use CodeIgniter\Model;

/** Lecture des candidats depuis le module Recrutement. */
class CandidatReadModel extends Model
{
    protected $table         = 'candidat';
    protected $primaryKey    = 'id_candidat';
    protected $returnType    = 'object';
    protected $useTimestamps = false;
}
