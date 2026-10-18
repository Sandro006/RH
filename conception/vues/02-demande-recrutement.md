# Vues — Demande de recrutement

Écrans du module **Demande de recrutement** (`conception/DemandeRecrutement.md`).
Workflow : En cours → En contrôle RH → En validation budgétaire → En validation hiérarchique →
(En consultation interne) → Validée / Refusée / Annulée.

Endpoints utilisés :

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/demandes` | Lister (filtres) |
| POST | `/api/demandes` | Créer (manager) |
| GET | `/api/demandes/{id}` | Fiche détaillée |
| PATCH | `/api/demandes/{id}` | Modifier (En cours / À corriger) |
| GET | `/api/demandes/{id}/coherence` | Résultat du contrôle de cohérence |
| GET | `/api/demandes/{id}/validations` | Décisions enregistrées |
| GET | `/api/demandes/{id}/historique` | Journal des statuts |
| POST | `/api/demandes/{id}/soumettre` | Soumettre au contrôle RH |
| POST | `/api/demandes/{id}/controle-rh` | Décision RH |
| POST | `/api/demandes/{id}/validation-budget` | Décision budgétaire |
| POST | `/api/demandes/{id}/validation-hierarchie` | Décision hiérarchique |
| POST | `/api/demandes/{id}/consultation` | Demander la consultation interne |
| POST | `/api/demandes/{id}/cloture-consultation` | Clôturer la consultation |
| POST | `/api/demandes/{id}/feu-vert` | Feu vert (vers Offre d'emploi) |
| POST | `/api/demandes/{id}/annuler` | Annuler |
| GET | `/api/referentiels/postes`, `/api/referentiels/types-contrat` | Listes de choix |

---

## Écran : Liste des demandes

- **Fichier** : `pages/recrutement/demandes.html`
- **Rôle(s)** : manager, rh, controle_gestion, direction, recruteur (lecture), admin
- **Objectif** : consulter, filtrer et accéder aux demandes.

```
┌ Filtres : [Statut ▼] [Département ▼] [Poste ▼] [Demandeur ▼] [Rechercher] [Réinitialiser] ┐
│                                                                                            │
│  N°   Poste        Département   Motif        Rémunération     Statut              Actions │
│  12   Dev Java     IT            Remplacement 35 000–45 000    ● En contrôle RH   Voir    │
│  11   Contrôleur   Finance       Création     28 000–32 000    ● Validée          Voir    │
│  …                                                                                  Pagination │
└────────────────────────────────────────────────────────────────────────────────────────┘
[+ Nouvelle demande]   (visible si rôle MANAGER)
```

| Colonne | Source |
|---|---|
| N° | `id_demande` |
| Poste | `poste.intitule` |
| Département | `service.nom` / `departement.nom` |
| Motif | `motif` (badge) |
| Rémunération | `remuneration_min`–`remuneration_max` |
| Statut | `statut` (badge coloré) |
| Actions | Voir / Soumettre / Annuler (selon rôle et statut) |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Nouvelle demande | bouton | — | ouvre `demande-form.html` |
| Filtrer | change / submit | `GET /api/demandes?…` | rafraîchit le tableau |
| Voir | ligne | — | ouvre `demande.html` |
| Soumettre | ligne (En cours) | `POST …/{id}/soumettre` | toast + statut mis à jour |

- **États** : vide (`empty-state` + « Créer une demande »), chargement, erreur, succès.
- **Navigation** : entrée = dashboard/menu ; sortie = formulaire ou fiche.

---

## Écran : Formulaire de demande (création / édition)

- **Fichier** : `pages/recrutement/demande-form.html`
- **Rôle(s)** : manager (création, édition d'une demande En cours / À corriger)
- **Objectif** : saisir les caractéristiques du besoin.

| Champ | Type | Contrôles | Notes |
|---|---|---|---|
| Poste | select (recherche) | obligatoire | issu de `referentiels/postes` |
| Demandeur | select | obligatoire | pré-rempli avec l'utilisateur connecté |
| Motif | select | obligatoire | Création / Remplacement / Renfort / Saisonnier |
| Personne remplacée | text | requis si motif = Remplacement | `nom_personne_remplacee` |
| Type de contrat | select | obligatoire | `referentiels/types-contrat` |
| Durée du contrat | text | requis si CDD/saisonnier | ex. « 6 mois » |
| Missions | textarea | obligatoire | |
| Date souhaitée | date | obligatoire, ≥ aujourd'hui | |
| Rémunération min / max | number | min ≤ max ; cohérentes avec la grille | affiche la `grille_salariale` du poste |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Enregistrer (brouillon) | submit | `POST /api/demandes` ou `PATCH /api/demandes/{id}` | fiche ouverte, statut **En cours** |
| Vérifier la cohérence | bouton | `GET /api/demandes/{id}/coherence` | panneau d'anomalies |
| Annuler | bouton | — | retour à la liste |

- **Panneau « Contrôle de cohérence »** : liste `LigneAnomalie` (règle, conforme, message).
- **États** : erreurs de champ, chargement des référentiels, succès.
- **Navigation** : entrée = liste ou fiche (mode édition) ; sortie = fiche.

---

## Écran : Fiche demande

- **Fichier** : `pages/recrutement/demande.html`
- **Rôle(s)** : manager, rh, controle_gestion, direction, recruteur, admin
- **Objectif** : consulter le détail, la progression et exécuter les décisions.

```
┌ Demande n°12 — Dev Java (IT)                         [badge ● En contrôle RH] ┐
│ Barre d'étapes : En cours ─ En contrôle RH ─ Validation budget ─ Hiérarchie  │
│                                                                              │
│ Onglets : [Détail] [Validations] [Historique]                                │
│                                                                              │
│ Motif : Remplacement — Type : CDI — Date souhaitée : 01/05/2026             │
│ Rémunération : 35 000–45 000 (grille : 32 000–48 000)                        │
│ Missions : ……                                                                │
│                                                                              │
│ Zone d'actions (selon rôle + statut)                                         │
└──────────────────────────────────────────────────────────────────────────────┘
```

**Onglets**

| Onglet | Contenu | Source |
|---|---|---|
| Détail | Données de la demande + résultat de cohérence | `GET /api/demandes/{id}`, `/coherence` |
| Validations | Liste des décisions (validateur, décision, date, commentaire) | `/validations` |
| Historique | Journal des changements de statut (timeline) | `/historique` |

**Zone d'actions — déterminée par le statut courant et le rôle**

| Statut | Rôle | Action affichée | Appel API |
|---|---|---|---|
| En cours / À corriger | manager (auteur) | Modifier | ouvre le formulaire |
| En cours / À corriger | manager | Soumettre au contrôle RH | `POST …/soumettre` |
| En contrôle RH | rh | Valider / Corriger / Refuser | `POST …/controle-rh` |
| En validation budgétaire | controle_gestion | Valider / Corriger / Refuser | `POST …/validation-budget` |
| En validation hiérarchique | direction | Feu vert / Consultation / Corriger / Refuser | `POST …/validation-hierarchie` |
| En consultation interne | direction | Clôturer (Valider / Refuser) | `POST …/cloture-consultation` |
| Validée | rh | Donner le feu vert (annonce) | `POST …/feu-vert` |
| Tout état non final | manager / rh | Annuler | `POST …/annuler` |

- **Décision** : ouvre une `modal` avec `decision` (select), `commentaire` (textarea) et, pour le
  budget, `montantApprouve`.
- **États** : statut final → actions masquées ; erreur → message ; succès → toast + rafraîchissement.
- **Navigation** : entrée = liste / dashboard ; sortie = liste, formulaire, module Offre d'emploi
  (après feu vert).

---

## Écran : File « Mes demandes à traiter »

- **Fichier** : `pages/recrutement/demandes-a-traiter.html`
- **Rôle(s)** : rh, controle_gestion, direction
- **Objectif** : liste filtrée sur les statuts `demandesEnAttente(role)`.

| Colonne | Source |
|---|---|
| N° / Poste / Demandeur | demande |
| Statut en attente d'action | statut |
| Date de soumission | historique |
| Action | bouton direct vers la modal de décision |

- **États** : vide (« Aucune demande à traiter »), chargement, erreur.

---

## Rendu des statuts (badges)

| Libellé | Couleur |
|---|---|
| En cours | bleu |
| En contrôle RH / En validation budgétaire / En validation hiérarchique / En consultation interne | orange |
| À corriger | orange |
| Validée | vert |
| Refusée / Annulée | rouge |

---

## Contrôle d'accès (UI)

| Rôle | Peut voir | Peut faire |
|---|---|---|
| manager | ses demandes + rattachées | créer, modifier, soumettre, annuler |
| rh | toutes | contrôle RH, feu vert |
| controle_gestion | en validation budgétaire | décision budgétaire |
| direction | en validation hiérarchique / consultation | décision hiérarchique, clôture consultation |
| recruteur | lecture | — |
| admin | toutes | administration |

> Les boutons sont **masqués** selon le rôle mais chaque endpoint **revérifie** `exigerRole`.
