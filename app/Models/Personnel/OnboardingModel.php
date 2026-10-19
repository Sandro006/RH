<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;
use App\Entities\Personnel\Onboarding;

class OnboardingModel extends Model
{
    protected $table         = 'onboarding';
    protected $primaryKey    = 'id_onboarding';
    protected $returnType    = Onboarding::class;
    protected $allowedFields = ['id_salarie', 'statut', 'date_debut', 'date_fin'];
    protected $useTimestamps = false;

    // trouverParSalarie
    public function trouverParSalarie(int $idSalarie): ?Onboarding
    {
        return $this->where('id_salarie', $idSalarie)
                    ->orderBy('date_debut', 'DESC')
                    ->first();
    }

    // onboardingActif
    public function onboardingActif(int $idSalarie): ?Onboarding
    {
        return $this->where('id_salarie', $idSalarie)
                    ->whereIn('statut', [1, 2, 3]) // À démarrer, En cours, En attente
                    ->first();
    }
}