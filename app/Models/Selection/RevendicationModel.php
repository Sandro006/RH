<?php

declare(strict_types=1);

namespace App\Models\Selection;

use CodeIgniter\Model;

class RevendicationModel extends Model
{
    protected $table         = 'revendication';
    protected $primaryKey    = 'id_revendication';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id_proposition',
        'sens',
        'id_candidat',
        'id_utilisateur',
        'nature_id',
        'valeur_demandee',
        'commentaire',
        'statut_id',
        'date_creation',
        'id_repondant',
        'reponse',
        'date_reponse',
    ];
}
