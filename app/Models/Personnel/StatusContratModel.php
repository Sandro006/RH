<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class StatusContratModel extends Model
{
    protected $table         = 'status_contrat';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $allowedFields = ['designation'];
    protected $useTimestamps = false;

    public function idParDesignation(string $designation): int
    {
        $row = $this->where('designation', $designation)->first();
        if ($row === null) {
            throw new \RuntimeException("Statut contrat inconnu : {$designation}");
        }
        return (int) $row->id;
    }
}