# Conception du module Évaluation du candidat — Gestion RH

Ce document décrit une conception possible du module **évaluation candidat** (`RH.md` §II.B.1),
basée sur les tables `evaluation_candidat`, `candidature`, `Employe`, `utilisateur`,
`journal_audit` et `Action`. L'évaluation intervient **après l'entretien** ; elle débouche sur
la **décision de sélection** : seules les candidatures **Retenues** pourront donner lieu à une
**proposition d'embauche**.
Les signatures utilisent une notation neutre (`nom(param: Type): TypeRetour`).

---

## 1. Rappel des tables utilisées

| Table | Colonnes utiles |
|---|---|
| `evaluation_candidat` | `id_evaluation`, `id_candidature`, `id_evaluateur`, `criteres`, `note`, `commentaire`, `date_evaluation` |
| `candidature` | `id_candidature`, `id_offre`, `id_candidat`, `statut` (En évaluation, Retenue, Refusée) |
| `Employe` | `id`, `Nom` (l'évaluateur) |
| `utilisateur` / `journal_audit` / `Action` | traçabilité |

> Remarque : `evaluation_candidat` **ne possède ni statut ni table d'historique** ; le statut
> suivi est celui de la `candidature`.

---

## 2. Architecture en couches

```
Vue (grille d'évaluation, synthèse, décision)
        │
Controller   ──►  EvaluationController
        │
Service      ──►  EvaluationService
        │            ├─►  SelectionService        (seuil, classement, décision)
        │            ├─►  NotificationService     (résultat au candidat)
        │            └─►  AuditService            (journal_audit)
Repository   ──►  EvaluationRepository
        │
Base de données (evaluation_candidat, candidature, ...)
```

**Lien amont** : le module Entretien fait passer la candidature à **En évaluation** lors de
`EntretienService.realiser`. **Lien aval** : `retenir` autorise ensuite le module Proposition
d'embauche (et, à l'acceptation, le module Salarié/Contrat).

---

## 3. Types de données (DTO / modèles)

```text
CriteresEvaluation {           // stocké en JSON dans evaluation_candidat.criteres
  competencesTechniques: int   // 0..5
  experience: int
  communication: int
  motivation: int
  adequationPoste: int
}

EvaluationCandidat {
  idEvaluation: long
  idCandidature: long
  idEvaluateur: long           // Employe.id
  criteres: CriteresEvaluation
  note: decimal | null         // note globale (0..5), calculée ou saisie
  commentaire: string | null
  dateEvaluation: datetime
}

NouvelleEvaluation {
  idCandidature: long
  idEvaluateur: long
  criteres: CriteresEvaluation
  note: decimal | null
  commentaire: string | null
}

SyntheseCandidature {
  idCandidature: long
  evaluations: EvaluationCandidat[]
  noteMoyenne: decimal
  nombreEvaluations: int
}

ResultatSelection {
  idCandidature: long
  retenue: boolean
  noteMoyenne: decimal
  motif: string | null
}

FiltreEvaluations {
  idOffre: long | null
  idCandidature: long | null
  idEvaluateur: long | null
}
```

---

## 4. `EvaluationController` — point d'entrée HTTP

| Signature | Utilité |
|---|---|
| `afficherGrille(idCandidature: long, requete: Requete): Vue` | Affiche la grille d'évaluation d'une candidature **En évaluation**. |
| `enregistrer(idCandidature: long, requete: Requete): Reponse` | Enregistre une évaluation (critères, note, commentaire). |
| `afficherSynthese(idCandidature: long, requete: Requete): Vue` | Affiche toutes les évaluations et la note moyenne. |
| `retenir(idCandidature: long, requete: Requete): Reponse` | Marque la candidature **Retenue** (décision de sélection). |
| `refuser(idCandidature: long, requete: Requete): Reponse` | Marque la candidature **Refusée**. |
| `afficherClassement(idOffre: long, requete: Requete): Vue` | Classement des candidatures évaluées d'une offre. |

---

## 5. `EvaluationService` — logique métier

| Signature | Utilité |
|---|---|
| `evaluer(nouvelle: NouvelleEvaluation, utilisateur: SessionUtilisateur): long` | Enregistre une évaluation pour une candidature **En évaluation** (plusieurs évaluateurs possibles). Calcule la note globale si non fournie. Retourne `idEvaluation`. |
| `calculerNote(criteres: CriteresEvaluation): decimal` | Moyenne des critères (si `note` non saisie manuellement). |
| `noteMoyenne(idCandidature: long): decimal` | Moyenne des notes de toutes les évaluations de la candidature. |
| `synthese(idCandidature: long): SyntheseCandidature` | Agrège les évaluations pour la décision. |
| `listerParCandidature(idCandidature: long): EvaluationCandidat[]` | Détail des évaluations. |
| `listerParOffre(idOffre: long): EvaluationCandidat[]` | Évaluations d'une offre. |
| `classerPourOffre(idOffre: long): SyntheseCandidature[]` | Classement décroissant par note moyenne (candidatures **En évaluation**). |

### `SelectionService` — décision de sélection

| Signature | Utilité |
|---|---|
| `seuilRetention(): decimal` | Seuil minimal de note (paramétrable) pour être retenu. |
| `candidatsEligibles(idOffre: long): SyntheseCandidature[]` | Candidatures dont la note moyenne ≥ seuil. |
| `retenir(idCandidature: long, utilisateur: SessionUtilisateur): void` | Passe la candidature à **Retenue** si elle est **En évaluation** et évaluée. |
| `refuser(idCandidature: long, motif: string \| null, utilisateur: SessionUtilisateur): void` | Passe la candidature à **Refusée** (candidature conservée). |
| `decider(idCandidature: long, retenue: boolean, utilisateur: SessionUtilisateur): ResultatSelection` | Décision finale (retenir/refuser) avec traçabilité. |

---

## 6. `EvaluationRepository` — accès aux données

| Signature | Utilité |
|---|---|
| `inserer(evaluation: EvaluationCandidat): long` | `INSERT` dans `evaluation_candidat`. |
| `trouverParId(idEvaluation: long): EvaluationCandidat \| null` | `SELECT` d'une évaluation. |
| `listerParCandidature(idCandidature: long): EvaluationCandidat[]` | Évaluations d'une candidature. |
| `listerParOffre(idOffre: long): EvaluationCandidat[]` | Évaluations d'une offre (jointure via `candidature`). |
| `moyenneParCandidature(idCandidature: long): decimal \| null` | Moyenne des notes. |

---

## 7. Règles de transition (côté candidature)

| État courant (candidature) | Action | État suivant |
|---|---|---|
| Entretien prévu | `EntretienService.realiser` | En évaluation |
| En évaluation | `retenir` | Retenue |
| En évaluation | `refuser` | Refusée |

> **Règle clé** : la transition vers **Retenue** est le **prérequis** de la proposition
> d'embauche (module suivant). Une candidature **Refusée** ou **Retirée** ne peut **jamais**
> recevoir de proposition.

---

## 8. Déroulé de `retenir` (pseudo-code)

```text
retenir(idCandidature, utilisateur):
  exigerRole(utilisateur, [RH, recruteur, direction])
  candidature = candidatureRepo.trouverParId(idCandidature)
  si candidature est null                          → erreur OBJET_INTROUVABLE
  si candidature.statut != EN_EVALUATION           → erreur STATUT_INVALIDE

  synthese = synthese(idCandidature)
  si synthese.nombreEvaluations == 0               → erreur DONNEES_INCOHERENTES
  si synthese.noteMoyenne < seuilRetention()       → erreur DONNEES_INCOHERENTES

  début transaction
      candidatureWorkflow.changerStatut(idCandidature, RETENUE, utilisateur,
                                        'Décision de sélection favorable')
      notificationService.notifierRetention(candidature)
      auditService.journaliser(utilisateur.idUtilisateur, 'VALIDATION',
                               'candidature', idCandidature, null, adresseIp)
  fin transaction
```

---

## 9. Points d'attention sur le modèle

- `criteres` est un `text` : convention à fixer (JSON) pour structurer la grille d'évaluation.
- `note` est un `numeric(5,2)` : définir l'échelle (ex. 0–5) et le **seuil de rétention**.
- Plusieurs évaluations par candidature (pas d'unicité) : la note de décision est la
  **moyenne** (ou une pondération à définir).
- Aucune table d'historique : les changements de statut sont tracés sur la `candidature`
  et dans `journal_audit`.
- La décision **Retenue** conditionne la proposition d'embauche : contrôler ce prérequis dans
  le module Proposition (double garde-fou).
- Règle `RH.md` §II.B.1 : conserver les candidats éliminés (**Refusée**) pour un éventuel
  changement d'avis ; ne jamais supprimer.

---

## 10. Ordre d'implémentation conseillé

1. `EvaluationRepository` (+ convention JSON des critères)
2. `EvaluationService.evaluer` / `calculerNote`
3. `noteMoyenne` / `synthese` / `listerParCandidature`
4. `SelectionService.seuilRetention` / `candidatsEligibles` / `classerPourOffre`
5. `retenir` / `refuser` (transitions candidature + audit + notification)
6. `EvaluationController` + vues (grille, synthèse, classement)
