<?php

declare(strict_types=1);

namespace App\Models\Personnel\ReadModels;

use CodeIgniter\Model;

/** Lecture du référentiel des statuts de proposition. */
class StatusPropositionReadModel extends Model
{
    protected $table         = 'status_proposition_embauche';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = false;

    private const TRANSITIONS = [
        'À envoyer'      => ['Envoyée'],
        'Envoyée'        => ['En négociation', 'Acceptée', 'Refusée', 'Expirée'],
        'En négociation' => ['Envoyée', 'Acceptée', 'Refusée', 'Expirée'],
        'Acceptée'       => [],
        'Refusée'        => [],
        'Expirée'        => [],
    ];

    public function idParDesignation(string $designation): int
    {
        $ligne = $this->where('designation', $designation)->first();

        if ($ligne === null) {
            throw new \RuntimeException("Statut de proposition inconnu : {$designation}");
        }

        return (int) $ligne->id;
    }

    public function designationParId(int $idStatut): string
    {
        $ligne = $this->find($idStatut);

        if ($ligne === null) {
            throw new \RuntimeException("Statut de proposition #{$idStatut} introuvable");
        }

        return (string) $ligne->designation;
    }

    public function transitionAutorisee(string $statutActuel, string $statutCible): bool
    {
        return in_array($statutCible, self::TRANSITIONS[$statutActuel] ?? [], true);
    }

    public function estFinal(string $statut): bool
    {
        return in_array($statut, ['Acceptée', 'Refusée', 'Expirée'], true);
    }

    public function estEnNegociation(string $statut): bool
    {
        return $statut === 'En négociation';
    }
}
