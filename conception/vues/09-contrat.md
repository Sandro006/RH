# Vues — Contrat

Écrans du module **Contrat** (`conception/Contrat.md`).
Workflow : En préparation → En attente de signature → Signé → En cours → Terminé / Résilié.
La **période d'essai est optionnelle** : un interrupteur décide de la renseigner ou non.

Endpoints utilisés :

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/contrats` | Lister (filtres) |
| POST | `/api/contrats` | Créer (avec/sans période d'essai) |
| GET | `/api/contrats/{id}` | Fiche contrat |
| PATCH | `/api/contrats/{id}` | Modifier (En préparation) |
| POST | `/api/contrats/{id}/soumettre` | Soumettre pour signature |
| POST | `/api/contrats/{id}/signer` | Signer |
| POST | `/api/contrats/{id}/activer` | Activer (En cours) |
| POST | `/api/contrats/{id}/renouveler` | Renouveler (CDD) |
| POST | `/api/contrats/{id}/avenant` | Créer un avenant (mobilité interne) |
| POST | `/api/contrats/{id}/mettre-fin` | Terminer / Résilier |
| GET | `/api/contrats/{id}/historique` | Journal des statuts |
| GET | `/api/referentiels/types-contrat`, `/api/referentiels/grille/{posteId}` | Choix / fourchette |

---

## Écran : Liste des contrats

- **Fichier** : `pages/personnel/contrats.html`
- **Rôle(s)** : rh, recruteur, admin
- **Objectif** : suivre les contrats et leur état.

```
┌ Filtres : [Statut ▼] [Type ▼] [Salarié ▼] [Rechercher] ┐
│ N°  Salarié     Type  Du         Au         Essai         Statut              Actions │
│ 9   A. Diallo   CDI   02/06/26   —          Oui → 02/08   ● En cours          Voir    │
│ 8   C. Sow      CDD   02/06/26   01/12/26   Non          ● En attente sign.  Voir    │
└───────────────────────────────────────────────────────────────────────────────────────┘
```

| Colonne | Source |
|---|---|
| N° | `id_contrat` |
| Salarié | `salarie` |
| Type | `type_contrat.designation` |
| Du / Au | `date_debut` / `date_fin` |
| Essai | Oui (`periode_essai_fin`) / Non (`NULL`) |
| Statut | `statut` (badge) |
| Actions | Voir |

- **États** : vide, chargement, erreur.
- **Navigation** : entrée = menu / fiche salarié ; sortie = fiche contrat.

---

## Écran : Formulaire de contrat (création / édition)

- **Fichier** : `pages/personnel/contrat-form.html`
- **Rôle(s)** : rh
- **Objectif** : rédiger le contrat, avec ou sans période d'essai.

```
┌ Contrat — A. Diallo                                                    ┐
│ Salarié : A. Diallo (MAT-2026-00001)      Poste : [ Dev Java ▼ ]       │
│ Type de contrat : [ CDI ▼ ]   Du : [02/06/26]   Au : [ — ] (requis CDD)│
│ Rémunération : [ 42 000 ]  (grille : 38 000–48 000)                    │
│                                                                        │
│ ☑ Prévoir une période d'essai                                          │
│    Catégorie : [ Cadre ▼ ]      Durée : (auto) 2 mois → fin 01/08      │
│                          [ ] saisir une durée manuelle                 │
│ [ Annuler ]                                       [ Enregistrer ]      │
└────────────────────────────────────────────────────────────────────────┘
```

| Champ | Type | Contrôles | Notes |
|---|---|---|---|
| Salarié | lecture/select | obligatoire | |
| Poste | select (recherche) | obligatoire | |
| Type de contrat | select | obligatoire | CDI/CDD/Stage/Interim/… |
| Date de début | date | obligatoire | |
| Date de fin | date | requis si CDD/Stage/Interim | |
| Rémunération de base | number | dans la **grille** du poste | fourchette affichée |
| **Prévoir une période d'essai** | checkbox | — | si décochée → essai `NULL` |
| Catégorie | select | visible si essai | Ouvrier/Employé, Agent de maîtrise, Cadre |
| Durée de l'essai | number (auto/manuelle) | ≤ plafond légal | sinon 400 |

**Règle affichée à l'écran** : CDI sans mention écrite → **pas** de période d'essai ;
plafonds CDD (2 semaines / 1 mois selon durée) et CDI (2/3/4 mois selon catégorie).

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Enregistrer | submit | `POST /api/contrats` ou `PATCH /api/contrats/{id}` | contrat **En préparation** ; retrait de l'essai si décoché |
| Basculer période d'essai | checkbox | — (client) | affiche/masque les champs d'essai |

- **États** : erreur (durée d'essai > plafond, salaire hors grille, date de fin manquante), succès.
- **Navigation** : entrée = salarié / liste ; sortie = fiche contrat.

---

## Écran : Fiche contrat

- **Fichier** : `pages/personnel/contrat.html`
- **Rôle(s)** : rh, recruteur, admin
- **Objectif** : suivre le contrat et exécuter les étapes de son cycle de vie.

```
┌ Contrat n°9 — A. Diallo (CDI)                    [badge ● En cours] ┐
│ Du 02/06/2026 — Sans terme                                         │
│ Rémunération : 42 000   Période d'essai : 02/06 → 02/08 (2 mois)   │
│ Signature : 30/05/2026                                             │
│ Onglets : [Détail] [Historique]                                    │
│ Zone d'actions (selon statut + rôle)                              │
└────────────────────────────────────────────────────────────────────┘
```

**Zone d'actions**

| Statut | Action | Appel API | UI |
|---|---|---|---|
| En préparation | Modifier | — | `contrat-form.html` |
| En préparation | Soumettre pour signature | `POST …/soumettre` | direct |
| En attente de signature | Signer | `POST …/signer` | modal : date de signature |
| Signé | Activer | `POST …/activer` | direct → statut salarié mis à jour |
| En cours | Créer un avenant | `POST …/avenant` | modal (nouveau poste/rém.) |
| En cours (CDD) | Renouveler | `POST …/renouveler` | modal : nouvelle date de fin |
| En cours | Mettre fin | `POST …/mettre-fin` | modal : motif + terme/résiliation |

> **Activer** place le salarié **En période d'essai** si le contrat en prévoit une, sinon **Actif**.

- **États** : final (Terminé/Résilié) → actions masquées ; succès → toast.
- **Navigation** : entrée = salarié / liste ; sortie = fiche salarié, onboarding (à la signature).

---

## Écran : Avenant / Renouvellement

- **Fichier** : `pages/personnel/contrat-avenant-form.html`
- **Rôle(s)** : rh
- **Objectif** : formaliser une mobilité interne ou un renouvellement de CDD.

| Champ | Type | Contrôles |
|---|---|---|
| Contrat d'origine | lecture | — |
| Type | select | Avenant / Renouvellement |
| Nouveau poste | select | requis si avenant (mobilité) |
| Nouvelle rémunération | number | dans la grille si modifiée |
| Nouvelle date de fin | date | requis si renouvellement CDD (max 2 renouvellements) |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Enregistrer | submit | `POST …/avenant` ou `/renouveler` | nouveau contrat lié (En préparation / Signé) |

- **États** : erreur (limite de renouvellements atteinte), succès.

---

## Rendu des statuts (badges)

| Libellé | Couleur |
|---|---|
| En préparation | gris |
| En attente de signature | orange |
| Signé | bleu |
| En cours | vert |
| Terminé | gris |
| Résilié | rouge |
