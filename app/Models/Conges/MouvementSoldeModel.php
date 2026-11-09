<?php

declare(strict_types=1);

namespace App\Models\Conges;

use CodeIgniter\Model;

/**
 * Table : mouvement_solde (journal des soldes, écrit par SoldeService).
 * type_mouvement ∈ ACQUISITION, RESERVATION, DEBIT, RECREDIT, REPORT, AJUSTEMENT, EXPIRATION.
 * nb_jours signé : positif = crédit, négatif = débit (selon type).
 */
class MouvementSoldeModel extends Model
{
    protected $table         = 'mouvement_solde';
    protected $primaryKey    = 'id_mouvement';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'id_solde_conge',
        'id_demande_conge',
        'type_mouvement',
        'nb_jours',
        'date_mouvement',
        'commentaire',
    ];
    protected $useTimestamps = false;

    public function listerParSolde(int $idSoldeConge): array
    {
        return $this->where('id_solde_conge', $idSoldeConge)
            ->orderBy('date_mouvement', 'ASC')
            ->orderBy('id_mouvement', 'ASC')
            ->findAll();
    }

    public function listerParDemande(int $idDemandeConge): array
    {
        return $this->where('id_demande_conge', $idDemandeConge)
            ->orderBy('date_mouvement', 'ASC')
            ->orderBy('id_mouvement', 'ASC')
            ->findAll();
    }
}
