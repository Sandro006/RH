<?php

declare(strict_types=1);

namespace App\Models\Conges;

use CodeIgniter\Model;

class TypeAbsenceModel extends Model
{
    protected $table         = 'type_absence';
    protected $primaryKey    = 'id_type_absence';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'code',
        'libelle',
        'retenue_paie',
    ];
    protected $useTimestamps = false;

    public function trouverParCode(string $code): ?object
    {
        return $this->where('code', $code)->first();
    }

    public function idParCode(string $code): ?int
    {
        $row = $this->trouverParCode($code);
        return $row !== null ? (int) $row->id_type_absence : null;
    }

    public function listerTous(): array
    {
        return $this->orderBy('libelle', 'ASC')->findAll();
    }
}
