<?php

declare(strict_types=1);

namespace App\Entities\Personnel;

use CodeIgniter\Entity\Entity;

class Document extends Entity
{
    protected $castHandlers = [
        'postgres-bool' => \App\Entities\Cast\PostgresBooleanCast::class,
    ];

    protected $dates = ['date_depot', 'date_emission', 'date_expiration'];

    protected $casts = [
        'id_document'             => 'integer',
        'id_salarie'              => 'integer',
        'type_document'           => 'integer',
        'id_utilisateur_depot'    => '?integer',
        'taille_octets'           => '?integer',
        'remplace_par'            => '?integer',
        'niveau_confidentialite'  => 'string',
        'archive'                 => 'postgres-bool',
    ];

    public function estArchive(): bool
    {
        return (bool) $this->archive;
    }

    public function estExpire(): bool
    {
        if ($this->date_expiration === null) {
            return false;
        }

        return $this->date_expiration->format('Y-m-d') < date('Y-m-d');
    }

    public function expireDans(int $jours): bool
    {
        if ($this->date_expiration === null || $this->estExpire()) {
            return false;
        }

        $limite = (new \DateTimeImmutable('today'))->modify("+{$jours} days");

        return $this->date_expiration->format('Y-m-d') <= $limite->format('Y-m-d');
    }

    /**
     * Statut de validité calculé, utilisé par l'API et les écrans RH.
     */
    public function statutValidite(int $seuilJours = 30): string
    {
        if ($this->estArchive()) {
            return 'archive';
        }

        if ($this->date_expiration === null) {
            return 'sans_expiration';
        }

        if ($this->estExpire()) {
            return 'expire';
        }

        if ($this->expireDans($seuilJours)) {
            return 'expire_bientot';
        }

        return 'valide';
    }

    /**
     * Représentation destinée à l'API : le chemin de stockage ne doit jamais
     * être exposé au client.
     */
    public function toPublicArray(): array
    {
        $donnees = $this->toArray();
        unset($donnees['chemin_fichier']);

        $donnees['date_depot']      = $this->date_depot?->format('Y-m-d H:i:s');
        $donnees['date_emission']   = $this->date_emission?->format('Y-m-d');
        $donnees['date_expiration'] = $this->date_expiration?->format('Y-m-d');
        $donnees['statut_validite'] = $this->statutValidite();

        return $donnees;
    }
}