<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class TypeDocumentModel extends Model
{
    protected $table         = 'type_document';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $allowedFields = ['designation'];
    protected $useTimestamps = false;

    // idParDesignation
    public function idParDesignation(string $designation): int
    {
        $row = $this->where('designation', $designation)->first();
        if ($row === null) {
            throw new \RuntimeException("Type de document inconnu : {$designation}");
        }
        return (int) $row->id;
    }

    // listerTous
    public function listerTous(): array
    {
        return $this->orderBy('designation', 'ASC')->findAll();
    }
}