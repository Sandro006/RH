# Conception du module Login — Gestion RH

Ce document décrit une conception possible du module d'authentification, basée sur les tables `utilisateur`, `Employe`, `journal_audit` et `Action` du modèle relationnel.
Les signatures sont écrites dans une notation neutre (`nom(param: Type): TypeRetour`) : elles se traduisent directement en PHP, Java, Python, etc.

---

## 1. Rappel des tables utilisées

| Table | Colonnes utiles pour le login |
|---|---|
| `utilisateur` | `id_utilisateur`, `id_employe`, `identifiant`, `mot_de_passe_hash`, `role`, `actif`, `date_creation`, `derniere_connexion` |
| `Employe` | `id`, `Nom`, `poste_id` |
| `journal_audit` | `id_utilisateur`, `action`, `entite`, `id_entite`, `date_action`, `details`, `adresse_ip` |
| `Action` | `id`, `action` (contient `LOGIN`, `LOGOUT`, `REFUS`…) |

---

## 2. Architecture en couches

```
Vue (formulaire login)
        │
Controller   ──►  AuthController
        │
Service      ──►  AuthService  ──►  PasswordHasher
        │                      └─►  SessionManager
        │                      └─►  AuditService
Repository   ──►  UtilisateurRepository
        │
Base de données (utilisateur, journal_audit)
```

Chaque couche a une seule responsabilité : le contrôleur gère HTTP, le service porte la logique métier, le repository ne fait que des requêtes SQL.

---

## 3. Types de données (DTO / modèles)

```text
Utilisateur {
  idUtilisateur: long
  idEmploye: long
  identifiant: string
  motDePasseHash: string
  role: string            // ex : 'ADMIN', 'RH', 'MANAGER', 'EMPLOYE'
  actif: boolean
  derniereConnexion: datetime | null
}

IdentifiantsConnexion {
  identifiant: string
  motDePasse: string
}

ResultatAuthentification {
  succes: boolean
  utilisateur: Utilisateur | null
  codeErreur: CodeErreurAuth | null
}

CodeErreurAuth = IDENTIFIANTS_INVALIDES | COMPTE_DESACTIVE | COMPTE_VERROUILLE | CHAMPS_VIDES

SessionUtilisateur {
  idUtilisateur: long
  identifiant: string
  role: string
  dateConnexion: datetime
}
```

---

## 4. `AuthController` — point d'entrée HTTP

| Signature | Utilité |
|---|---|
| `afficherFormulaire(): Vue` | Affiche la page de connexion ; redirige vers le tableau de bord si une session existe déjà. |
| `connecter(requete: Requete): Reponse` | Récupère `identifiant` et `motDePasse` du formulaire, appelle `AuthService.authentifier`, puis redirige (succès) ou réaffiche le formulaire avec un message d'erreur. |
| `deconnecter(requete: Requete): Reponse` | Appelle `AuthService.deconnecter` et redirige vers la page de login. |

---

## 5. `AuthService` — logique métier

| Signature | Utilité |
|---|---|
| `authentifier(identifiants: IdentifiantsConnexion, adresseIp: string): ResultatAuthentification` | Fonction centrale : valide les champs, cherche l'utilisateur, vérifie `actif`, compare le mot de passe, ouvre la session, met à jour `derniere_connexion` et écrit dans le journal d'audit. |
| `deconnecter(session: SessionUtilisateur, adresseIp: string): void` | Détruit la session et journalise l'action `LOGOUT`. |
| `validerChamps(identifiants: IdentifiantsConnexion): boolean` | Refuse les champs vides ou trop longs avant toute requête en base. |
| `verifierMotDePasse(motDePasseClair: string, hash: string): boolean` | Délègue au `PasswordHasher` ; ne contient aucun algorithme en dur. |
| `estCompteVerrouille(identifiant: string): boolean` | Indique si le compte a dépassé le nombre d'échecs autorisés (protection contre la force brute). |
| `enregistrerEchec(identifiant: string, adresseIp: string): void` | Incrémente le compteur d'échecs et journalise la tentative refusée (`REFUS`). |
| `reinitialiserEchecs(idUtilisateur: long): void` | Remet le compteur à zéro après une connexion réussie. |
| `changerMotDePasse(idUtilisateur: long, ancien: string, nouveau: string): boolean` | Vérifie l'ancien mot de passe, applique la politique de complexité, enregistre le nouveau hash. |
| `aLeRole(session: SessionUtilisateur, rolesAutorises: string[]): boolean` | Contrôle d'autorisation : sert aux garde-fous des autres modules (RH, validation, etc.). |

---

## 6. `PasswordHasher` — sécurité des mots de passe

| Signature | Utilité |
|---|---|
| `hasher(motDePasseClair: string): string` | Produit le hash stocké dans `mot_de_passe_hash` (bcrypt ou argon2, avec sel intégré). |
| `verifier(motDePasseClair: string, hash: string): boolean` | Compare un mot de passe saisi avec le hash, en temps constant. |
| `doitEtreRehashe(hash: string): boolean` | Détecte un hash issu d'un ancien coût/algorithme pour le mettre à jour à la prochaine connexion. |
| `validerComplexite(motDePasseClair: string): boolean` | Applique la politique (longueur minimale, majuscule, chiffre, caractère spécial). |

---

## 7. `SessionManager` — gestion de la session

| Signature | Utilité |
|---|---|
| `ouvrir(utilisateur: Utilisateur): SessionUtilisateur` | Crée la session après authentification et régénère l'identifiant de session (anti « session fixation »). |
| `courante(): SessionUtilisateur | null` | Retourne la session active ou `null` si l'utilisateur n'est pas connecté. |
| `detruire(): void` | Invalide la session côté serveur et supprime le cookie. |
| `estExpiree(session: SessionUtilisateur): boolean` | Vérifie le délai d'inactivité autorisé. |

---

## 8. `UtilisateurRepository` — accès aux données

| Signature | Utilité |
|---|---|
| `trouverParIdentifiant(identifiant: string): Utilisateur | null` | `SELECT` sur `utilisateur` par `identifiant` (requête préparée). |
| `trouverParId(idUtilisateur: long): Utilisateur | null` | Recharge un utilisateur à partir de la session. |
| `mettreAJourDerniereConnexion(idUtilisateur: long, date: datetime): void` | Met à jour `derniere_connexion`. |
| `mettreAJourMotDePasse(idUtilisateur: long, nouveauHash: string): void` | Met à jour `mot_de_passe_hash`. |
| `creer(utilisateur: Utilisateur): long` | Insère un nouveau compte et retourne son `id_utilisateur`. |
| `activerDesactiver(idUtilisateur: long, actif: boolean): void` | Active ou désactive un compte (champ `actif`). |

---

## 9. `AuditService` — traçabilité

| Signature | Utilité |
|---|---|
| `journaliser(idUtilisateur: long | null, action: string, entite: string, idEntite: string | null, details: string, adresseIp: string): void` | Insère une ligne dans `journal_audit` ; l'`id` de l'action est retrouvé par `SELECT id FROM Action WHERE action = ?`. |
| `journaliserConnexion(idUtilisateur: long, adresseIp: string): void` | Raccourci pour l'action `LOGIN`. |
| `journaliserDeconnexion(idUtilisateur: long, adresseIp: string): void` | Raccourci pour l'action `LOGOUT`. |
| `journaliserEchec(identifiantSaisi: string, adresseIp: string): void` | Raccourci pour l'action `REFUS` quand l'utilisateur est inconnu ou le mot de passe faux. |

---

## 10. Contrôle d'accès (middleware)

| Signature | Utilité |
|---|---|
| `exigerConnexion(requete: Requete): void` | Redirige vers le login si aucune session valide n'existe. À placer devant toutes les pages protégées. |
| `exigerRole(requete: Requete, roles: string[]): void` | Retourne une erreur 403 si le rôle de l'utilisateur n'est pas dans la liste. |

---

## 11. Déroulé de `authentifier` (pseudo-code)

```text
authentifier(identifiants, adresseIp):
  si non validerChamps(identifiants)            → échec CHAMPS_VIDES
  si estCompteVerrouille(identifiants.identifiant) → échec COMPTE_VERROUILLE

  utilisateur = repo.trouverParIdentifiant(identifiants.identifiant)
  si utilisateur est null                       → enregistrerEchec ; échec IDENTIFIANTS_INVALIDES
  si utilisateur.actif est faux                 → échec COMPTE_DESACTIVE
  si non verifierMotDePasse(motDePasse, hash)   → enregistrerEchec ; échec IDENTIFIANTS_INVALIDES

  reinitialiserEchecs(utilisateur.idUtilisateur)
  session = sessionManager.ouvrir(utilisateur)
  repo.mettreAJourDerniereConnexion(utilisateur.idUtilisateur, maintenant)
  audit.journaliserConnexion(utilisateur.idUtilisateur, adresseIp)
  retourner succès(utilisateur)
```

Remarque de sécurité : pour « utilisateur inconnu » et « mot de passe faux », on renvoie le **même message** (`IDENTIFIANTS_INVALIDES`) afin de ne pas révéler quels identifiants existent.

---

## 12. Points d'attention sur le modèle

- Le modèle actuel n'a **pas de colonne pour compter les échecs** ni verrouiller un compte. Pour `estCompteVerrouille` / `enregistrerEchec`, ajouter par exemple `tentatives_echec integer DEFAULT 0` et `verrouille_jusqu_a timestamp` dans `utilisateur`, ou calculer les échecs récents depuis `journal_audit` (action `REFUS`).
- La colonne `role` est un `varchar` : définir la liste des rôles autorisés (par exemple `ADMIN`, `RH`, `MANAGER`, `EMPLOYE`) et la valider côté application, ou la remplacer par une table `role`.
- `mot_de_passe_hash` ne doit jamais contenir un mot de passe en clair ; prévoir une longueur suffisante (au moins 255 caractères).

---

## 13. Ordre d'implémentation conseillé

1. `PasswordHasher` (testable seul)
2. `UtilisateurRepository`
3. `SessionManager`
4. `AuditService`
5. `AuthService.authentifier` puis `deconnecter`
6. `AuthController` et vue du formulaire
7. Middleware `exigerConnexion` / `exigerRole`
8. Verrouillage de compte et changement de mot de passe