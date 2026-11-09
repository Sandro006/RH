<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exception métier des congés (évite de fuiter des messages techniques).
 * Le `code` métier est mappé vers un statut HTTP par le controller (étape 7) :
 *   SOLDE_INSUFFISANT 422, CHEVAUCHEMENT 409, DELAI_NON_RESPECTE 422,
 *   DUREE_MAX 422, ANCIENNETE 422, CONTRAT_INACTIF 422,
 *   JUSTIFICATIF_REQUIS 422, STATUT_INVALIDE 409, NON_AUTORISE 403.
 */
class CongeException extends \RuntimeException
{
    public const SOLDE_INSUFFISANT = 'SOLDE_INSUFFISANT';
    public const CHEVAUCHEMENT = 'CHEVAUCHEMENT';
    public const DELAI_NON_RESPECTE = 'DELAI_NON_RESPECTE';
    public const DUREE_MAX = 'DUREE_MAX';
    public const ANCIENNETE = 'ANCIENNETE';
    public const CONTRAT_INACTIF = 'CONTRAT_INACTIF';
    public const JUSTIFICATIF_REQUIS = 'JUSTIFICATIF_REQUIS';
    public const STATUT_INVALIDE = 'STATUT_INVALIDE';
    public const NON_AUTORISE = 'NON_AUTORISE';
    public const DATES_INVALIDES = 'DATES_INVALIDES';
    public const DEMI_JOURNEE_NON_AUTORISEE = 'DEMI_JOURNEE_NON_AUTORISEE';
    public const TYPE_INACTIF = 'TYPE_INACTIF';
    public const OBJET_INTROUVABLE = 'OBJET_INTROUVABLE';

    public function __construct(string $message, string $codeMetier)
    {
        parent::__construct($message);
        $this->codeMetier = $codeMetier;
    }

    private readonly string $codeMetier;

    public function getCodeMetier(): string
    {
        return $this->codeMetier;
    }
}
