<?php

declare(strict_types=1);

namespace App\Models\Conges;

use CodeIgniter\Model;

/**
 * Table : delegation_validation (optionnel).
 * CHECK : id_manager <> id_delegue, date_fin >= date_debut.
 */
class DelegationValidationModel extends Model
{
    protected $table         = 'delegation_validation';
    protected $primaryKey    = 'id_delegation';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'id_manager',
        'id_delegue',
        'date_debut',
        'date_fin',
        'motif',
    ];
    protected $useTimestamps = false;

    /**
     * Délégué en vigueur pour un manager à $date (Y-m-d).
     */
    public function trouverDelegue(int $idManager, string $date): ?object
    {
        return $this->where('id_manager', $idManager)
            ->where('date_debut <=', $date)
            ->where('date_fin >=', $date)
            ->orderBy('date_debut', 'DESC')
            ->first();
    }

    public function listerActives(string $date): array
    {
        return $this->where('date_debut <=', $date)
            ->where('date_fin >=', $date)
            ->orderBy('id_manager', 'ASC')
            ->findAll();
    }

    public function listerParManager(int $idManager): array
    {
        return $this->where('id_manager', $idManager)
            ->orderBy('date_debut', 'DESC')
            ->findAll();
    }
}
