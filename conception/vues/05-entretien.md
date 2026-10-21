# Vues — Entretien

Écrans du module **Entretien** (`conception/Entretien.md`).
Workflow : Planifié → Confirmé → Réalisé ; Reporté (retour à Planifié) ; Annulé.
Un entretien est planifié pour une candidature **Présélectionnée** ; sa réalisation passe la
candidature à **En évaluation**.

Endpoints utilisés :

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/entretiens` | Lister / agenda (filtres) |
| POST | `/api/entretiens` | Planifier un entretien |
| GET | `/api/entretiens/{id}` | Fiche entretien |
| POST | `/api/entretiens/{id}/confirmer` | Confirmer |
| POST | `/api/entretiens/{id}/reporter` | Reporter |
| POST | `/api/entretiens/{id}/realiser` | Saisir le compte rendu + Réalisé |
| POST | `/api/entretiens/{id}/annuler` | Annuler |
| GET | `/api/entretiens/{id}/historique` | Journal des statuts |
| POST | `/api/offres/{id}/planifier-entretiens` | Planifier en lot après clôture |
| GET | `/api/referentiels/types-entretien`, `/api/employes` | Listes de choix |

---

## Écran : Agenda des entretiens

- **Fichier** : `app/Views/selection/entretiens.php`
- **Rôle(s)** : recruteur, rh, admin
- **Objectif** : visualiser et gérer les entretiens (vue liste + calendrier).

```
┌ Filtres : [Offre ▼] [Recruteur ▼] [Statut ▼] [Du __] [Au __]   ( ) Liste  (•) Calendrier ┐
│                                                                                          │
│  Vue Calendrier (semaine)                      │  Vue Liste                              │
│  Lun 04  [◼ 10h A.Diallo]                       │  Date/heure  Candidat   Offre  Statut   │
│  Mar 05  [◼ 14h B.Ndiaye] [◼ 16h C.Sow]         │  04/05 10h   A.Diallo   #12    Confirmé │
│  …                                              │  …                                      │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

| Colonne / carte | Source |
|---|---|
| Date & heure | `date_entretien` |
| Candidat | `candidature` → `candidat` |
| Offre | `candidature` → `offre_emploi.titre` |
| Recruteur | `id_recruteur` → `employe` |
| Type | `type_entretien` |
| Statut | `statut` (badge) |
| Actions | Ouvrir / Confirmer / Reporter / Annuler |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Filtrer | change/submit | `GET /api/entretiens?…` | rafraîchit liste/agenda |
| Ouvrir | ligne/clic | — | ouvre `app/Views/selection/entretien.php` |
| Planifier en lot | bouton (offre clôturée) | `POST /api/offres/{id}/planifier-entretiens` | confirme + liste mise à jour |

- **États** : vide, chargement, erreur.
- **Navigation** : entrée = menu / candidature ; sortie = fiche entretien.

---

## Écran : Planifier un entretien

- **Fichier** : `app/Views/selection/entretien-form.php` (ou modal)
- **Rôle(s)** : recruteur, rh
- **Objectif** : créer un entretien pour une candidature présélectionnée.

| Champ | Type | Contrôles | Notes |
|---|---|---|---|
| Candidature | select (pré-rempli) | obligatoire | doit être **Présélectionnée** |
| Recruteur | select | obligatoire | `referentiels/employes` |
| Date & heure | datetime-local | obligatoire, ≥ maintenant | |
| Type d'entretien | select | obligatoire | **Entretien d'embauche** (pas intégration) |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Planifier | submit | `POST /api/entretiens` | entretien **Planifié**, candidature → **Entretien prévu** |
| Annuler | bouton | — | ferme sans créer |

- **États** : erreur (candidature non présélectionnée → 400), succès (toast + redirection).
- **Notification** : convocation envoyée au candidat et au recruteur (côté serveur).

---

## Écran : Fiche entretien

- **Fichier** : `app/Views/selection/entretien.php`
- **Rôle(s)** : recruteur, rh, admin
- **Objectif** : suivre l'entretien et saisir le compte rendu.

```
┌ Entretien n°33 — A. Diallo / Dev Java            [badge ● Confirmé] ┐
│ Date : 04/05/2026 10h00   Type : Entretien d'embauche              │
│ Recruteur : M. Sow        Candidature n°45 (→ lien)                │
│ Onglets : [Détail] [Compte rendu] [Historique]                     │
│ Zone d'actions (selon statut + rôle)                              │
└────────────────────────────────────────────────────────────────────┘
```

**Onglets**

| Onglet | Contenu | Source |
|---|---|---|
| Détail | Date, type, recruteur, lien candidature | `GET /api/entretiens/{id}` |
| Compte rendu | Champ `compte_rendu` (éditable si non final) | inclus dans la fiche |
| Historique | Timeline des statuts | `/historique` |

**Zone d'actions**

| Statut | Action | Appel API | UI |
|---|---|---|---|
| Planifié | Confirmer | `POST …/confirmer` | direct |
| Planifié / Confirmé | Reporter | `POST …/reporter` | modal : nouvelle date + motif |
| Planifié / Confirmé | Réaliser | `POST …/realiser` | modal : saisie du compte rendu |
| Planifié / Confirmé / Reporté | Annuler | `POST …/annuler` | modal (motif) |

> **Réaliser** enregistre `compte_rendu` et passe la **candidature** à **En évaluation**
> (accès au module Évaluation).

- **États** : final (Réalisé/Annulé) → actions et compte rendu en lecture seule.
- **Navigation** : entrée = agenda / candidature ; sortie = agenda, candidature, évaluation.

---

## Rendu des statuts (badges)

| Libellé | Couleur |
|---|---|
| Planifié | bleu |
| Confirmé | vert |
| Reporté | orange |
| Réalisé | vert |
| Annulé | rouge |
