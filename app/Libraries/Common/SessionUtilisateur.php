<?php

declare(strict_types=1);

namespace App\Libraries\Common;

final class SessionUtilisateur
{
    public function __construct(
        public readonly int $idUtilisateur,
        public readonly string $identifiant,
        public readonly string $role,
        public readonly ?int $idEmploye,
        public readonly string $adresseIp,
        public readonly string $dateConnexion,
        public readonly int $derniereActivite,
        public readonly bool $doitChangerMotDePasse = false,
    ) {
    }

    public static function depuisTableau(array $donnees): self
    {
        return new self(
            (int) ($donnees['idUtilisateur'] ?? 0),
            (string) ($donnees['identifiant'] ?? ''),
            (string) ($donnees['role'] ?? ''),
            isset($donnees['idEmploye']) && $donnees['idEmploye'] !== null
                ? (int) $donnees['idEmploye']
                : null,
            (string) ($donnees['adresseIp'] ?? ''),
            (string) ($donnees['dateConnexion'] ?? date('c')),
            (int) ($donnees['derniereActivite'] ?? time()),
            (bool) ($donnees['doitChangerMotDePasse'] ?? false),
        );
    }

    public static function courante(): ?self
    {
        return service('sessionManager')->courante();
    }

    public function aLeRole(array $rolesAutorises): bool
    {
        return in_array($this->role, $rolesAutorises, true);
    }

    public function toArray(): array
    {
        return [
            'idUtilisateur'         => $this->idUtilisateur,
            'identifiant'           => $this->identifiant,
            'role'                  => $this->role,
            'idEmploye'             => $this->idEmploye,
            'adresseIp'             => $this->adresseIp,
            'dateConnexion'         => $this->dateConnexion,
            'derniereActivite'      => $this->derniereActivite,
            'doitChangerMotDePasse' => $this->doitChangerMotDePasse,
        ];
    }
}
