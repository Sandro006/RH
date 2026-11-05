<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class StatusSalarieModel extends Model
{
    protected $table         = 'status_salarie';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $allowedFields = ['designation'];
    protected $useTimestamps = false;

    /**
     * Résout l'id d'un statut par sa désignation (ex. "Actif").
     */
    public function idParDesignation(string $designation): int
    {
        $row = $this->where('designation', $designation)->first();
        if ($row === null) {
            throw new \RuntimeException("Statut salarié inconnu : {$designation}");
        }
        return (int) $row->id;
    }
}