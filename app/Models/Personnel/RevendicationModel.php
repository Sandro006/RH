<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class RevendicationModel extends Model
{
    protected $table         = 'revendication';
    protected $primaryKey    = 'id_revendication';
    protected $returnType    = 'object';
    protected $allowedFields = [
        'id_proposition',
        'sens',
        'id_candidat',
        'id_utilisateur',
        'nature_id',
        'valeur_demandee',
        'commentaire',
        'statut_id',
        'id_repondant',
        'reponse',
        'date_reponse',
    ];
    protected $useTimestamps = false;

    public function listerParProposition(int $idProposition): array
    {
        return $this->where('id_proposition', $idProposition)
            ->orderBy('date_creation', 'ASC')
            ->findAll();
    }
}
