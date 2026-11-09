<?php

declare(strict_types=1);

namespace App\Libraries\Auth;

class PasswordHasher
{
<<<<<<< HEAD
    private const LONGUEUR_MIN = 12;
=======
    private const LONGUEUR_MIN = 8 ;
>>>>>>> 57f1b34f20901561d84f17b8b34950d5e25ea61e
    private const LONGUEUR_MAX = 72;

    public function hasher(string $motDePasseClair): string
    {
        return password_hash($motDePasseClair, PASSWORD_BCRYPT);
    }

    public function verifier(string $motDePasseClair, string $hash): bool
    {
        return password_verify($motDePasseClair, $hash);
    }

    public function doitEtreRehashe(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT);
    }

    public function validerComplexite(string $motDePasseClair): bool
    {
        $longueur = strlen($motDePasseClair);

        if ($longueur < self::LONGUEUR_MIN || $longueur > self::LONGUEUR_MAX) {
            return false;
        }

        $categories = 0;

        if (preg_match('/[a-z]/', $motDePasseClair) === 1) {
            $categories++;
        }
        if (preg_match('/[A-Z]/', $motDePasseClair) === 1) {
            $categories++;
        }
        if (preg_match('/[0-9]/', $motDePasseClair) === 1) {
            $categories++;
        }
        if (preg_match('/[^a-zA-Z0-9]/', $motDePasseClair) === 1) {
            $categories++;
        }

        return $categories >= 3;
    }

    public function genererTemporaire(int $longueur = 16): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
        $max      = strlen($alphabet) - 1;

        do {
            $motDePasse = '';

            for ($i = 0; $i < $longueur; $i++) {
                $motDePasse .= $alphabet[random_int(0, $max)];
            }
        } while (!$this->validerComplexite($motDePasse));

        return $motDePasse;
    }
}
