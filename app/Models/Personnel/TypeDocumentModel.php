<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class TypeDocumentModel extends Model
{
    protected $table         = 'type_document';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $allowedFields = [
        'designation', 'obligatoire', 'duree_validite_mois', 'niveau_confidentialite', 'actif',
    ];
    protected $useTimestamps = false;

    // idParDesignation
    public function idParDesignation(string $designation): int
    {
        $row = $this->trouverParDesignation($designation);
        if ($row === null) {
            throw new \RuntimeException("Type de document inconnu : {$designation}");
        }
        return (int) $row->id;
    }

    // trouverParDesignation
    public function trouverParDesignation(string $designation): ?object
    {
        return $this->where('designation', $designation)->first();
    }

    // listerTous
    public function listerTous(): array
    {
        return $this->where('actif', true)
                    ->orderBy('designation', 'ASC')
                    ->findAll();
    }

    // obligatoires
    public function obligatoires(): array
    {
        return $this->where('obligatoire', true)
                    ->where('actif', true)
                    ->orderBy('designation', 'ASC')
                    ->findAll();
    }
}