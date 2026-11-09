<?php

declare(strict_types=1);

namespace App\Models\Selection;

use CodeIgniter\Model;
use App\Exceptions\StatutInvalideException;

class StatusEntretienModel extends Model
{
    protected $table         = 'status_entretien';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $allowedFields = ['designation'];
    protected $useTimestamps = false;

    private const TRANSITIONS = [
        'Planifié'  => ['Confirmé', 'Réalisé', 'Reporté', 'Annulé'],
        'Confirmé'  => ['Réalisé', 'Reporté', 'Annulé'],
        'Reporté'   => ['Planifié', 'Annulé'],
        'Réalisé'   => [],
        'Annulé'    => [],
    ];

    public function idParDesignation(string $designation): int
    {
        $ligne = $this->where('designation', $designation)->first();

        if ($ligne === null) {
            throw new \RuntimeException("Statut d'entretien inconnu : {$designation}");
        }

        return (int) $ligne->id;
    }

    public function designationParId(int $idStatut): string
    {
        $ligne = $this->find($idStatut);

        if ($ligne === null) {
            throw new \RuntimeException("Statut d'entretien #{$idStatut} introuvable");
        }

        return (string) $ligne->designation;
    }

    public function transitionAutorisee(string $statutActuel, string $statutCible): bool
    {
        return in_array($statutCible, self::TRANSITIONS[$statutActuel] ?? [], true);
    }

    public function estFinal(string $statut): bool
    {
        return in_array($statut, ['Réalisé', 'Annulé'], true);
    }
}
