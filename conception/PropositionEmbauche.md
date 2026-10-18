# Conception du module Proposition d'embauche — Gestion RH

Ce document décrit une conception possible du module **proposition d'embauche** (`RH.md` §II.B.2),
basée sur les tables `proposition_embauche`, `status_proposition_embauche`, `candidature`,
`grille_salariale`, `proposition_embauche_status_historique`, `utilisateur`, `journal_audit` et `Action`.
Une proposition ne peut être créée que pour une candidature **Retenue** (décision du module
Évaluation). À l'acceptation, elle ouvre la création du **salarié** et du **contrat** (modules suivants).
Les signatures utilisent une notation neutre (`nom(param: Type): TypeRetour`).

---

## 1. Rappel des tables utilisées

| Table | Colonnes utiles |
|---|---|
| `proposition_embauche` | `id_proposition`, `statut`, `id_candidature`, `date_proposition`, `remuneration_proposee`, `date_reponse`, `reponse` |
| `status_proposition_embauche` | `id`, `designation` (À envoyer, Envoyée, Acceptée, Refusée, Expirée) |
| `candidature` | `id_candidature`, `id_candidat`, `id_offre`, `statut` (doit être **Retenue**) |
| `grille_salariale` | `id_grille`, `id_poste`, `salaire_min`, `salaire_max` (cadrage de la rémunération) |
| `proposition_embauche_status_historique` | `id_proposition`, `statut_ancien`, `statut_nouveau`, `date_changement`, `id_utilisateur`, `commentaire` |
| `utilisateur` / `journal_audit` / `Action` | traçabilité |

---

## 2. Architecture en couches

```
Vue (création de proposition, réponse du candidat)
        │
Controller   ──►  PropositionEmbaucheController
        │
Service      ──►  PropositionEmbaucheService
        │            ├─►  WorkflowService        (transitions de statut)
        │            ├─►  GrilleService          (contrôle rémunération)
        │            ├─►  NotificationService    (envoi au candidat)
        │            └─►  AuditService           (journal_audit)
Repository   ──►  PropositionEmbaucheRepository
        │            └─►  HistoriqueRepository    (proposition_embauche_status_historique)
        │
Base de données (proposition_embauche, ...)
```

**Liens** : amont = module Évaluation (**Retenue**) ; aval = module Salarié/Contrat
(statut **Acceptée**) et fin de process (statut **Refusée** / **Expirée**).

---

## 3. Types de données (DTO / modèles)

```text
StatutProposition = A_ENVOYER | ENVOYEE | ACCEPTEE | REFUSEE | EXPIREE

PropositionEmbauche {
  idProposition: long
  statut: StatutProposition
  idCandidature: long
  dateProposition: date
  remunerationProposee: decimal | null
  dateReponse: date | null
  reponse: string | null
}

NouvelleProposition {
  idCandidature: long
  remunerationProposee: decimal
}

ReponseProposition {
  accepte: boolean
  commentaire: string | null
}

FiltrePropositions {
  statut: StatutProposition | null
  idOffre: long | null
  idCandidature: long | null
}
```

---

## 4. `PropositionEmbaucheController` — point d'entrée HTTP

| Signature | Utilité |
|---|---|
| `creer(idCandidature: long, requete: Requete): Reponse` | Crée la proposition pour une candidature **Retenue**. |
| `afficherFiche(idProposition: long, requete: Requete): Vue` | Détail de la proposition et actions. |
| `envoyer(idProposition: long, requete: Requete): Reponse` | Envoie la proposition au candidat. |
| `repondre(idProposition: long, requete: Requete): Reponse` | Enregistre la réponse du candidat (acceptée/refusée). |
| `marquerExpiree(idProposition: long, requete: Requete): Reponse` | Marque la proposition **Expirée** (délai dépassé). |
| `lister(requete: Requete): Vue` | Liste filtrée des propositions. |

---

## 5. `PropositionEmbaucheService` — logique métier

| Signature | Utilité |
|---|---|
| `creerDepuisCandidature(idCandidature: long, remunerationProposee: decimal, utilisateur: SessionUtilisateur): long` | Crée la proposition au statut **À envoyer** depuis une candidature **Retenue** ; contrôle la rémunération vs `grille_salariale`. Retourne `idProposition`. |
| `envoyer(idProposition: long, utilisateur: SessionUtilisateur): void` | Passe la proposition à **Envoyée** et notifie le candidat. |
| `enregistrerReponse(idProposition: long, reponse: ReponseProposition, utilisateur: SessionUtilisateur \| null): void` | Enregistre `reponse`, `date_reponse` et passe à **Acceptée** ou **Refusée**. |
| `marquerExpiree(idProposition: long, utilisateur: SessionUtilisateur \| null): void` | Passe à **Expirée** si le délai de réponse est dépassé. |
| `consulter(idProposition: long): PropositionEmbauche \| null` | Détail d'une proposition. |
| `lister(filtre: FiltrePropositions): PropositionEmbauche[]` | Liste filtrée. |
| `historique(idProposition: long): LigneHistorique[]` | Journal des changements de statut. |

### `GrilleService` — cadrage de la rémunération

| Signature | Utilité |
|---|---|
| `fourchettePoste(idPoste: long): (min: decimal, max: decimal)` | Récupère la fourchette depuis `grille_salariale`. |
| `remunerationValide(idPoste: long, montant: decimal): boolean` | Vérifie `salaire_min ≤ montant ≤ salaire_max`. |

---

## 6. `WorkflowService` — transitions de statut

| Signature | Utilité |
|---|---|
| `idStatut(designation: StatutProposition): long` | Résout l'`id` dans `status_proposition_embauche`. |
| `designationStatut(idStatut: long): StatutProposition` | Opération inverse. |
| `transitionAutorisee(statutActuel: StatutProposition, statutCible: StatutProposition): boolean` | Valide la table des transitions (§8). |
| `estFinal(statut: StatutProposition): boolean` | `true` pour **Acceptée**, **Refusée**, **Expirée**. |
| `changerStatut(idProposition: long, statutCible: StatutProposition, utilisateur: SessionUtilisateur \| null, commentaire: string \| null): void` | Met à jour `proposition_embauche.statut`, écrit dans l'historique et l'audit. |

---

## 7. `PropositionEmbaucheRepository` — accès aux données

| Signature | Utilité |
|---|---|
| `inserer(proposition: PropositionEmbauche): long` | `INSERT` dans `proposition_embauche` (statut **À envoyer**). |
| `trouverParId(idProposition: long): PropositionEmbauche \| null` | `SELECT` d'une proposition. |
| `trouverParCandidature(idCandidature: long): PropositionEmbauche \| null` | Proposition liée à une candidature. |
| `lister(filtre: FiltrePropositions): PropositionEmbauche[]` | `SELECT` filtré. |
| `mettreAJourReponse(idProposition: long, reponse: string, dateReponse: date): void` | `UPDATE` de la réponse. |
| `mettreAJourStatut(idProposition: long, idStatut: long): void` | `UPDATE` du seul champ `statut`. |

---

## 8. Table des transitions autorisées

| État courant | Action | État suivant |
|---|---|---|
| (aucun) | `creerDepuisCandidature` | À envoyer |
| À envoyer | `envoyer` | Envoyée |
| Envoyée | `enregistrerReponse` (accepte) | Acceptée |
| Envoyée | `enregistrerReponse` (refuse) | Refusée |
| Envoyée | `marquerExpiree` | Expirée |

États **finaux** : Acceptée, Refusée, Expirée.

---

## 9. Déroulé de `creerDepuisCandidature` (pseudo-code)

```text
creerDepuisCandidature(idCandidature, remunerationProposee, utilisateur):
  exigerRole(utilisateur, [RH, recruteur, direction])
  candidature = candidatureRepo.trouverParId(idCandidature)
  si candidature est null                              → erreur OBJET_INTROUVABLE

  // RÈGLE CLÉ : seules les candidatures retenues peuvent recevoir une proposition
  si candidature.statut != RETENUE                     → erreur STATUT_INVALIDE

  offre = offreRepo.trouverParId(candidature.idOffre)
  si non grilleService.remunerationValide(offre.idPoste, remunerationProposee):
                                                         → erreur DONNEES_INCOHERENTES

  proposition = NouvelleProposition(idCandidature, remunerationProposee, A_ENVOYER, aujourd'hui)

  début transaction
      idProposition = repo.inserer(proposition)
      workflow.changerStatut(idProposition, A_ENVOYER, utilisateur, 'Création proposition')
      auditService.journaliser(utilisateur.idUtilisateur, 'CREATE',
                               'proposition_embauche', idProposition, null, adresseIp)
  fin transaction
  retourner idProposition
```

### Enchaînement à l'acceptation

```text
enregistrerReponse(idProposition, reponse, utilisateur):
  ... enregistrer la réponse, statut = ACCEPTEE
  si accepte:
      // ouvre la phase « Contrat et Onboarding » (modules suivants)
      salarieService.creerDepuisCandidature(idCandidature, utilisateur)
      contratService.creerAMonter(idSalarie, candidature.idOffre, utilisateur)
```

---

## 10. Points d'attention sur le modèle

- `proposition_embauche.id_candidature` **n'est plus UNIQUE** : garantir « au plus une
  proposition active par candidature » dans le service (une nouvelle proposition reste possible
  après **Expirée**/**Refusée**).
- `statut` est un entier (FK vers `status_proposition_embauche.id`) : prévoir le mapping
  désignation ↔ id.
- La **règle de sélection** (candidature **Retenue**) est le garde-fou central : la contrôler
  ici **et** dans le module Évaluation.
- Le « oui / non » du candidat (`RH.md` §II.B.2) est porté par `reponse` (texte) et le statut
  (**Acceptée** / **Refusée**) ; `date_reponse` horodate la réponse.
- La rémunération proposée doit rester dans la `grille_salariale` du poste ; la validation est
  faite par `GrilleService.remunerationValide`.
- Le délai d'expiration n'est pas stocké : le calculer (ex. `date_proposition + N jours`) ou
  ajouter une colonne `date_expiration`.

---

## 11. Ordre d'implémentation conseillé

1. `PropositionEmbaucheRepository` + `WorkflowService.idStatut/designationStatut`
2. `WorkflowService.changerStatut` + `HistoriqueRepository` + `AuditService`
3. `GrilleService.remunerationValide`
4. `PropositionEmbaucheService.creerDepuisCandidature` (contrôle statut **Retenue**)
5. `envoyer` / `enregistrerReponse` / `marquerExpiree`
6. `consulter`, `lister`, `historique`
7. `PropositionEmbaucheController` + vues
8. Enchaînement vers Salarié/Contrat à l'acceptation
