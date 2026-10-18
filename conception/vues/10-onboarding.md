# Vues — Onboarding

Écrans du module **Onboarding** (`conception/Onboarding.md`).
Démarre à la signature du contrat : checklist d'intégration (contrat signé, badge, compte,
matériel, formation, inscription paie) + dossier documentaire.
Workflow : À démarrer → En cours → Terminé ; Annulé. Tâches : À faire → En cours → Terminée ;
Bloquée ; Annulée.

Endpoints utilisés :

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/onboardings` | Lister (filtres) |
| POST | `/api/onboardings` | Démarrer (+ tâches par défaut) |
| GET | `/api/onboardings/{id}` | Fiche onboarding |
| POST | `/api/onboardings/{id}/taches` | Ajouter une tâche |
| POST | `/api/onboardings/taches/{tid}/demarrer` | Tâche : démarrer |
| POST | `/api/onboardings/taches/{tid}/realiser` | Tâche : terminer |
| POST | `/api/onboardings/taches/{tid}/bloquer` | Tâche : bloquer |
| POST | `/api/onboardings/taches/{tid}/annuler` | Tâche : annuler |
| POST | `/api/onboardings/{id}/cloturer` | Clôturer |
| POST | `/api/onboardings/{id}/annuler` | Annuler |
| GET | `/api/onboardings/{id}/historique` | Journal des statuts |
| GET · POST | `/api/salaries/{id}/documents` | Dossier documentaire |

---

## Écran : Liste des onboardings

- **Fichier** : `pages/personnel/onboardings.html`
- **Rôle(s)** : rh, recruteur, admin
- **Objectif** : suivre les intégrations en cours.

```
┌ Filtres : [Statut ▼] [Salarié ▼] [Rechercher] ┐
│ N°  Salarié     Début      Tâches        Progression    Statut         Actions │
│ 3   A. Diallo   02/06/26   4/6 terminées [████░░] 67%   ● En cours      Voir    │
│ 2   C. Sow      02/06/26   0/6 terminées [░░░░░░]  0%   ● À démarrer    Voir    │
└───────────────────────────────────────────────────────────────────────────────┘
```

| Colonne | Source |
|---|---|
| N° | `id_onboarding` |
| Salarié | `salarie` |
| Début | `date_debut` |
| Tâches | terminées / total (`onboarding_tache`) |
| Progression | `progression(id)` |
| Statut | `statut` (badge) |
| Actions | Voir |

- **États** : vide, chargement, erreur.
- **Navigation** : entrée = menu / salarié ; sortie = fiche onboarding.

---

## Écran : Fiche onboarding (checklist)

- **Fichier** : `pages/personnel/onboarding.html`
- **Rôle(s)** : rh, recruteur, admin
- **Objectif** : piloter l'intégration, les tâches et le dossier.

```
┌ Onboarding n°3 — A. Diallo                        [badge ● En cours] ┐
│ Progression : [████░░] 67%   Début : 02/06/2026                       │
│ Onglets : [Checklist] [Documents] [Historique]                        │
│                                                                       │
│ ☑ Contrat signé            Terminée      resp. RH        02/06        │
│ ☑ Badge                    Terminée      resp. RH        03/06        │
│ ◐ Compte informatique      En cours      resp. IT        03/06        │
│ ☐ Matériel                 À faire       resp. Logistique 04/06       │
│ ☐ Formation                À faire       resp. RH        10/06        │
│ ☐ Inscription paie         À faire       resp. Compta    05/06        │
│ [+ Ajouter une tâche]                             [ Clôturer ]        │
└───────────────────────────────────────────────────────────────────────┘
```

**Onglets**

| Onglet | Contenu | Source |
|---|---|---|
| Checklist | Tâches (statut, responsable, échéance, réalisation) | `GET /api/onboardings/{id}` |
| Documents | Pièces du salarié (CNI, RIB, diplôme…) | `/api/salaries/{id}/documents` |
| Historique | Timeline onboarding + tâches | `/historique` |

**Actions par tâche** (menu contextuel / boutons)

| Statut tâche | Actions | Appel API |
|---|---|---|
| À faire | Démarrer · Bloquer · Annuler | `…/taches/{tid}/demarrer` · `/bloquer` · `/annuler` |
| En cours | Terminer · Bloquer · Annuler | `…/taches/{tid}/realiser` · `/bloquer` · `/annuler` |
| Bloquée | Débloquer (→ En cours) | `…/taches/{tid}/demarrer` |
| Terminée / Annulée | — (lecture) | — |

**Actions globales**

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Ajouter une tâche | bouton | `POST /api/onboardings/{id}/taches` | modal : intitulé, responsable, échéance |
| Clôturer | bouton | `POST /api/onboardings/{id}/cloturer` | statut **Terminé** (activé si 100%) |
| Annuler | bouton | `POST /api/onboardings/{id}/annuler` | statut **Annulé** |
| Démarrer | bouton (À démarrer) | `POST /api/onboardings/{id}/demarrer` | statut **En cours** |

- **Garde-fou UI** : *Clôturer* activé seulement lorsque toutes les tâches sont terminées/annulées.
- **États** : vide (aucune tâche), chargement, erreur, succès.
- **Navigation** : entrée = salarié / contrat signé ; sortie = fiche salarié.

---

## Écran : Ajouter un document

- **Fichier** : `pages/personnel/document-form.html` (ou modal dans l'onglet Documents)
- **Rôle(s)** : rh
- **Objectif** : déposer une pièce au dossier du salarié.

| Champ | Type | Contrôles | Notes |
|---|---|---|---|
| Type de document | select | obligatoire | `referentiels/types-document` |
| Fichier | file | obligatoire | |
| Nom du fichier | text | auto-rempli | |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Déposer | submit | `POST /api/salaries/{id}/documents` | document ajouté, liste rafraîchie |

- **États** : erreur (type manquant), succès (toast).

---

## Rendu des statuts (badges)

| Onboarding | Couleur |
|---|---|
| À démarrer | gris |
| En cours | bleu |
| En attente | orange |
| Terminé | vert |
| Annulé | rouge |

| Tâche | Couleur |
|---|---|
| À faire | gris |
| En cours | bleu |
| Bloquée | orange |
| Terminée | vert |
| Annulée | rouge |
