<?php

declare(strict_types=1);

namespace App\Models\Conges;

use CodeIgniter\Model;

/**
 * Référentiel des statuts de demande de congé.
 * Valeurs : EN_ATTENTE_MANAGER, EN_ATTENTE_RH, VALIDEE, REFUSEE, ANNULEE.
 */
class StatutDemandeCongeModel extends Model
{
    protected $table         = 'statut_demande_conge';
    protected $primaryKey    = 'id_statut';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'code',
        'libelle',
    ];
    protected $useTimestamps = false;

    public function trouverParCode(string $code): ?object
    {
        return $this->where('code', $code)->first();
    }

    public function idParCode(string $code): ?int
    {
        $row = $this->trouverParCode($code);
        return $row !== null ? (int) $row->id_statut : null;
    }

    public function listerTous(): array
    {
        return $this->orderBy('id_statut', 'ASC')->findAll();
    }
}
