<?php

declare(strict_types=1);

namespace App\Models\Selection;

use CodeIgniter\Model;

class EvaluationCandidatModel extends Model
{
    protected $table         = 'evaluation_candidat';
    protected $primaryKey    = 'id_evaluation';
    protected $returnType    = 'object';
    protected $allowedFields = [
        'id_candidature',
        'id_evaluateur',
        'criteres',
        'note',
        'commentaire',
        'date_evaluation',
    ];
    protected $useTimestamps = false;

    public function listerParCandidature(int $idCandidature): array
    {
        return $this->where('id_candidature', $idCandidature)
            ->orderBy('date_evaluation', 'ASC')
            ->findAll();
    }

    public function moyenneParCandidature(int $idCandidature): ?float
    {
        $ligne = $this->selectAvg('note', 'moyenne')
            ->where('id_candidature', $idCandidature)
            ->first();

        return $ligne === null || $ligne->moyenne === null ? null : (float) $ligne->moyenne;
    }
}
