<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;
use App\Entities\Personnel\TacheOnboarding;

class TacheModel extends Model
{
    protected $table         = 'onboarding_tache';
    protected $primaryKey    = 'id_tache';
    protected $returnType    = TacheOnboarding::class;
    protected $allowedFields = [
        'id_onboarding', 'intitule', 'description',
        'responsable', 'date_echeance', 'date_realisation', 'statut',
    ];
    protected $useTimestamps = false;

    // listerParOnboarding
    public function listerParOnboarding(int $idOnboarding): array
    {
        return $this->where('id_onboarding', $idOnboarding)
                    ->orderBy('id_tache', 'ASC')
                    ->findAll();
    }

    // compterParStatut
    public function compterParStatut(int $idOnboarding, int $idStatut): int
    {
        return $this->where('id_onboarding', $idOnboarding)
                    ->where('statut', $idStatut)
                    ->countAllResults();
    }

    // compterTotal
    public function compterTotal(int $idOnboarding): int
    {
        return $this->where('id_onboarding', $idOnboarding)->countAllResults();
    }
}