<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class StatusTacheModel extends Model
{
    protected $table         = 'status_onboarding_tache';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $allowedFields = ['designation'];
    protected $useTimestamps = false;

    // idParDesignation
    public function idParDesignation(string $designation): int
    {
        $row = $this->where('designation', $designation)->first();
        if ($row === null) {
            throw new \RuntimeException("Statut tâche inconnu : {$designation}");
        }
        return (int) $row->id;
    }
}