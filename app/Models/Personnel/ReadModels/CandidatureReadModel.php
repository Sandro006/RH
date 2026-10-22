<?php

declare(strict_types=1);

namespace App\Models\Personnel\ReadModels;

use CodeIgniter\Model;

/** Lecture des candidatures depuis le module Recrutement. */
class CandidatureReadModel extends Model
{
    protected $table         = 'candidature';
    protected $primaryKey    = 'id_candidature';
    protected $returnType    = 'object';
    protected $useTimestamps = false;
}
