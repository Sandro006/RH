# Conception du module Entretien — Gestion RH

Ce document décrit une conception possible du module **entretien** (`RH.md` §II.B.1),
basée sur les tables `entretien`, `status_entretien`, `type_entrentien`, `candidature`,
`Employe`, `entretien_status_historique`, `utilisateur`, `journal_audit` et `Action`.
Un entretien est planifié pour une candidature **présélectionnée** ; il constitue la
**première étape** du passage des candidats après la clôture de l'offre, avant l'évaluation.
Les signatures utilisent une notation neutre (`nom(param: Type): TypeRetour`).

---

## 1. Rappel des tables utilisées

| Table | Colonnes utiles |
|---|---|
| `entretien` | `id_entretien`, `id_candidature`, `id_recruteur`, `date_entretien`, `type_entretien`, `compte_rendu`, `statut` |
| `status_entretien` | `id`, `designation` (Planifié, Confirmé, Réalisé, Reporté, Annulé) |
| `type_entrentien` | `id`, `designation` (Entretien d'embauche, fin de période d'essai, intégration) |
| `candidature` | `id_candidature`, `id_offre`, `statut` (Présélectionnée, Entretien prévu, En évaluation…) |
| `Employe` | `id`, `Nom` (le recruteur qui mène l'entretien) |
| `entretien_status_historique` | `id_entretien`, `statut_ancien`, `statut_nouveau`, `date_changement`, `id_utilisateur`, `commentaire` |
| `utilisateur` / `journal_audit` / `Action` | traçabilité |

---

## 2. Architecture en couches

```
Vue (planification, saisie du compte rendu)
        │
Controller   ──►  EntretienController
        │
Service      ──►  EntretienService
        │            ├─►  WorkflowService        (transitions de statut)
        │            ├─►  NotificationService     (convocation candidat/recruteur)
        │            └─►  AuditService            (journal_audit)
Repository   ──►  EntretienRepository
        │            └─►  HistoriqueRepository    (entretien_status_historique)
        │
Base de données (entretien, status_entretien, ...)
```

**Liens** : le module Candidature appelle `EntretienService.planifier` (statut candidature
**Présélectionnée** → **Entretien prévu**) ; à la réalisation, l'entretien passe la candidature
à **En évaluation** (module Évaluation).

---

## 3. Types de données (DTO / modèles)

```text
StatutEntretien = PLANIFIE | CONFIRME | REALISE | REPORTE | ANNULE

Entretien {
  idEntretien: long
  idCandidature: long
  idRecruteur: long            // Employe.id
  dateEntretien: datetime
  typeEntretien: long          // type_entrentien.id
  compteRendu: string | null
  statut: StatutEntretien
}

NouvelEntretien {
  idCandidature: long
  idRecruteur: long
  dateEntretien: datetime
  typeEntretien: long
}

FiltreEntretiens {
  idOffre: long | null
  idCandidature: long | null
  idRecruteur: long | null
  statut: StatutEntretien | null
  dateDebut: date | null
  dateFin: date | null
}
```

---

## 4. `EntretienController` — point d'entrée HTTP

| Signature | Utilité |
|---|---|
| `planifier(idCandidature: long, requete: Requete): Reponse` | Crée un entretien pour une candidature présélectionnée. |
| `afficherFiche(idEntretien: long, requete: Requete): Vue` | Détail de l'entretien et actions possibles. |
| `confirmer(idEntretien: long, requete: Requete): Reponse` | Confirme la tenue de l'entretien. |
| `reporter(idEntretien: long, requete: Requete): Reponse` | Reporte l'entretien à une nouvelle date. |
| `realiser(idEntretien: long, requete: Requete): Reponse` | Saisit le compte rendu et marque l'entretien **Réalisé**. |
| `annuler(idEntretien: long, requete: Requete): Reponse` | Annule l'entretien. |
| `lister(requete: Requete): Vue` | Liste filtrée des entretiens (agenda recruteur). |

---

## 5. `EntretienService` — logique métier

| Signature | Utilité |
|---|---|
| `planifier(nouvel: NouvelEntretien, utilisateur: SessionUtilisateur): long` | Crée l'entretien au statut **Planifié** et fait passer la candidature à **Entretien prévu**. Retourne `idEntretien`. |
| `confirmer(idEntretien: long, utilisateur: SessionUtilisateur): void` | Passe l'entretien à **Confirmé** (candidat/recruteur disponible). |
| `reporter(idEntretien: long, nouvelleDate: datetime, motif: string \| null, utilisateur: SessionUtilisateur): void` | Passe à **Reporté** puis re-planifie à la nouvelle date (**Planifié**). |
| `realiser(idEntretien: long, compteRendu: string, utilisateur: SessionUtilisateur): void` | Passe à **Réalisé**, enregistre `compte_rendu` et fait passer la candidature à **En évaluation**. |
| `annuler(idEntretien: long, motif: string \| null, utilisateur: SessionUtilisateur): void` | Passe à **Annulé** ; remet éventuellement la candidature à **Présélectionnée**. |
| `consulter(idEntretien: long): Entretien \| null` | Détail d'un entretien. |
| `lister(filtre: FiltreEntretiens): Entretien[]` | Liste filtrée (agenda, par offre, par recruteur). |
| `historique(idEntretien: long): LigneHistorique[]` | Journal des changements de statut. |
| `planifierPourOffre(idOffre: long, utilisateur: SessionUtilisateur): long[]` | Crée les entretiens des candidatures **Présélectionnées** d'une offre (après clôture). |

---

## 6. `WorkflowService` — transitions de statut

| Signature | Utilité |
|---|---|
| `idStatut(designation: StatutEntretien): long` | Résout l'`id` dans `status_entretien`. |
| `designationStatut(idStatut: long): StatutEntretien` | Opération inverse. |
| `transitionAutorisee(statutActuel: StatutEntretien, statutCible: StatutEntretien): boolean` | Valide la table des transitions (§8). |
| `changerStatut(idEntretien: long, statutCible: StatutEntretien, utilisateur: SessionUtilisateur, commentaire: string \| null): void` | Met à jour `entretien.statut`, écrit dans `entretien_status_historique` et l'audit. |

---

## 7. `EntretienRepository` — accès aux données

| Signature | Utilité |
|---|---|
| `inserer(entretien: Entretien): long` | `INSERT` dans `entretien` (statut **Planifié**). |
| `trouverParId(idEntretien: long): Entretien \| null` | `SELECT` d'un entretien. |
| `listerParCandidature(idCandidature: long): Entretien[]` | Entretiens d'une candidature (plusieurs possibles). |
| `lister(filtre: FiltreEntretiens): Entretien[]` | `SELECT` filtré. |
| `mettreAJourCompteRendu(idEntretien: long, compteRendu: string): void` | `UPDATE` du compte rendu. |
| `mettreAJourStatut(idEntretien: long, idStatut: long): void` | `UPDATE` du seul champ `statut`. |

---

## 8. Table des transitions autorisées

| État courant | Action | État suivant |
|---|---|---|
| (aucun) | `planifier` | Planifié |
| Planifié | `confirmer` | Confirmé |
| Planifié | `reporter` | Reporté (puis Planifié) |
| Planifié | `realiser` | Réalisé |
| Planifié | `annuler` | Annulé |
| Confirmé | `reporter` | Reporté (puis Planifié) |
| Confirmé | `realiser` | Réalisé |
| Confirmé | `annuler` | Annulé |
| Reporté | `planifier` | Planifié |
| Reporté | `annuler` | Annulé |

États **finaux** : Réalisé, Annulé.

---

## 9. Déroulé de `realiser` (pseudo-code)

```text
realiser(idEntretien, compteRendu, utilisateur):
  exigerRole(utilisateur, [recruteur, RH, direction])
  entretien = repo.trouverParId(idEntretien)
  si entretien est null                            → erreur OBJET_INTROUVABLE
  si entretien.statut pas dans {PLANIFIE, CONFIRME}→ erreur STATUT_INVALIDE

  début transaction
      repo.mettreAJourCompteRendu(idEntretien, compteRendu)
      workflow.changerStatut(idEntretien, REALISE, utilisateur, 'Entretien réalisé')
      // passage de la candidature à l'étape suivante (évaluation)
      candidatureService.marquerEvaluee(entretien.idCandidature, utilisateur)
      auditService.journaliser(utilisateur.idUtilisateur, 'UPDATE',
                               'entretien', idEntretien, null, adresseIp)
  fin transaction
```

---

## 10. Points d'attention sur le modèle

- `entretien` → `type_entrentien` : la table référentielle contient un **typo** historique
  (`entrentien`) ; à conserver pour l'instant (ou renommer proprement dans le schéma).
- `statut` est un entier (FK vers `status_entretien.id`) : prévoir le mapping désignation ↔ id.
- Le type « Entretien d'intégration » de `type_entrentien` relève plutôt du module
  Onboarding ; ne pas l'utiliser pour la sélection.
- Une candidature peut avoir **plusieurs entretiens** (aucune contrainte d'unicité) : gérer
  l'agrégation côté service.
- Pas de table de créneaux/disponibilités : `date_entretien` suffit pour ce périmètre.

---

## 11. Ordre d'implémentation conseillé

1. `EntretienRepository` + `WorkflowService.idStatut/designationStatut`
2. `WorkflowService.changerStatut` + `HistoriqueRepository` + `AuditService`
3. `EntretienService.planifier` (lien candidature → **Entretien prévu**)
4. `confirmer`, `reporter`, `annuler`
5. `realiser` (compte rendu + candidature → **En évaluation**)
6. `consulter`, `lister`, `historique`
7. `EntretienController` + vues (agenda)
8. `planifierPourOffre` (traitement par lots après clôture de l'offre)
