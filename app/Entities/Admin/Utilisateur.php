<?php

declare(strict_types=1);

namespace App\Entities\Admin;

use CodeIgniter\Entity\Entity;

class Utilisateur extends Entity
{
    protected $casts = [
        'id_utilisateur'          => 'integer',
        'actif'                   => 'boolean',
        'tentatives_echec'        => 'integer',
        'doit_changer_mot_de_passe' => 'boolean',
    ];

    protected $hidden = [
        'mot_de_passe_hash',
    ];

    public function toPublicArray(): array
    {
        return [
            'idUtilisateur'         => (int) $this->id_utilisateur,
            'idEmploye'             => $this->id_employe !== null ? (int) $this->id_employe : null,
            'identifiant'           => (string) $this->identifiant,
            'role'                  => (string) $this->role,
            'actif'                 => (bool) $this->actif,
            'dateCreation'          => $this->date_creation,
            'derniereConnexion'     => $this->derniere_connexion,
            'doitChangerMotDePasse' => (bool) ($this->doit_changer_mot_de_passe ?? false),
        ];
    }
}
