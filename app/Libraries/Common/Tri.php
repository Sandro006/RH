<?php

declare(strict_types=1);

namespace App\Libraries\Common;

/**
 * Résolution sécurisée des paramètres de tri d'une liste.
 */
final class Tri
{
    /**
     * @param array<string, mixed>  $parametres Paramètres de requête (tri/ordre ou sort/order).
     * @param array<string, string> $colonnes   Alias public => colonne SQL autorisée.
     *
     * @return array{0: string, 1: string} Colonne SQL et direction (ASC|DESC).
     */
    public static function resoudre(
        array $parametres,
        array $colonnes,
        string $defaut,
        string $ordreDefaut = 'ASC'
    ): array {
        $demande = (string) ($parametres['tri'] ?? $parametres['sort'] ?? '');
        $colonne = $colonnes[$demande] ?? $defaut;

        $ordreDemande = strtolower((string) ($parametres['ordre'] ?? $parametres['order'] ?? $ordreDefaut));
        $ordre = $ordreDemande === 'desc' ? 'DESC' : 'ASC';

        return [$colonne, $ordre];
    }
}
