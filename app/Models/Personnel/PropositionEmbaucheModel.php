<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class PropositionEmbaucheModel extends Model
{
    protected $table         = 'proposition_embauche';
    protected $primaryKey    = 'id_proposition';
    protected $returnType    = 'object';
    protected $allowedFields = [
        'statut',
        'id_candidature',
        'date_proposition',
        'remuneration_proposee',
        'date_embauche_proposee',
        'date_reponse',
        'reponse',
    ];
    protected $useTimestamps = false;

    public function listerParCandidature(int $idCandidature): array
    {
        return $this->where('id_candidature', $idCandidature)
            ->orderBy('id_proposition', 'DESC')
            ->findAll();
    }

    public function derniereParCandidature(int $idCandidature): ?object
    {
        return $this->where('id_candidature', $idCandidature)
            ->orderBy('id_proposition', 'DESC')
            ->first();
    }
}
