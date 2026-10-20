<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Entities\Admin\Utilisateur;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Libraries\Auth\PasswordHasher;
use App\Libraries\Auth\ResultatAuthentification;
use App\Libraries\Auth\SessionManager;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Models\Admin\UtilisateurModel;

class AuthService
{
    public const MAX_TENTATIVES              = 5;
    public const DUREE_VERROUILLEMENT_MINUTES = 15;
    public const LONGUEUR_MAX_IDENTIFIANT    = 120;

    public function __construct(
        private readonly UtilisateurModel $utilisateurModel,
        private readonly PasswordHasher $passwordHasher,
        private readonly SessionManager $sessionManager,
        private readonly AuditService $auditService,
    ) {
    }

    public function authentifier(array $identifiants, string $adresseIp): ResultatAuthentification
    {
        if (!$this->validerChamps($identifiants)) {
            return ResultatAuthentification::echec(ResultatAuthentification::CHAMPS_VIDES);
        }

        $identifiant = trim((string) ($identifiants['identifiant'] ?? ''));
        $motDePasse  = (string) ($identifiants['motDePasse'] ?? '');

        $utilisateur = $this->utilisateurModel->trouverParIdentifiant($identifiant);

        if ($utilisateur === null) {
            $this->enregistrerEchec($identifiant, $adresseIp);

            return ResultatAuthentification::echec(ResultatAuthentification::IDENTIFIANTS_INVALIDES);
        }

        $idUtilisateur = (int) $utilisateur->id_utilisateur;

        if ($this->estCompteVerrouille($utilisateur)) {
            $this->auditService->journaliserEchec($idUtilisateur, $identifiant, $adresseIp);

            return ResultatAuthentification::echec(ResultatAuthentification::COMPTE_VERROUILLE);
        }

        if (!$utilisateur->actif) {
            return ResultatAuthentification::echec(ResultatAuthentification::COMPTE_DESACTIVE);
        }

        if (!$this->verifierMotDePasse($motDePasse, (string) $utilisateur->mot_de_passe_hash)) {
            $this->enregistrerEchec($identifiant, $adresseIp);

            return ResultatAuthentification::echec(ResultatAuthentification::IDENTIFIANTS_INVALIDES);
        }

        if ($this->passwordHasher->doitEtreRehashe((string) $utilisateur->mot_de_passe_hash)) {
            $this->utilisateurModel->mettreAJourMotDePasse(
                $idUtilisateur,
                $this->passwordHasher->hasher($motDePasse),
                (bool) ($utilisateur->doit_changer_mot_de_passe ?? false)
            );
        }

        $this->reinitialiserEchecs($idUtilisateur);
        $this->sessionManager->ouvrir($utilisateur, $adresseIp);
        $this->utilisateurModel->mettreAJourDerniereConnexion($idUtilisateur);
        $this->auditService->journaliserConnexion($idUtilisateur, $adresseIp);

        return ResultatAuthentification::succes($utilisateur);
    }

    public function deconnecter(SessionUtilisateur $session, string $adresseIp): void
    {
        $this->auditService->journaliserDeconnexion($session->idUtilisateur, $adresseIp);
        $this->sessionManager->detruire();
    }

    public function changerMotDePasse(int $idUtilisateur, string $ancien, string $nouveau): bool
    {
        $utilisateur = $this->utilisateurModel->trouverParId($idUtilisateur);

        if ($utilisateur === null) {
            throw new ObjetIntrouvableException("Utilisateur #{$idUtilisateur} introuvable");
        }

        if (!$this->verifierMotDePasse($ancien, (string) $utilisateur->mot_de_passe_hash)) {
            throw new DonneesIncoherentesException('Ancien mot de passe incorrect');
        }

        if (!$this->passwordHasher->validerComplexite($nouveau)) {
            throw new DonneesIncoherentesException(
                'Le nouveau mot de passe ne respecte pas la politique de complexité'
            );
        }

        if ($this->verifierMotDePasse($nouveau, (string) $utilisateur->mot_de_passe_hash)) {
            throw new DonneesIncoherentesException(
                'Le nouveau mot de passe doit être différent de l’ancien'
            );
        }

        $this->utilisateurModel->mettreAJourMotDePasse(
            $idUtilisateur,
            $this->passwordHasher->hasher($nouveau),
            false
        );
        $this->utilisateurModel->reinitialiserEchecs($idUtilisateur);
        $this->sessionManager->marquerMotDePasseChange();
        $this->auditService->journaliser(
            $idUtilisateur,
            'UPDATE',
            'utilisateur',
            $idUtilisateur,
            ['champ' => 'mot_de_passe_hash'],
            null
        );

        return true;
    }

    public function validerChamps(array $identifiants): bool
    {
        $identifiant = trim((string) ($identifiants['identifiant'] ?? ''));
        $motDePasse  = (string) ($identifiants['motDePasse'] ?? '');

        if ($identifiant === '' || $motDePasse === '') {
            return false;
        }

        if (strlen($identifiant) > self::LONGUEUR_MAX_IDENTIFIANT) {
            return false;
        }

        return strlen($motDePasse) <= 72;
    }

    public function enregistrerEchec(string $identifiant, string $adresseIp): void
    {
        $utilisateur = $this->utilisateurModel->trouverParIdentifiant($identifiant);

        if ($utilisateur === null) {
            $this->auditService->journaliserEchec(null, $identifiant, $adresseIp);

            return;
        }

        $idUtilisateur = (int) $utilisateur->id_utilisateur;

        $this->utilisateurModel->enregistrerEchec(
            $idUtilisateur,
            self::MAX_TENTATIVES,
            self::DUREE_VERROUILLEMENT_MINUTES
        );
        $this->auditService->journaliserEchec($idUtilisateur, $identifiant, $adresseIp);
    }

    public function verifierMotDePasse(string $motDePasseClair, string $hash): bool
    {
        return $this->passwordHasher->verifier($motDePasseClair, $hash);
    }

    public function estCompteVerrouille(Utilisateur|string $utilisateur): bool
    {
        if (is_string($utilisateur)) {
            $utilisateur = $this->utilisateurModel->trouverParIdentifiant($utilisateur);
        }

        if ($utilisateur === null) {
            return false;
        }

        $verrou = $utilisateur->verrouille_jusqu_a ?? null;

        if ($verrou === null) {
            return false;
        }

        return strtotime((string) $verrou) > time();
    }

    public function reinitialiserEchecs(int $idUtilisateur): void
    {
        $this->utilisateurModel->reinitialiserEchecs($idUtilisateur);
    }

    public function aLeRole(SessionUtilisateur $session, array $rolesAutorises): bool
    {
        return $session->aLeRole($rolesAutorises);
    }
}
