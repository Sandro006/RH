<?php

declare(strict_types=1);

namespace App\Models\Paie;

use CodeIgniter\Model;

/**
 * Table : taux_cotisation (paramétrage CNPS employé / patronal, en %).
 */
class TauxCotisationModel extends Model
{
    protected $table         = 'taux_cotisation';
    protected $primaryKey    = 'id_taux_cotisation';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'code',
        'designation',
        'taux_employe',
        'taux_patronal',
        'actif',
    ];
    protected $useTimestamps = false;

    public function listerActifs(): array
    {
        return $this->where('actif', true)
            ->orderBy('designation', 'ASC')
            ->findAll();
    }

    public function trouverParCode(string $code): ?object
    {
        return $this->where('code', $code)->first();
    }
}
