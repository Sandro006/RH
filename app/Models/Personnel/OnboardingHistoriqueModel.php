<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class OnboardingHistoriqueModel extends Model
{
    protected $table         = 'onboarding_status_historique';
    protected $primaryKey    = 'id_historique';
    protected $returnType    = 'object';
    protected $allowedFields = [
        'id_onboarding', 'statut_ancien', 'statut_nouveau',
        'date_changement', 'id_utilisateur', 'commentaire',
    ];
    protected $useTimestamps = false;

    // listerParOnboarding
    public function listerParOnboarding(int $idOnboarding): array
    {
        return $this->where('id_onboarding', $idOnboarding)
                    ->orderBy('date_changement', 'ASC')
                    ->findAll();
    }
}