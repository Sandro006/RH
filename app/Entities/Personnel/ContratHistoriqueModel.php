<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class ContratHistoriqueModel extends Model
{
    protected $table         = 'contrat_status_historique';
    protected $primaryKey    = 'id_historique';
    protected $returnType    = 'object';
    protected $allowedFields = [
        'id_contrat', 'statut_ancien', 'statut_nouveau',
        'date_changement', 'id_utilisateur', 'commentaire',
    ];
    protected $useTimestamps = false;

    public function listerParContrat(int $idContrat): array
    {
        return $this->where('id_contrat', $idContrat)
                    ->orderBy('date_changement', 'ASC')
                    ->findAll();
    }
}