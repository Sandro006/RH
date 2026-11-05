<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class SalarieHistoriqueModel extends Model
{
    protected $table         = 'salarie_status_historique';
    protected $primaryKey    = 'id_historique';
    protected $returnType    = 'object';
    protected $allowedFields = [
        'id_salarie', 'statut_ancien', 'statut_nouveau',
        'date_changement', 'id_utilisateur', 'commentaire',
    ];
    protected $useTimestamps = false;

    public function listerParSalarie(int $idSalarie): array
    {
        return $this->where('id_salarie', $idSalarie)
                    ->orderBy('date_changement', 'ASC')
                    ->findAll();
    }
}