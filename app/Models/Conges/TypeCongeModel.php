<?php

declare(strict_types=1);

namespace App\Models\Conges;

use CodeIgniter\Model;

class TypeCongeModel extends Model
{
    protected $table         = 'type_conge';
    protected $primaryKey    = 'id_type_conge';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'code',
        'libelle',
        'est_paye',
        'deduit_solde',
        'justificatif_requis',
        'delai_prevenance_jours',
        'duree_max_jours',
        'demi_journee_autorisee',
        'actif',
    ];
    protected $useTimestamps = false;

    public function trouverParCode(string $code): ?object
    {
        return $this->where('code', $code)->first();
    }

    public function idParCode(string $code): ?int
    {
        $row = $this->trouverParCode($code);
        return $row !== null ? (int) $row->id_type_conge : null;
    }

    public function listerActifs(): array
    {
        return $this->where('actif', true)
            ->orderBy('libelle', 'ASC')
            ->findAll();
    }

    public function listerTous(): array
    {
        return $this->orderBy('libelle', 'ASC')->findAll();
    }
}
