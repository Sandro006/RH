# Vues — Conventions générales

Documentation des écrans de l'application **Gestion RH**.
Cible technique : **HTML / CSS / JavaScript statiques**, consommant l'API REST du back-end
Spring Boot (échanges en JSON via `fetch`).

---

## 1. Arborescence prévue

```
web/
├── index.html                 → redirige vers login.html ou dashboard.html
├── login.html                 → connexion
├── dashboard.html             → tableau de bord (selon rôle)
├── css/
│   ├── base.css               → reset, typographie, variables (design tokens)
│   ├── layout.css             → en-tête, menu latéral, contenu
│   └── components.css         → tableau, formulaire, modal, toast, badge…
├── js/
│   ├── api.js                 → client HTTP (fetch + gestion du jeton)
│   ├── auth.js                → session, contrôle d'accès côté UI
│   ├── ui.js                  → helpers (toast, modal, formatage dates)
│   └── pages/                 → un script par écran
└── pages/
    ├── recrutement/           → demandes, offres, candidatures…
    ├── personnel/             → salariés, contrats, onboarding…
    └── admin/                 → utilisateurs, référentiels
```

---

## 2. Gabarit commun (layout)

```
┌──────────────────────────────────────────────────────────────┐
│  En-tête : logo | recherche | utilisateur connecté | déconnexion │
├───────────────┬──────────────────────────────────────────────┤
│  Menu latéral │  Fil d'Ariane                                  │
│  (selon rôle) │  Titre de page + actions principales           │
│               │  Zone de contenu (tableau / formulaire / fiche)│
│               │                                                │
└───────────────┴──────────────────────────────────────────────┘
```

- **En-tête** : identité, rôle, bouton *Déconnexion*.
- **Menu latéral** : liens **filtrés par rôle** (cf. §4).
- **Zone de contenu** : un écran = une ressource (liste, détail, formulaire).

---

## 3. Composants réutilisables

| Composant | Rôle | Notes |
|---|---|---|
| `table` | Listes paginées | Colonnes triables, filtre, pagination |
| `form` | Saisie / édition | Validation HTML5 + messages d'erreur |
| `modal` | Confirmation / action rapide | Validation avant action destructive |
| `toast` | Retour d'opération | Succès / erreur / info |
| `badge-statut` | Afficher un statut | Couleur par statut (cf. §5) |
| `breadcrumb` | Fil d'Ariane | Navigation hiérarchique |
| `empty-state` | Liste vide | Message + action suggérée |
| `pagination` | Navigation de liste | Précédent / Suivant / page N |

---

## 4. Navigation par rôle

| Menu | admin | manager | rh | controle_gestion | direction | recruteur | salarie |
|---|---|---|---|---|---|---|---|
| Tableau de bord | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Demande de recrutement | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | — |
| Offre d'emploi | ✔ | — | ✔ | — | — | ✔ | — |
| Candidature | ✔ | — | ✔ | — | — | ✔ | — |
| Entretien | ✔ | — | ✔ | — | — | ✔ | — |
| Évaluation | ✔ | — | ✔ | — | — | ✔ | — |
| Proposition d'embauche | ✔ | — | ✔ | — | — | ✔ | — |
| Salarié / Contrat | ✔ | — | ✔ | — | — | ✔ | — |
| Onboarding | ✔ | — | ✔ | — | — | ✔ | — |
| Utilisateurs / Référentiels | ✔ | — | — | — | — | — | — |

> Le menu est **masqué côté UI**, mais chaque action est **revalidée côté serveur**
> (`SessionUtilisateur` / rôle).

---

## 5. Design tokens (variables CSS)

| Variable | Usage |
|---|---|
| `--color-primary` | Couleur d'action principale |
| `--color-success` / `--color-danger` / `--color-warning` | États |
| `--color-muted` | Texte secondaire |
| `--radius` / `--spacing-*` | Coins, espacements |
| `--font-family`, `--font-size-*` | Typographie |

Couleurs de **badge** par famille de statut :

| Sens | Couleur |
|---|---|
| En cours / En traitement | bleu |
| En attente / À faire / Brouillon | gris |
| Validée / Actif / Terminée | vert |
| Refusée / Résiliée / Annulée | rouge |
| À corriger / Suspendue / Bloquée | orange |

---

## 6. Conventions JavaScript

- `api.js` expose : `api.get(url)`, `api.post(url, body)`, `api.patch(url, body)`, `api.del(url)`.
- Le **jeton de session** est stocké (sessionStorage) et ajouté automatiquement en en-tête
  (`Authorization: Bearer …`) ; une réponse `401` redirige vers `login.html`.
- Chaque page a son script dans `js/pages/` : chargement des données → rendu → gestion des actions.
- Formatage centralisé dans `ui.js` (dates `JJ/MM/AAAA`, montants, libellés de statut).

---

## 7. États standard d'un écran

| État | Rendu |
|---|---|
| **Chargement** | Squelette/spinner |
| **Vide** | `empty-state` + action suggérée |
| **Erreur** | Message + bouton *Réessayer* |
| **Succès** | `toast` + rafraîchissement de la zone |

---

## 8. Gabarit de fiche d'écran (réutilisé dans chaque document)

```
## Écran : <nom>
- Fichier   : pages/…/xxx.html
- Rôle(s)   : …
- Objectif  : …
- Zones     : … (composants)
- Champs / colonnes : tableau
- Actions   : tableau (action, déclencheur, appel API, résultat)
- États     : vide / chargement / erreur / succès
- Navigation: entrées et sorties
```

---

## 9. Liste des documents de vues

| # | Fichier | Module |
|---|---|---|
| 00 | `00-conventions.md` | (ce document) |
| 01 | `01-login.md` | Authentification & utilisateurs |
| 02 | `02-demande-recrutement.md` | Demande de recrutement |
| 03 | `03-offre-emploi.md` | Offre d'emploi |
| 04 | `04-candidature.md` | Candidature |
| 05 | `05-entretien.md` | Entretien |
| 06 | `06-evaluation.md` | Évaluation candidat |
| 07 | `07-proposition-embauche.md` | Proposition d'embauche |
| 08 | `08-salarie.md` | Salarié |
| 09 | `09-contrat.md` | Contrat |
| 10 | `10-onboarding.md` | Onboarding |
