# Vues — Salarié

Écrans du module **Salarié** (`conception/Salarie.md`).
Créé à l'acceptation d'une proposition d'embauche ; son statut suit le contrat actif
(En période d'essai / Actif / Suspendu / Parti).

Endpoints utilisés :

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/salaries` | Lister (filtres) |
| POST | `/api/salaries` | Créer depuis une candidature (proposition acceptée) |
| GET | `/api/salaries/{id}` | Fiche salarié |
| PATCH | `/api/salaries/{id}` | Modifier les informations |
| POST | `/api/salaries/{id}/affectations` | Affecter à un poste |
| GET | `/api/salaries/{id}/affectations` | Historique des affectations |
| GET | `/api/salaries/{id}/contrats` | Contrats du salarié |
| GET | `/api/salaries/{id}/documents` | Dossier documentaire |
| GET | `/api/salaries/{id}/historique` | Journal des statuts |

---

## Écran : Liste des salariés

- **Fichier** : `pages/personnel/salaries.html`
- **Rôle(s)** : rh, recruteur, admin
- **Objectif** : rechercher et parcourir l'effectif.

```
┌ Filtres : [Statut ▼] [Département ▼] [Poste ▼] [Rechercher] ┐
│ Matricule     Nom          Poste        Département  Embauché le  Statut         Actions │
│ MAT-2026-00001 A. Diallo   Dev Java     IT           02/06       ● En période d'essai Voir │
│ MAT-2026-00002 B. Dro   Contrôleur   Finance      02/06       ● Actif        Voir    │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

| Colonne | Source |
|---|---|
| Matricule | `matricule` |
| Nom, Prénom | `salarie` |
| Poste | `affectation` → `poste.intitule` |
| Département | `poste` → `service` → `departement` |
| Embauché le | `date_embauche` |
| Statut | `statut` (badge) |
| Actions | Voir |

- **États** : vide, chargement, erreur.
- **Navigation** : entrée = menu ; sortie = fiche salarié.

---

## Écran : Fiche salarié (dossier)

- **Fichier** : `pages/personnel/salarie.html`
- **Rôle(s)** : rh, recruteur, admin
- **Objectif** : dossier complet du salarié.

```
┌ A. Diallo — MAT-2026-00001                       [badge ● Actif] ┐
│ Onglets : [Identité] [Affectations] [Contrats] [Documents] [Historique] │
│                                                                 │
│ Identité : nom, prénom, email, téléphone, date d'embauche       │
│ [Modifier]  [+ Affecter à un poste]                             │
└─────────────────────────────────────────────────────────────────┘
```

**Onglets**

| Onglet | Contenu | Source |
|---|---|---|
| Identité | Informations + bouton Modifier | `GET /api/salaries/{id}` |
| Affectations | Postes occupés (dates début/fin, motif) | `/affectations` |
| Contrats | Contrats successifs (type, période, statut) → module Contrat | `/contrats` |
| Documents | Pièces du dossier (contrat, CNI, diplôme, RIB…) | `/documents` |
| Historique | Timeline des statuts du salarié | `/historique` |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Modifier | bouton | `PATCH /api/salaries/{id}` | toast + màj |
| Affecter | bouton | `POST /api/salaries/{id}/affectations` | modal : poste + date + motif |
| Ouvrir un contrat | onglet Contrats | — | module Contrat |

> Création : le bouton *Créer le salarié* (depuis une proposition **Acceptée**) appelle
> `POST /api/salaries` avec `idCandidature`.

- **États** : vide (aucune affectation/contrat/document), chargement, erreur.
- **Navigation** : entrée = liste / proposition acceptée ; sortie = contrat, onboarding.

---

## Écran : Affecter à un poste

- **Fichier** : `pages/personnel/affectation-form.html` (ou modal)
- **Rôle(s)** : rh
- **Objectif** : rattacher un salarié à un poste (mobilité / nomination).

| Champ | Type | Contrôles | Notes |
|---|---|---|---|
| Salarié | lecture | — | |
| Poste | select (recherche) | obligatoire | `referentiels/postes` |
| Date de début | date | obligatoire | clôt l'affectation précédente |
| Motif | text | optionnel | nomination, mobilité… |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Affecter | submit | `POST /api/salaries/{id}/affectations` | nouvelle affectation + toast |

- **États** : erreur, succès (onglet Affectations rafraîchi).

---

## Rendu des statuts (badges)

| Libellé | Couleur |
|---|---|
| En attente d'embauche | gris |
| En période d'essai | orange |
| Actif | vert |
| Suspendu | orange |
| Parti | rouge |
