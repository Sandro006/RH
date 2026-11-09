<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class DemandeCongeModel extends Model
{
    protected $table            = 'demande_conge';
    protected $primaryKey       = 'id_conge';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id_utilisateur',
        'id_employe',
        'type_conge',
        'date_debut',
        'date_fin',
        'motif',
        'justification',
        'statut',
    ];

    public function listerParUtilisateur(int $idUtilisateur): array
    {
        return $this->where('id_utilisateur', $idUtilisateur)
            ->orderBy('date_creation', 'DESC')
            ->findAll();
    }

    public function listerParEmploye(int $idEmploye): array
    {
        return $this->where('id_employe', $idEmploye)
            ->orderBy('date_creation', 'DESC')
            ->findAll();
    }
}
