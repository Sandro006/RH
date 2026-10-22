<?php

declare(strict_types=1);

namespace App\Libraries\Auth;

use App\Entities\Admin\Utilisateur;
use App\Libraries\Common\SessionUtilisateur;

class SessionManager
{
    private const CLE_SESSION = 'session_utilisateur';

    public function ouvrir(Utilisateur $utilisateur, string $adresseIp = ''): SessionUtilisateur
    {
        $session = service('session');
        $session->regenerate(true);

        $donnees = [
            'idUtilisateur'         => (int) $utilisateur->id_utilisateur,
            'identifiant'           => (string) $utilisateur->identifiant,
            'role'                  => (string) $utilisateur->role,
            'idEmploye'             => $utilisateur->id_employe !== null
                ? (int) $utilisateur->id_employe
                : null,
            'adresseIp'             => $adresseIp,
            'dateConnexion'         => date('c'),
            'derniereActivite'      => time(),
            'doitChangerMotDePasse' => (bool) ($utilisateur->doit_changer_mot_de_passe ?? false),
        ];

        $session->set(self::CLE_SESSION, $donnees);

        return SessionUtilisateur::depuisTableau($donnees);
    }

    public function courante(): ?SessionUtilisateur
    {
        $session = service('session');
        $donnees = $session->get(self::CLE_SESSION);

        if (!is_array($donnees) || empty($donnees['idUtilisateur'])) {
            return null;
        }

        $expiration = (int) config('Session')->expiration;

        if ($expiration > 0) {
            $derniereActivite = (int) ($donnees['derniereActivite'] ?? 0);

            if ($derniereActivite > 0 && (time() - $derniereActivite) > $expiration) {
                $session->remove(self::CLE_SESSION);

                return null;
            }
        }

        $donnees['derniereActivite'] = time();
        $session->set(self::CLE_SESSION, $donnees);

        return SessionUtilisateur::depuisTableau($donnees);
    }

    public function detruire(): void
    {
        service('session')->destroy();
    }

    public function marquerMotDePasseChange(): void
    {
        $session = service('session');
        $donnees = $session->get(self::CLE_SESSION);

        if (!is_array($donnees)) {
            return;
        }

        $donnees['doitChangerMotDePasse'] = false;
        $session->set(self::CLE_SESSION, $donnees);
    }

    public function estExpiree(SessionUtilisateur $sessionUtilisateur): bool
    {
        $expiration = (int) config('Session')->expiration;

        if ($expiration <= 0) {
            return false;
        }

        return (time() - $sessionUtilisateur->derniereActivite) > $expiration;
    }
}
