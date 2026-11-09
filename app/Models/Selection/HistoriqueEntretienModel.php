<?php

namespace App\Models\Selection;

use CodeIgniter\Model;

class HistoriqueEntretienModel extends Model
{
    protected $table            = 'entretien_status_historique';
    protected $primaryKey       = 'id_historique';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;

    protected $allowedFields = [
        'id_entretien',
        'statut_ancien',
        'statut_nouveau',
        'date_changement',
        'id_utilisateur',
        'commentaire',
    ];

    protected $useTimestamps = false;
}
