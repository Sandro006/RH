<?php

declare(strict_types=1);

namespace App\Entities\Cast;

use CodeIgniter\Entity\Cast\BaseCast;

/**
 * PostgreSQL retourne parfois les colonnes boolean sous la forme "t"/"f".
 * Un cast PHP direct de "f" en booléen produirait malheureusement true.
 */
final class PostgresBooleanCast extends BaseCast
{
    public static function get($value, array $params = []): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 't', 'true', 'yes', 'on'], true);
    }

    public static function set($value, array $params = []): bool
    {
        return self::get($value);
    }
}
