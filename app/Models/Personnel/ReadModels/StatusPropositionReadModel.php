<?php

declare(strict_types=1);

namespace App\Models\Personnel\ReadModels;

use CodeIgniter\Model;

/** Lecture du référentiel des statuts de proposition. */
class StatusPropositionReadModel extends Model
{
    protected $table         = 'status_proposition_embauche';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = false;

    public function idParDesignation(string $designation): int
    {
        $ligne = $this->where('designation', $designation)->first();

        if ($ligne === null) {
            throw new \RuntimeException("Statut de proposition inconnu : {$designation}");
        }

        return (int) $ligne->id;
    }
}
