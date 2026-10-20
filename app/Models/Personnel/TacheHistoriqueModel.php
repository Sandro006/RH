<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class TacheHistoriqueModel extends Model
{
    protected $table         = 'onboarding_tache_status_historique';
    protected $primaryKey    = 'id_historique';
    protected $returnType    = 'object';
    protected $allowedFields = [
        'id_tache', 'statut_ancien', 'statut_nouveau',
        'date_changement', 'id_utilisateur', 'commentaire',
    ];
    protected $useTimestamps = false;

    // listerParTache
    public function listerParTache(int $idTache): array
    {
        return $this->where('id_tache', $idTache)
                    ->orderBy('date_changement', 'ASC')
                    ->findAll();
    }
}