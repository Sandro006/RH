<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class NatureRevendicationModel extends Model
{
    protected $table         = 'nature_revendication';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $allowedFields = ['designation'];
    protected $useTimestamps = false;

    public function idParDesignation(string $designation): int
    {
        $ligne = $this->where('designation', $designation)->first();

        if ($ligne === null) {
            throw new \RuntimeException("Nature de revendication inconnue : {$designation}");
        }

        return (int) $ligne->id;
    }

    public function designationParId(int $id): string
    {
        $ligne = $this->find($id);

        if ($ligne === null) {
            throw new \RuntimeException("Nature de revendication #{$id} introuvable");
        }

        return (string) $ligne->designation;
    }
}
