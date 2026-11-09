<?php

declare(strict_types=1);

namespace App\Models\Personnel\ReadModels;

use CodeIgniter\Model;

/** Lecture des propositions d'embauche depuis le module Embauche. */
class PropositionEmbaucheReadModel extends Model
{
    protected $table         = 'proposition_embauche';
    protected $primaryKey    = 'id_proposition';
    protected $returnType    = 'object';
    protected $useTimestamps = false;

    public function trouverParCandidature(int $idCandidature): ?object
    {
        return $this->where('id_candidature', $idCandidature)->first();
    }
}
