# Vues — Évaluation candidat

Écrans du module **Évaluation candidat** (`conception/EvaluationCandidat.md`).
Après l'entretien, plusieurs évaluateurs notent la candidature (grille de critères) ; la décision
de sélection (**Retenue** / **Refusée**) se base sur la note moyenne et un seuil.
Règle clé : seule une candidature **Retenue** peut donner lieu à une proposition d'embauche.

Endpoints utilisés :

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/candidatures/{id}` | En-tête (candidat, offre, statut) |
| GET | `/api/candidatures/{id}/evaluations` | Évaluations existantes |
| POST | `/api/candidatures/{id}/evaluations` | Enregistrer une évaluation |
| GET | `/api/candidatures/{id}/synthese` | Synthèse + note moyenne |
| GET | `/api/offres/{id}/classement` | Classement des candidatures évaluées |
| POST | `/api/candidatures/{id}/retenir` | Décision : Retenue |
| POST | `/api/candidatures/{id}/refuser` | Décision : Refusée |
| GET | `/api/referentiels/employes` | Liste des évaluateurs |

---

## Écran : Grille d'évaluation

- **Fichier** : `pages/recrutement/evaluation-form.html`
- **Rôle(s)** : recruteur, rh, direction
- **Objectif** : noter une candidature **En évaluation**.

```
┌ Évaluer la candidature n°45 — A. Diallo (Dev Java)   [badge ● En évaluation] ┐
│ Évaluateur : [ M. Sow ▼ ]                                                     │
│                                                                              │
│ Critère                    Note (0–5)   (étoiles / curseur)                  │
│ Compétences techniques     ○○○○○                                              │
│ Expérience                 ○○○○○                                              │
│ Communication              ○○○○○                                              │
│ Motivation                 ○○○○○                                              │
│ Adéquation au poste        ○○○○○                                              │
│ ───────────────────────────────────────────────                              │
│ Note globale (auto) : 3.8 / 5      [ ] saisir manuellement                    │
│ Commentaire : [__________________________________________]                    │
│ [ Annuler ]                                        [ Enregistrer ]           │
└──────────────────────────────────────────────────────────────────────────────┘
```

| Champ | Type | Contrôles | Notes |
|---|---|---|---|
| Évaluateur | select | obligatoire | plusieurs évaluateurs possibles |
| 5 critères | note 0–5 | obligatoires | stockés en JSON dans `criteres` |
| Note globale | number | auto (moyenne) ou manuelle | `note` |
| Commentaire | textarea | optionnel | |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Enregistrer | submit | `POST /api/candidatures/{id}/evaluations` | évaluation ajoutée, toast |
| Calcul auto | change critères | — (client) | met à jour la note globale |

- **États** : erreur (statut non « En évaluation » → 400), succès.
- **Navigation** : entrée = candidature / après entretien réalisé ; sortie = synthèse.

---

## Écran : Synthèse d'une candidature

- **Fichier** : `pages/recrutement/evaluation-synthese.html`
- **Rôle(s)** : recruteur, rh, direction
- **Objectif** : agréger les évaluations et prendre la décision de sélection.

```
┌ Synthèse — Candidature n°45 (A. Diallo)          [badge ● En évaluation] ┐
│ Note moyenne : 3.8 / 5   (2 évaluations)   Seuil retenu : 3.0            │
│                                                                          │
│ Évaluateur   Date       Note   Commentaire                               │
│ M. Sow       04/05      4.0    Bonne technicité                          │
│ M. Fall      05/05      3.6    Motivé                                    │
│                                                                          │
│ [ Refuser ]                                   [ Retenir ]                │
└──────────────────────────────────────────────────────────────────────────┘
```

**Contenu** : liste des évaluations, note moyenne, seuil de rétention, comparaison au seuil
(indicateur).

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Retenir | bouton | `POST /api/candidatures/{id}/retenir` | statut → **Retenue** (prérequis proposition) |
| Refuser | bouton | `POST /api/candidatures/{id}/refuser` | statut → **Refusée** (candidature conservée) |

- **Garde-fous UI** : bouton *Retenir* désactivé si note moyenne < seuil ou aucune évaluation.
- **États** : erreur (statut non « En évaluation », note insuffisante), succès.
- **Navigation** : entrée = grille / candidature ; sortie = classement, module Proposition
  d'embauche (si Retenue).

---

## Écran : Classement des candidatures (par offre)

- **Fichier** : `pages/recrutement/classement.html`
- **Rôle(s)** : rh, recruteur, direction
- **Objectif** : comparer les candidatures évaluées d'une offre et décider.

```
┌ Offre : Dev Java - #12 — Classement                                        ┐
│ Rang  Candidat    Origine    Évaluations  Note moy.   Éligible  Décision    │
│ 1     A. Diallo   Externe    2            3.8         ✔         [Retenir]   │
│ 2     B. Dro   Mobilité   1            3.2         ✔         [Retenir]   │
│ 3     C. Sow      Externe    1            2.4         ✘         [Refuser]   │
└─────────────────────────────────────────────────────────────────────────────┘
```

| Colonne | Source |
|---|---|
| Rang | tri décroissant par `note_moyenne` |
| Candidat | `candidat` |
| Origine | `origine_candidature` (badge) |
| Évaluations | nombre d'évaluations |
| Note moyenne | `moyenneParCandidature` |
| Éligible | note ≥ seuil |
| Décision | boutons Retenir / Refuser |

- **Actions** : *Retenir* / *Refuser* par ligne → `POST …/{id}/retenir` ou `/refuser`.
- **États** : vide (aucune candidature évaluée), chargement, erreur.
- **Navigation** : entrée = offre / évaluations ; sortie = synthèse, proposition d'embauche.

---

## Rendu des statuts (badges candidature)

| Libellé | Couleur |
|---|---|
| En évaluation | orange |
| Retenue | vert |
| Refusée | rouge |

> La sélection suit les **critères** et la note moyenne, jamais un filtrage automatique par
> critère externe (`RH.md` §II.B.1). Toute décision est tracée dans `journal_audit`.
