<?php

declare(strict_types=1);

namespace App\Models\Common;

use CodeIgniter\Model;

class ActionModel extends Model
{
    protected $table            = 'action';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = ['action'];

    public function idParDesignation(string $designation): ?int
    {
        $ligne = $this->db->table($this->table)
            ->select('id')
            ->where('action', $designation)
            ->get()
            ->getRow();

        return $ligne !== null ? (int) $ligne->id : null;
    }

    public function assurer(string $designation): int
    {
        $id = $this->idParDesignation($designation);

        if ($id !== null) {
            return $id;
        }

        try {
            $this->insert(['action' => $designation], true);
        } catch (\Throwable) {
            $id = $this->idParDesignation($designation);

            if ($id !== null) {
                return $id;
            }

            throw new \RuntimeException("Action inconnue : {$designation}");
        }

        $id = $this->idParDesignation($designation);

        if ($id === null) {
            throw new \RuntimeException("Action inconnue : {$designation}");
        }

        return $id;
    }
}
