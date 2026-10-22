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
| GET | `/api/dashboard` | Indicateurs + actions en attente |
| GET | `/api/referentiels/departements` · `/services` · `/postes` · `/rangs` | Listes de choix |
| GET | `/api/referentiels/postes/{id}/grilles` · `/grilles/{id}` | Grilles salariales |
| GET | `/api/referentiels/types-contrat` · `/types-entretien` · `/types-document` | Types |
| GET | `/api/referentiels/actions` · `/natures-revendication` | Référentiels divers |
| GET | `/api/referentiels/statuts/{domaine}` | Statuts d'un domaine |

> L'authentification repose sur la **session serveur** (cookie) ; les appels `fetch` doivent
> inclure les credentials. Une réponse `401` redirige vers `login.html`.

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
| Se connecter | submit | `POST /api/auth/login` | 200 → rediriger vers `dashboard.html` (ou `mot-de-passe.html` si `doitChangerMotDePasse`) |
| Afficher/masquer le mot de passe | clic 👁 | — | bascule le type du champ |

| Code HTTP | `error` | Affichage |
|---|---|---|
| 400 | `CHAMPS_VIDES` | « Identifiant et mot de passe obligatoires » |
| 401 | `IDENTIFIANTS_INVALIDES` | « Identifiants invalides » (message unique) |
| 403 | `COMPTE_DESACTIVE` | « Ce compte est désactivé » |
| 423 | `COMPTE_VERROUILLE` | « Compte temporairement verrouillé » + délai |

- **États** : chargement (bouton désactivé), erreur (bloc message), succès (redirection).
- **Navigation** : entrée = `index.html` (redirige ici si non connecté) ; sortie = `dashboard.html`.
- **Sécurité UI** : message unique « Identifiants invalides » pour identifiant inconnu / mauvais
  mot de passe ; jamais de mot de passe dans l'URL ni en clair ; pas de rappel du nombre d'essais
  restants (protection contre l'énumération).

---

## Écran : Tableau de bord

- **Fichier** : `dashboard.html`
- **Rôle(s)** : tous (contenu adapté au rôle)
- **Objectif** : point d'entrée après connexion, synthèse et raccourcis.

- **Zones** :
  - 6 cartes d'indicateurs issues de `GET /api/dashboard` → `indicateurs` :
    `demandesEnAttente`, `offresPubliees`, `candidatures`, `entretiensAVenir`,
    `propositionsEnCours`, `tachesOnboarding`.
  - Liste « Mes actions en attente » issue de `actionsEnAttente` (chaque ligne :
    `libelle`, `nombre`, `lien`) ; contenu filtré par le rôle.
  - Raccourcis vers les modules autorisés.
- **Actions** : clic sur une carte ou une ligne → navigation vers le `lien` fourni par l'API.
- **États** : vide (aucune action), chargement (squelettes), erreur (la carte vaut `0` si la table
  est indisponible), succès.
- **Navigation** : entrée = login ; sortie = menu latéral / modules.

**Actions en attente par rôle**

| Rôle | Actions affichées |
|---|---|
| admin | Comptes désactivés → `admin/utilisateurs.html?actif=0` |
| manager | Demandes à compléter → `recrutement/demandes.html` |
| controle_gestion | Demandes à valider (budget) → `demandes.html?statut=…` |
| direction | Demandes à valider (hiérarchie) → `demandes.html?statut=…` |
| rh | Demandes à contrôler, propositions à envoyer, onboarding en retard |
| recruteur | Candidatures à traiter, entretiens à venir |
| salarie | Mes tâches d'onboarding |

---

## Écran : Changer mon mot de passe

- **Fichier** : `pages/profil/mot-de-passe.html`
- **Rôle(s)** : utilisateur connecté
- **Objectif** : modifier son propre mot de passe.

| Champ | Type | Contrôles |
|---|---|---|
| Ancien mot de passe | password | obligatoire |
| Nouveau mot de passe | password | 12 à 72 caractères, au moins 3 catégories (minuscule, majuscule, chiffre, spécial), différent de l'ancien |
| Confirmation | password | identique au nouveau |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Enregistrer | submit | `PATCH /api/auth/mot-de-passe` | 200 → toast succès + `doitChangerMotDePasse = false` ; 400 → message ; 401 → login |

- **Mode imposé** : si la session porte `doitChangerMotDePasse = true` (compte réinitialisé par un
  admin), l'utilisateur est redirigé ici et les autres écrans répondent
  `403 CHANGEMENT_MOT_DE_PASSE_REQUIS`.
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
| Employé lié | `employeNom` (`id_employe` → nom) |
| Actif | `actif` (badge Oui/Non) |
| Créé le | `dateCreation` |
| Dernière connexion | `derniereConnexion` (`—` si jamais connecté) |
| Actions | Modifier · Activer/Désactiver · Réinitialiser |

- **Filtres** : `role`, `actif` (0/1), `q` (identifiant ou nom d'employé).
- **Pagination** : `page`, `parPage` (20 par défaut, 100 max) ; réponse `{donnees, total, page, parPage}`.
- **Actions** :

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Nouveau compte | bouton | — | ouvre `utilisateur-form.html` |
| Modifier | ligne | — | ouvre le formulaire pré-rempli |
| Activer / Désactiver | ligne | `POST …/{id}/activer` ou `/desactiver` | toast + rafraîchissement |
| Réinitialiser mot de passe | ligne (modal) | `POST …/{id}/reinitialiser-mot-de-passe` | modal affichant `motDePasseTemporaire` **une seule fois** + toast |

- **États** : vide, chargement, erreur, succès.
- **Sécurité** : un admin ne peut pas désactiver son propre compte ; la désactivation ou la
  rétrogradation du **dernier admin actif** renvoie `409 CONFLIT` (message affiché).

---

## Écran : Formulaire utilisateur (admin)

- **Fichier** : `pages/admin/utilisateur-form.html`
- **Rôle(s)** : `admin`
- **Objectif** : créer ou modifier un compte.

| Champ | Type | Contrôles |
|---|---|---|
| Identifiant | text | obligatoire, unique, ≤ 120 caractères |
| Mot de passe | password | obligatoire à la création : 12 à 72 caractères, 3 catégories minimum |
| Rôle | select | `admin`, `manager`, `rh`, `controle_gestion`, `direction`, `recruteur`, `salarie` |
| Employé lié | select (recherche) | optionnel, doit exister (`404` sinon) |
| Actif | checkbox | défaut : coché |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Enregistrer | submit | `POST /api/utilisateurs` ou `PATCH /api/utilisateurs/{id}` | `201` création / `200` édition → retour à la liste + toast ; `409` identifiant déjà pris |

- **États** : création vs édition (titre et champs adaptés), erreurs, succès.
- **Sécurité** : le mot de passe n'est **jamais** renvoyé ni pré-rempli en édition.

---

## Écran : Référentiels (lecture)

- **Fichier** : `pages/admin/referentiels.html`
- **Rôle(s)** : admin (consultation) ; les listes sont aussi consommées par les autres modules
  pour alimenter leurs `select`.
- **Objectif** : offrir un **affichage de consultation** des référentiels de la Piste A (A7) sans
  duplication dans chaque module.

```
┌ Référentiels                                              [Rechercher] ┐
│ Onglets : [Départements] [Services] [Postes] [Grilles] [Types] [Statuts] │
│                                                                         │
│  Code / Id     Libellé                  Parent / Détail        Actions  │
│  DEP-01        Direction                —                     Voir      │
│  SRV-03        Service informatique     Direction             Voir      │
│  POSTE-12      Développeur Java         SRV-03 · Rang 3       Voir      │
│  …                                                                      │
└─────────────────────────────────────────────────────────────────────────┘
```

| Onglet | Endpoint | Colonnes |
|---|---|---|
| Départements | `/api/referentiels/departements` | id, nom, description |
| Services | `/api/referentiels/services?idDepartement=` | id, nom, département |
| Postes | `/api/referentiels/postes?idService=` | id, intitulé, service, rang |
| Grilles | `/api/referentiels/postes/{id}/grilles` · `/grilles/{id}` | poste, min, max, date d'application |
| Types | `/types-contrat`, `/types-entretien`, `/types-document` | id, désignation |
| Statuts | `/api/referentiels/statuts/{domaine}` | id, désignation, domaine |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Changer d'onglet | clic | endpoint de l'onglet | rafraîchit le tableau |
| Filtrer par parent | change | `?idDepartement=` / `?idService=` | services / postes filtrés |
| Rechercher | saisie | filtrage local ou requête | tableau filtré |

- **États** : vide (`empty-state`), chargement, erreur, succès.
- **Navigation** : entrée = menu *Utilisateurs / Référentiels* ; sortie = formulaire utilisateur ou
  module consommateur.
- **Lecture seule** : aucun bouton de création/modification (les référentiels sont gérés hors UI).

---

## Charte des statuts (rappel)

| Libellé affiché | Couleur |
|---|---|
| Actif | vert |
| Désactivé | rouge |
| Verrouillé (temporaire) | orange |
| À faire / En attente | gris |
