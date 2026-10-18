# Vues — Authentification & utilisateurs (Login)

Écrans du module **Login** (`conception/Auth.md`). Cible : HTML/CSS/JS statique + API REST.

Endpoints utilisés :

| Méthode | URL | Rôle |
|---|---|---|
| POST | `/api/auth/login` | Connexion |
| POST | `/api/auth/logout` | Déconnexion |
| GET | `/api/auth/session` | Utilisateur courant |
| PATCH | `/api/auth/mot-de-passe` | Changer son mot de passe |
| GET | `/api/utilisateurs` | Lister les comptes (admin) |
| POST | `/api/utilisateurs` | Créer un compte (admin) |
| PATCH | `/api/utilisateurs/{id}` | Modifier un compte (admin) |
| POST | `/api/utilisateurs/{id}/activer` · `/desactiver` | Activer / désactiver |
| POST | `/api/utilisateurs/{id}/reinitialiser-mot-de-passe` | Réinitialiser |

---

## Écran : Connexion

- **Fichier** : `login.html`
- **Rôle(s)** : visiteur (non authentifié)
- **Objectif** : s'authentifier et ouvrir une session.

```
┌───────────────────────────────────────────────┐
│                  GESTION RH                    │
│                                                │
│   Identifiant  [_______________________]       │
│   Mot de passe [_______________________]  👁    │
│                                                │
│   [        Se connecter        ]               │
│                                                │
│   ⚠ Identifiants invalides ≈ (message générique) │
└───────────────────────────────────────────────┘
```

| Champ | Type | Contrôles |
|---|---|---|
| Identifiant | text | obligatoire, non vide |
| Mot de passe | password | obligatoire, non vide |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Se connecter | submit | `POST /api/auth/login` | 200 → stocker le jeton, rediriger vers `dashboard.html` ; 401 → message générique |
| Afficher/masquer le mot de passe | clic 👁 | — | bascule le type du champ |

- **États** : chargement (bouton désactivé), erreur (bloc message), succès (redirection).
- **Navigation** : entrée = `index.html` (redirige ici si non connecté) ; sortie = `dashboard.html`.
- **Sécurité UI** : message unique « Identifiants invalides » (ne pas distinguer identifiant
  inconnu / mauvais mot de passe) ; jamais de mot de passe dans l'URL ni en clair.

---

## Écran : Tableau de bord

- **Fichier** : `dashboard.html`
- **Rôle(s)** : tous (contenu adapté au rôle)
- **Objectif** : point d'entrée après connexion, synthèse et raccourcis.

- **Zones** :
  - Cartes d'indicateurs : demandes en attente, offres publiées, candidatures, entretiens à venir,
    propositions en cours, tâches d'onboarding.
  - Liste « Mes actions en attente » (filtrée par rôle).
  - Raccourcis vers les modules autorisés.
- **Actions** : clic sur une carte → liste filtrée du module concerné.
- **États** : vide (aucune action), chargement, erreur.
- **Navigation** : entrée = login ; sortie = menu latéral.

---

## Écran : Changer mon mot de passe

- **Fichier** : `pages/profil/mot-de-passe.html`
- **Rôle(s)** : utilisateur connecté
- **Objectif** : modifier son propre mot de passe.

| Champ | Type | Contrôles |
|---|---|---|
| Ancien mot de passe | password | obligatoire |
| Nouveau mot de passe | password | politique de robustesse (longueur, complexité) |
| Confirmation | password | identique au nouveau |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Enregistrer | submit | `PATCH /api/auth/mot-de-passe` | 200 → toast succès ; 400/401 → message |

- **États** : erreur (ancien mot de passe incorrect, règles non respectées), succès (toast).

---

## Écran : Liste des utilisateurs (admin)

- **Fichier** : `pages/admin/utilisateurs.html`
- **Rôle(s)** : `admin`
- **Objectif** : consulter et administrer les comptes.

| Colonne | Source |
|---|---|
| Identifiant | `identifiant` |
| Rôle | `role` (badge) |
| Employé lié | `id_employe` → nom |
| Actif | `actif` (badge Oui/Non) |
| Dernière connexion | `derniere_connexion` |
| Actions | Modifier · Activer/Désactiver · Réinitialiser |

- **Filtres** : rôle, actif/inactif, recherche par identifiant.
- **Actions** :

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Nouveau compte | bouton | — | ouvre `utilisateur-form.html` |
| Modifier | ligne | — | ouvre le formulaire pré-rempli |
| Activer / Désactiver | ligne | `POST …/{id}/activer` ou `/desactiver` | toast + rafraîchissement |
| Réinitialiser mot de passe | ligne (modal) | `POST …/{id}/reinitialiser-mot-de-passe` | modal + toast |

- **États** : vide, chargement, erreur, succès.
- **Sécurité** : bloquer la désactivation du **dernier admin** (message).

---

## Écran : Formulaire utilisateur (admin)

- **Fichier** : `pages/admin/utilisateur-form.html`
- **Rôle(s)** : `admin`
- **Objectif** : créer ou modifier un compte.

| Champ | Type | Contrôles |
|---|---|---|
| Identifiant | text | obligatoire, unique |
| Mot de passe | password | obligatoire à la création uniquement |
| Rôle | select | `admin`, `manager`, `rh`, `controle_gestion`, `direction`, `recruteur`, `salarie` |
| Employé lié | select (recherche) | optionnel |
| Actif | checkbox | défaut : coché |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Enregistrer | submit | `POST /api/utilisateurs` ou `PATCH /api/utilisateurs/{id}` | 200 → retour à la liste + toast ; 409 → identifiant déjà pris |

- **États** : création vs édition (titre et champs adaptés), erreurs, succès.

---

## Charte des statuts (rappel)

| Libellé affiché | Couleur |
|---|---|
| Actif | vert |
| Désactivé | rouge |
| À faire / En attente | gris |
