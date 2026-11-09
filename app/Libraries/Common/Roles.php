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
<<<<<<< HEAD
=======
    public const FINANCE           = 'finance';
>>>>>>> 57f1b34f20901561d84f17b8b34950d5e25ea61e

    public const TOUS = [
        self::ADMIN,
        self::MANAGER,
        self::RH,
        self::CONTROLE_GESTION,
        self::DIRECTION,
        self::RECRUTEUR,
        self::SALARIE,
<<<<<<< HEAD
=======
        self::FINANCE,
>>>>>>> 57f1b34f20901561d84f17b8b34950d5e25ea61e
    ];

    public static function estValide(string $role): bool
    {
        return in_array($role, self::TOUS, true);
    }
}
