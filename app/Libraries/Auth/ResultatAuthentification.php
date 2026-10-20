<?php

declare(strict_types=1);

namespace App\Libraries\Auth;

use App\Entities\Admin\Utilisateur;

final class ResultatAuthentification
{
    public const CHAMPS_VIDES          = 'CHAMPS_VIDES';
    public const IDENTIFIANTS_INVALIDES = 'IDENTIFIANTS_INVALIDES';
    public const COMPTE_DESACTIVE      = 'COMPTE_DESACTIVE';
    public const COMPTE_VERROUILLE     = 'COMPTE_VERROUILLE';

    private function __construct(
        public readonly bool $succes,
        public readonly ?Utilisateur $utilisateur = null,
        public readonly ?string $codeErreur = null,
    ) {
    }

    public static function succes(Utilisateur $utilisateur): self
    {
        return new self(true, $utilisateur);
    }

    public static function echec(string $codeErreur): self
    {
        return new self(false, null, $codeErreur);
    }
}
