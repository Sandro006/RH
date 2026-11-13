<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Entities\Admin\Utilisateur;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\ConflitException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Libraries\Auth\PasswordHasher;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\Roles;
use App\Libraries\Common\SessionUtilisateur;
use App\Models\Admin\EmployeModel;
use App\Models\Admin\UtilisateurModel;
use App\Models\Personnel\SalarieModel;

class UtilisateurService
{
    private const LONGUEUR_MAX_IDENTIFIANT = 120;

    public function __construct(
        private readonly UtilisateurModel $utilisateurModel,
        private readonly EmployeModel $employeModel,
        SalarieModel|PasswordHasher $salarieModelOrHasher = null,
        PasswordHasher|AuditService|null $passwordHasherOrAudit = null,
        ?AuditService $auditService = null,
    ) {
        // Support both old and new constructor signatures for compatibility
        if ($salarieModelOrHasher instanceof PasswordHasher) {
            // Old signature: (UtilisateurModel, EmployeModel, PasswordHasher, AuditService)
            $this->salarieModel = model(\App\Models\Personnel\SalarieModel::class);
            $this->passwordHasher = $salarieModelOrHasher;
            $this->auditService = $passwordHasherOrAudit instanceof AuditService ? $passwordHasherOrAudit : service('auditService');
        } else {
            // New signature
            $this->salarieModel = $salarieModelOrHasher ?? model(\App\Models\Personnel\SalarieModel::class);
            $this->passwordHasher = $passwordHasherOrAudit instanceof PasswordHasher ? $passwordHasherOrAudit : new PasswordHasher();
            $this->auditService = $auditService ?? service('auditService');
        }
    }

    private readonly SalarieModel $salarieModel;
    private readonly PasswordHasher $passwordHasher;
    private readonly AuditService $auditService;

    public function lister(array $filtres, SessionUtilisateur $administrateur): array
    {
        $this->exigerAdminOuInformatique($administrateur);

        $page    = (int) ($filtres['page'] ?? 1);
        $parPage = (int) ($filtres['parPage'] ?? 20);

        return $this->utilisateurModel->lister($filtres, $page, $parPage);
    }

    public function creer(array $donnees, SessionUtilisateur $administrateur): int
    {
        $this->exigerAdminOuInformatique($administrateur);

        $identifiant = trim((string) ($donnees['identifiant'] ?? ''));
        $motDePasse  = (string) ($donnees['motDePasse'] ?? '');
        $role        = trim((string) ($donnees['role'] ?? ''));

        $this->validerIdentifiant($identifiant);

        if ($this->utilisateurModel->existeIdentifiant($identifiant)) {
            throw new ConflitException("L’identifiant « {$identifiant} » est déjà utilisé");
        }

        if (!Roles::estValide($role)) {
            throw new DonneesIncoherentesException('Rôle invalide');
        }

        if (!$this->passwordHasher->validerComplexite($motDePasse)) {
            throw new DonneesIncoherentesException(
                'Le mot de passe ne respecte pas la politique de complexité'
            );
        }

        $idEmploye = $this->resoudreOuCreerEmploye($donnees);
        $actif     = array_key_exists('actif', $donnees)
            ? filter_var($donnees['actif'], FILTER_VALIDATE_BOOLEAN)
            : true;

        $idUtilisateur = $this->utilisateurModel->insert([
            'id_employe'                => $idEmploye,
            'identifiant'               => $identifiant,
            'mot_de_passe_hash'         => $this->passwordHasher->hasher($motDePasse),
            'role'                      => $role,
            'actif'                     => $actif,
            'doit_changer_mot_de_passe' => false,
        ], true);

        if ($idUtilisateur === false) {
            throw new \RuntimeException('Création de l’utilisateur impossible');
        }

        $this->auditService->journaliser(
            $administrateur->idUtilisateur,
            'CREATE',
            'utilisateur',
            (int) $idUtilisateur,
            [
                'identifiant' => $identifiant,
                'role'        => $role,
                'idEmploye'   => $idEmploye,
                'actif'       => $actif,
            ],
            $administrateur->adresseIp
        );

        return (int) $idUtilisateur;
    }

    public function modifier(int $id, array $donnees, SessionUtilisateur $administrateur): void
    {
        $this->exigerAdmin($administrateur);

        $utilisateur = $this->utilisateurModel->trouverParId($id);

        if ($utilisateur === null) {
            throw new ObjetIntrouvableException("Utilisateur #{$id} introuvable");
        }

        $nouveauRole  = array_key_exists('role', $donnees)
            ? trim((string) $donnees['role'])
            : (string) $utilisateur->role;
        $nouvelActif  = array_key_exists('actif', $donnees)
            ? filter_var($donnees['actif'], FILTER_VALIDATE_BOOLEAN)
            : (bool) $utilisateur->actif;

        if (!Roles::estValide($nouveauRole)) {
            throw new DonneesIncoherentesException('Rôle invalide');
        }

        $this->verifierGardeFous($utilisateur, $administrateur, $nouveauRole, $nouvelActif);

        $modifications = [];

        if (array_key_exists('identifiant', $donnees)) {
            $identifiant = trim((string) $donnees['identifiant']);
            $this->validerIdentifiant($identifiant);

            if ($this->utilisateurModel->existeIdentifiant($identifiant, $id)) {
                throw new ConflitException("L’identifiant « {$identifiant} » est déjà utilisé");
            }

            $modifications['identifiant'] = $identifiant;
        }

        if (array_key_exists('role', $donnees)) {
            $modifications['role'] = $nouveauRole;
        }

        if (array_key_exists('actif', $donnees)) {
            $modifications['actif'] = $nouvelActif;
        }

        if (array_key_exists('idEmploye', $donnees)) {
            $modifications['id_employe'] = $this->resoudreEmploye($donnees['idEmploye']);
        }

        if ($modifications === []) {
            return;
        }

        $this->utilisateurModel->update($id, $modifications);

        $this->auditService->journaliser(
            $administrateur->idUtilisateur,
            'UPDATE',
            'utilisateur',
            $id,
            [
                'ancienneValeur' => $utilisateur->toPublicArray(),
                'nouvelleValeur' => $modifications,
            ],
            $administrateur->adresseIp
        );
    }

    public function activer(int $id, SessionUtilisateur $administrateur): void
    {
        $this->exigerAdmin($administrateur);

        $utilisateur = $this->utilisateurModel->trouverParId($id);

        if ($utilisateur === null) {
            throw new ObjetIntrouvableException("Utilisateur #{$id} introuvable");
        }

        if ($utilisateur->actif) {
            return;
        }

        $this->utilisateurModel->activerDesactiver($id, true);
        $this->utilisateurModel->reinitialiserEchecs($id);

        $this->auditService->journaliser(
            $administrateur->idUtilisateur,
            'UPDATE',
            'utilisateur',
            $id,
            ['actif' => true],
            $administrateur->adresseIp
        );
    }

    public function desactiver(int $id, SessionUtilisateur $administrateur): void
    {
        $this->exigerAdmin($administrateur);

        $utilisateur = $this->utilisateurModel->trouverParId($id);

        if ($utilisateur === null) {
            throw new ObjetIntrouvableException("Utilisateur #{$id} introuvable");
        }

        if (!$utilisateur->actif) {
            return;
        }

        $this->verifierGardeFous($utilisateur, $administrateur, (string) $utilisateur->role, false);

        $this->utilisateurModel->activerDesactiver($id, false);

        $this->auditService->journaliser(
            $administrateur->idUtilisateur,
            'UPDATE',
            'utilisateur',
            $id,
            ['actif' => false],
            $administrateur->adresseIp
        );
    }

    public function reinitialiserMotDePasse(int $id, SessionUtilisateur $administrateur): string
    {
        $this->exigerAdminOuInformatique($administrateur);

        $utilisateur = $this->utilisateurModel->trouverParId($id);

        if ($utilisateur === null) {
            throw new ObjetIntrouvableException("Utilisateur #{$id} introuvable");
        }

        $motDePasseTemporaire = $this->passwordHasher->genererTemporaire();

        $this->utilisateurModel->mettreAJourMotDePasse(
            $id,
            $this->passwordHasher->hasher($motDePasseTemporaire),
            true
        );
        $this->utilisateurModel->reinitialiserEchecs($id);

        $this->auditService->journaliser(
            $administrateur->idUtilisateur,
            'UPDATE',
            'utilisateur',
            $id,
            ['champ' => 'mot_de_passe_hash', 'reinitialisation' => true],
            $administrateur->adresseIp
        );

        return $motDePasseTemporaire;
    }

    private function verifierGardeFous(
        Utilisateur $cible,
        SessionUtilisateur $administrateur,
        string $nouveauRole,
        bool $nouvelActif
    ): void {
        if ((int) $cible->id_utilisateur === $administrateur->idUtilisateur && !$nouvelActif) {
            throw new ConflitException('Un administrateur ne peut pas désactiver son propre compte');
        }

        $cibleEstAdminActif = $cible->actif && $cible->role === Roles::ADMIN;
        $quitteAdmin        = $nouveauRole !== Roles::ADMIN || !$nouvelActif;

        if ($cibleEstAdminActif && $quitteAdmin && $this->utilisateurModel->compterAdminsActifs() <= 1) {
            throw new ConflitException('Impossible de désactiver ou rétrograder le dernier administrateur actif');
        }
    }

    private function validerIdentifiant(string $identifiant): void
    {
        if ($identifiant === '') {
            throw new DonneesIncoherentesException('Identifiant obligatoire');
        }

        if (strlen($identifiant) > self::LONGUEUR_MAX_IDENTIFIANT) {
            throw new DonneesIncoherentesException('Identifiant trop long');
        }
    }

    private function resoudreEmploye(mixed $idEmploye): ?int
    {
        if ($idEmploye === null || $idEmploye === '') {
            return null;
        }

        $id = (int) $idEmploye;

        if ($id <= 0 || !$this->employeModel->existe($id)) {
            throw new ObjetIntrouvableException("Employé #{$id} introuvable");
        }

        return $id;
    }

    /**
     * Résout l'employé lié au compte utilisateur :
     * - soit un employé existant (idEmploye fourni),
     * - soit la création d'un employé à partir d'un salarié (idSalarie) + poste (idPoste).
     */
    private function resoudreOuCreerEmploye(array $donnees): ?int
    {
        $idEmployeFourni = isset($donnees['idEmploye']) ? (int) $donnees['idEmploye'] : 0;

        if ($idEmployeFourni > 0) {
            if (!$this->employeModel->existe($idEmployeFourni)) {
                throw new ObjetIntrouvableException("Employé #{$idEmployeFourni} introuvable");
            }

            return $idEmployeFourni;
        }

        $idSalarie = isset($donnees['idSalarie']) ? (int) $donnees['idSalarie'] : 0;
        $idPoste   = isset($donnees['idPoste']) ? (int) $donnees['idPoste'] : 0;

        if ($idSalarie <= 0 || $idPoste <= 0) {
            return null;
        }

        $salarie = $this->salarieModel->find($idSalarie);

        if ($salarie === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        $employe = $this->employeModel->trouverParSalarie($idSalarie);

        if ($employe !== null) {
            return (int) $employe->id;
        }

        return $this->employeModel->insererDepuisSalarie(
            $idSalarie,
            $idPoste,
            trim(((string) ($salarie->prenom ?? '')) . ' ' . ((string) ($salarie->nom ?? ''))),
            (string) ($salarie->date_naissance ?? date('Y-m-d')),
        );
    }

    private function exigerAdmin(SessionUtilisateur $utilisateur): void
    {
        if (!$utilisateur->aLeRole([Roles::ADMIN])) {
            throw new AccesRefuseException('Rôle administrateur requis');
        }
    }

    private function exigerAdminOuInformatique(SessionUtilisateur $utilisateur): void
    {
        if ($utilisateur->aLeRole([Roles::ADMIN])) {
            return;
        }

        if ($utilisateur->idEmploye !== null) {
            $departement = $this->employeModel->getDepartementDeEmploye($utilisateur->idEmploye);
            if ($departement !== null && $departement === 'Informatique') {
                return;
            }
        }

        throw new AccesRefuseException('Accès réservé aux administrateurs ou au département Informatique');
    }
}
