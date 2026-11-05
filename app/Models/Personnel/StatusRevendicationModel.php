<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class StatusRevendicationModel extends Model
{
    protected $table         = 'status_revendication';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $allowedFields = ['designation'];
    protected $useTimestamps = false;

    private const TRANSITIONS = [
        'Soumise'       => ['En discussion', 'Acceptée', 'Refusée', 'Retirée'],
        'En discussion' => ['Acceptée', 'Refusée', 'Retirée'],
        'Acceptée'      => [],
        'Refusée'       => [],
        'Retirée'       => [],
    ];

    public function idParDesignation(string $designation): int
    {
        $ligne = $this->where('designation', $designation)->first();

        if ($ligne === null) {
            throw new \RuntimeException("Statut de revendication inconnu : {$designation}");
        }

        return (int) $ligne->id;
    }

    public function designationParId(int $idStatut): string
    {
        $ligne = $this->find($idStatut);

        if ($ligne === null) {
            throw new \RuntimeException("Statut de revendication #{$idStatut} introuvable");
        }

        return (string) $ligne->designation;
    }

    public function transitionAutorisee(string $statutActuel, string $statutCible): bool
    {
        return in_array($statutCible, self::TRANSITIONS[$statutActuel] ?? [], true);
    }

    public function estFinal(string $statut): bool
    {
        return in_array($statut, ['Acceptée', 'Refusée', 'Retirée'], true);
    }
}
