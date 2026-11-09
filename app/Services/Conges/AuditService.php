<?php

declare(strict_types=1);

namespace App\Services\Conges;

use App\Libraries\Common\AuditService as AuditServiceExistante;

/**
 * AuditService du module Congés : adapte la signature TODO
 * (entité, id, action, ancien/nouveau, acteur) vers l'AuditService
 * existant (journal_audit) sans le dupliquer.
 */
class AuditService
{
    public function __construct(
        private readonly AuditServiceExistante $audit,
    ) {
    }

    public function journaliserTransition(
        ?int $idUtilisateur,
        string $entite,
        int $idEntite,
        string $ancienStatut,
        string $nouveauStatut,
        array $details = [],
        ?string $adresseIp = null,
    ): void {
        $this->audit->journaliser(
            $idUtilisateur,
            'VALIDATION',
            $entite,
            $idEntite,
            array_merge($details, [
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => $nouveauStatut,
            ]),
            $adresseIp
        );
    }

    public function journaliser(
        ?int $idUtilisateur,
        string $action,
        string $entite,
        int|string|null $idEntite,
        array|string|null $details = null,
        ?string $adresseIp = null,
    ): void {
        $this->audit->journaliser($idUtilisateur, $action, $entite, $idEntite, $details, $adresseIp);
    }
}
