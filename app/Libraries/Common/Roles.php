<?php

declare(strict_types=1);

namespace App\Libraries\Common;

final class Roles
{
    public const ADMIN             = 'admin';
    public const MANAGER           = 'manager';
    public const RH                = 'rh';
    public const CONTROLE_GESTION  = 'controle_gestion';
    public const DIRECTION         = 'direction';
    public const RECRUTEUR         = 'recruteur';
    public const SALARIE           = 'salarie';

    public const TOUS = [
        self::ADMIN,
        self::MANAGER,
        self::RH,
        self::CONTROLE_GESTION,
        self::DIRECTION,
        self::RECRUTEUR,
        self::SALARIE,
    ];

    public static function estValide(string $role): bool
    {
        return in_array($role, self::TOUS, true);
    }
}
