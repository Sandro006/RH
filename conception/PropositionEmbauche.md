# Conception du module Proposition d'embauche — Gestion RH

Ce document décrit une conception possible du module **proposition d'embauche** (`RH.md` §II.B.2),
basée sur les tables `proposition_embauche`, `status_proposition_embauche`, `candidature`,
`grille_salariale`, `revendication`, `nature_revendication`, `status_revendication`,
`revendication_status_historique`, `proposition_embauche_status_historique`, `utilisateur`,
`journal_audit` et `Action`.
Une proposition ne peut être créée que pour une candidature **Retenue** (décision du module
Évaluation). À l'acceptation, elle ouvre la création du **salarié** et du **contrat** (modules suivants).
Entre l'envoi et la réponse, le candidat peut formuler des **revendications** (contre-propositions :
rémunération, date d'embauche, temps de travail, avantages…), ce que `RH.md` §II.B.2 appelle des
« négations » : la réponse n'est pas seulement oui/non.
Les signatures utilisent une notation neutre (`nom(param: Type): TypeRetour`).

---

## 1. Rappel des tables utilisées

| Table | Colonnes utiles |
|---|---|
| `proposition_embauche` | `id_proposition`, `statut`, `id_candidature`, `date_proposition`, `remuneration_proposee`, `date_embauche_proposee`, `date_reponse`, `reponse` |
| `status_proposition_embauche` | `id`, `designation` (À envoyer, Envoyée, **En négociation**, Acceptée, Refusée, Expirée) |
| `candidature` | `id_candidature`, `id_candidat`, `id_offre`, `statut` (doit être **Retenue**) |
| `grille_salariale` | `id_grille`, `id_poste`, `salaire_min`, `salaire_max` (cadrage de la rémunération) |
| `revendication` | `id_revendication`, `id_proposition`, `sens` (CANDIDAT/ENTREPRISE), `id_candidat`, `id_utilisateur`, `nature_id`, `valeur_demandee`, `commentaire`, `statut_id`, `date_creation`, `id_repondant`, `reponse`, `date_reponse` |
| `nature_revendication` | `id`, `designation` (Rémunération, Date d'embauche, Temps de travail, Avantages, Poste/Intitulé, Autre) |
| `status_revendication` | `id`, `designation` (Soumise, En discussion, Acceptée, Refusée, Retirée) |
| `revendication_status_historique` | `id_revendication`, `statut_ancien`, `statut_nouveau`, `date_changement`, `id_utilisateur`, `commentaire` |
| `proposition_embauche_status_historique` | `id_proposition`, `statut_ancien`, `statut_nouveau`, `date_changement`, `id_utilisateur`, `commentaire` |
| `utilisateur` / `journal_audit` / `Action` | traçabilité |

---

## 2. Architecture en couches

```
Vue (création de proposition, négociation, réponse du candidat)
        │
Controller   ──►  PropositionEmbaucheController
        │
Service      ──►  PropositionEmbaucheService
        │            ├─►  RevendicationService    (fil de négociation)
        │            ├─►  WorkflowService         (transitions de statut)
        │            ├─►  GrilleService           (contrôle rémunération)
        │            ├─►  NotificationService     (envoi / relance)
        │            └─►  AuditService            (journal_audit)
Repository   ──►  PropositionEmbaucheRepository
        │            ├─►  RevendicationRepository
        │            └─►  HistoriqueRepository     (proposition_embauche_status_historique,
        │                                           revendication_status_historique)
        │
Base de données (proposition_embauche, revendication, ...)
```

**Liens** : amont = module Évaluation (**Retenue**) ; aval = module Salarié/Contrat
(statut **Acceptée**) et fin de process (statut **Refusée** / **Expirée**).

---

## 3. Types de données (DTO / modèles)

```text
StatutProposition = A_ENVOYER | ENVOYEE | EN_NEGOCIATION | ACCEPTEE | REFUSEE | EXPIREE

SensRevendication = CANDIDAT | ENTREPRISE

StatutRevendication = SOUMISE | EN_DISCUSSION | ACCEPTEE | REFUSEE | RETIREE

PropositionEmbauche {
  idProposition: long
  statut: StatutProposition
  idCandidature: long
  dateProposition: date
  remunerationProposee: decimal | null
  dateEmbaucheProposee: date | null
  dateReponse: date | null
  reponse: string | null
}

NouvelleProposition {
  idCandidature: long
  remunerationProposee: decimal
  dateEmbaucheProposee: date | null
}

Revendication {
  idRevendication: long
  idProposition: long
  sens: SensRevendication
  idCandidat: long | null       // si sens = CANDIDAT
  idUtilisateur: long | null    // si sens = ENTREPRISE
  nature: string                // nature_revendication.designation
  valeurDemandee: string | null
  commentaire: string | null
  statut: StatutRevendication
  dateCreation: datetime
  idRepondant: long | null
  reponse: string | null
  dateReponse: datetime | null
}

NouvelleRevendication {
  idProposition: long
  sens: SensRevendication
  idCandidat: long | null
  nature: string
  valeurDemandee: string | null
  commentaire: string | null
}

ReponseRevendication {
  decision: StatutRevendication   // ACCEPTEE | REFUSEE | EN_DISCUSSION
  reponse: string | null
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

FiltreRevendications {
  idProposition: long | null
  statut: StatutRevendication | null
  sens: SensRevendication | null
}
```

---

## 4. `PropositionEmbaucheController` — point d'entrée HTTP

| Signature | Utilité |
|---|---|
| `creer(idCandidature: long, requete: Requete): Reponse` | Crée la proposition pour une candidature **Retenue**. |
| `afficherFiche(idProposition: long, requete: Requete): Vue` | Détail de la proposition, des revendications et actions. |
| `envoyer(idProposition: long, requete: Requete): Reponse` | Envoie la proposition au candidat. |
| `soumettreRevendication(idProposition: long, requete: Requete): Reponse` | Le candidat formule une revendication (contre-proposition). |
| `repondreRevendication(idRevendication: long, requete: Requete): Reponse` | La RH accepte / refuse / discute une revendication. |
| `contreProposer(idProposition: long, requete: Requete): Reponse` | La RH émet une nouvelle offre après négociation. |
| `repondre(idProposition: long, requete: Requete): Reponse` | Enregistre la réponse du candidat (acceptée/refusée). |
| `marquerExpiree(idProposition: long, requete: Requete): Reponse` | Marque la proposition **Expirée** (délai dépassé). |
| `lister(requete: Requete): Vue` | Liste filtrée des propositions. |

---

## 5. `PropositionEmbaucheService` — logique métier

| Signature | Utilité |
|---|---|
| `creerDepuisCandidature(idCandidature: long, remunerationProposee: decimal, dateEmbaucheProposee: date \| null, utilisateur: SessionUtilisateur): long` | Crée la proposition au statut **À envoyer** depuis une candidature **Retenue** ; contrôle la rémunération vs `grille_salariale`. Retourne `idProposition`. |
| `envoyer(idProposition: long, utilisateur: SessionUtilisateur): void` | Passe la proposition à **Envoyée** et notifie le candidat. |
| `enregistrerReponse(idProposition: long, reponse: ReponseProposition, utilisateur: SessionUtilisateur \| null): void` | Enregistre `reponse`, `date_reponse` et passe à **Acceptée** ou **Refusée** ; **refuse** si une revendication est encore ouverte (Soumise / En discussion). |
| `contreProposer(idProposition: long, remunerationProposee: decimal, dateEmbaucheProposee: date \| null, utilisateur: SessionUtilisateur): void` | Après négociation, met à jour l'offre et repasse la proposition à **Envoyée** (nouveau tour). |
| `marquerExpiree(idProposition: long, utilisateur: SessionUtilisateur \| null): void` | Passe à **Expirée** si le délai de réponse est dépassé et qu'aucune négociation n'est en cours. |
| `consulter(idProposition: long): PropositionEmbauche \| null` | Détail d'une proposition. |
| `lister(filtre: FiltrePropositions): PropositionEmbauche[]` | Liste filtrée. |
| `historique(idProposition: long): LigneHistorique[]` | Journal des changements de statut. |

### `RevendicationService` — fil de négociation

| Signature | Utilité |
|---|---|
| `soumettre(nouvelle: NouvelleRevendication, auteur: SessionUtilisateur \| null): long` | Enregistre une revendication **Soumise** et passe la proposition à **En négociation**. Retourne `idRevendication`. |
| `repondre(idRevendication: long, reponse: ReponseRevendication, utilisateur: SessionUtilisateur): void` | Décide d'une revendication (**Acceptée**, **Refusée**, **En discussion**) ; trace l'historique. |
| `appliquerAcceptees(idProposition: long, utilisateur: SessionUtilisateur): void` | Reporte les revendications acceptées sur la proposition (rémunération, date d'embauche), puis `contreProposer`. |
| `retirer(idRevendication: long, auteur: SessionUtilisateur \| null): void` | Le candidat retire sa revendication (**Retirée**). |
| `lister(idProposition: long): Revendication[]` | Fil complet de la négociation (candidat + entreprise). |
| `ouvertes(idProposition: long): Revendication[]` | Revendications **Soumise** / **En discussion** (bloquent l'acceptation). |
| `historique(idRevendication: long): LigneHistorique[]` | Journal des statuts d'une revendication. |

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
| `estEnNegociation(statut: StatutProposition): boolean` | `true` pour **En négociation**. |
| `changerStatut(idProposition: long, statutCible: StatutProposition, utilisateur: SessionUtilisateur \| null, commentaire: string \| null): void` | Met à jour `proposition_embauche.statut`, écrit dans l'historique et l'audit. |

### Workflow de revendication

| Signature | Utilité |
|---|---|
| `idStatutRevendication(designation: StatutRevendication): long` | Résout l'`id` dans `status_revendication`. |
| `changerStatutRevendication(idRevendication: long, statutCible: StatutRevendication, utilisateur: SessionUtilisateur \| null, commentaire: string \| null): void` | Met à jour `revendication.statut`, écrit dans `revendication_status_historique` et l'audit. |

---

## 7. `PropositionEmbaucheRepository` — accès aux données

| Signature | Utilité |
|---|---|
| `inserer(proposition: PropositionEmbauche): long` | `INSERT` dans `proposition_embauche` (statut **À envoyer**). |
| `trouverParId(idProposition: long): PropositionEmbauche \| null` | `SELECT` d'une proposition. |
| `trouverParCandidature(idCandidature: long): PropositionEmbauche \| null` | Proposition liée à une candidature. |
| `lister(filtre: FiltrePropositions): PropositionEmbauche[]` | `SELECT` filtré. |
| `mettreAJourOffre(idProposition: long, remunerationProposee: decimal, dateEmbaucheProposee: date \| null): void` | `UPDATE` de l'offre (contre-proposition). |
| `mettreAJourReponse(idProposition: long, reponse: string, dateReponse: date): void` | `UPDATE` de la réponse. |
| `mettreAJourStatut(idProposition: long, idStatut: long): void` | `UPDATE` du seul champ `statut`. |

### `RevendicationRepository`

| Signature | Utilité |
|---|---|
| `inserer(revendication: Revendication): long` | `INSERT` dans `revendication` (statut **Soumise**). |
| `trouverParId(idRevendication: long): Revendication \| null` | `SELECT` d'une revendication. |
| `listerParProposition(idProposition: long): Revendication[]` | Fil de négociation d'une proposition. |
| `listerOuvertes(idProposition: long): Revendication[]` | Revendications **Soumise** / **En discussion**. |
| `mettreAJourReponse(idRevendication: long, idRepondant: long, reponse: string, dateReponse: datetime): void` | `UPDATE` de la réponse. |
| `mettreAJourStatut(idRevendication: long, idStatut: long): void` | `UPDATE` du seul champ `statut`. |

---

## 8. Table des transitions autorisées

| État courant | Action | État suivant |
|---|---|---|
| (aucun) | `creerDepuisCandidature` | À envoyer |
| À envoyer | `envoyer` | Envoyée |
| Envoyée | `soumettreRevendication` | En négociation |
| En négociation | `repondreRevendication` | (inchangé, ligne par ligne) |
| En négociation | `contreProposer` | Envoyée (nouveau tour) |
| En négociation | `enregistrerReponse` (accepte) | Acceptée (si aucune revendication ouverte) |
| En négociation | `enregistrerReponse` (refuse) | Refusée |
| Envoyée | `enregistrerReponse` (accepte) | Acceptée |
| Envoyée | `enregistrerReponse` (refuse) | Refusée |
| Envoyée / En négociation | `marquerExpiree` | Expirée |

États **finaux** : Acceptée, Refusée, Expirée.

### Transitions d'une revendication

| État courant | Action | État suivant |
|---|---|---|
| (aucun) | `soumettre` | Soumise |
| Soumise | `repondre` (ACCEPTEE) | Acceptée |
| Soumise | `repondre` (REFUSEE) | Refusée |
| Soumise | `repondre` (EN_DISCUSSION) | En discussion |
| En discussion | `repondre` (ACCEPTEE / REFUSEE) | Acceptée / Refusée |
| Soumise / En discussion | `retirer` | Retirée |

États **finaux** : Acceptée, Refusée, Retirée.

---

## 9. Déroulé de `creerDepuisCandidature` (pseudo-code)

```text
creerDepuisCandidature(idCandidature, remunerationProposee, dateEmbaucheProposee, utilisateur):
  exigerRole(utilisateur, [RH, recruteur, direction])
  candidature = candidatureRepo.trouverParId(idCandidature)
  si candidature est null                              → erreur OBJET_INTROUVABLE

  // RÈGLE CLÉ : seules les candidatures retenues peuvent recevoir une proposition
  si candidature.statut != RETENUE                     → erreur STATUT_INVALIDE

  offre = offreRepo.trouverParId(candidature.idOffre)
  si non grilleService.remunerationValide(offre.idPoste, remunerationProposee):
                                                         → erreur DONNEES_INCOHERENTES

  proposition = NouvelleProposition(idCandidature, remunerationProposee, dateEmbaucheProposee,
                                     A_ENVOYER, aujourd'hui)

  début transaction
      idProposition = repo.inserer(proposition)
      workflow.changerStatut(idProposition, A_ENVOYER, utilisateur, 'Création proposition')
      auditService.journaliser(utilisateur.idUtilisateur, 'CREATE',
                               'proposition_embauche', idProposition, null, adresseIp)
  fin transaction
  retourner idProposition
```

### Déroulé de `soumettreRevendication` (pseudo-code)

```text
soumettreRevendication(idProposition, revendication, auteur):
  proposition = propositionRepo.trouverParId(idProposition)
  si proposition est null                          → erreur OBJET_INTROUVABLE
  si proposition.statut != ENVOYEE
     et proposition.statut != EN_NEGOCIATION        → erreur STATUT_INVALIDE
  si proposition.statut == ACCEPTEE/REFUSEE/EXPIREE → erreur STATUT_INVALIDE

  début transaction
      idRev = revendicationRepo.inserer(Revendication(..., SOUMISE))
      workflowRev.changerStatutRevendication(idRev, SOUMISE, auteur, 'Revendication du candidat')
      si proposition.statut != EN_NEGOCIATION:
          workflow.changerStatut(idProposition, EN_NEGOCIATION, auteur,
                                 'Négociation ouverte')
      notificationService.notifierNouvelleRevendication(proposition, idRev)
      auditService.journaliser(auteur?.idUtilisateur, 'CREATE',
                               'revendication', idRev, null, adresseIp)
  fin transaction
  retourner idRev
```

### Déroulé de `repondreRevendication` (pseudo-code)

```text
repondreRevendication(idRev, decision, reponse, utilisateur):
  exigerRole(utilisateur, [RH, recruteur, direction])
  rev = revendicationRepo.trouverParId(idRev)
  si rev est null                                  → erreur OBJET_INTROUVABLE
  si rev.statut pas dans {SOUMISE, EN_DISCUSSION}  → erreur STATUT_INVALIDE

  début transaction
      revendicationRepo.mettreAJourReponse(idRev, utilisateur.idUtilisateur,
                                           reponse, maintenant)
      workflowRev.changerStatutRevendication(idRev, decision, utilisateur, reponse)

      // si toutes les revendications sont traitées, on réémet l'offre
      si revendicationRepo.listerOuvertes(rev.idProposition) est vide:
          revendicationService.appliquerAcceptees(rev.idProposition, utilisateur)
          propositionService.contreProposer(...)   // repasse la proposition à ENVOYEE
      auditService.journaliser(utilisateur.idUtilisateur, 'UPDATE',
                               'revendication', idRev, reponse, adresseIp)
  fin transaction
```

### Enchaînement à l'acceptation

```text
enregistrerReponse(idProposition, reponse, utilisateur):
  ... enregistrer la réponse, statut = ACCEPTEE
  si accepte:
      // ouvre la phase « Contrat et Onboarding » (modules suivants)
      salarieService.creerDepuisCandidature(idCandidature, utilisateur)
      contratService.creerContrat(idSalarie, candidature.idOffre, utilisateur)
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
- Le « oui / non ou autres (négations) » du candidat (`RH.md` §II.B.2) est porté par `reponse`
  (texte) et le statut (**Acceptée** / **Refusée**) ; les « autres » passent par `revendication`.
- **Négociation** : la réponse finale est bloquée tant qu'une revendication est **Soumise** ou
  **En discussion** ; chaque tour est une ligne de `revendication` (fil conservé).
- `sens` (CANDIDAT / ENTREPRISE) et les deux colonnes d'auteur (`id_candidat` / `id_utilisateur`)
  sont **exclusifs** : poser une contrainte applicative (ou un `CHECK`).
- `valeur_demandee` est un `varchar` volontairement souple (montant, date, texte) : la nature
  (`nature_id`) indique comment l'interpréter ; les montants sont revalidés par `GrilleService`.
- `date_embauche_proposee` permet de négocier la **date d'embauche** en plus de la rémunération ;
  ne pas oublier de la reporter dans le contrat à l'acceptation.
- La rémunération proposée doit rester dans la `grille_salariale` du poste ; la validation est
  faite par `GrilleService.remunerationValide` (y compris après une contre-proposition).
- Le délai d'expiration n'est pas stocké : le calculer (ex. `date_proposition + N jours`) ou
  ajouter une colonne `date_expiration`. Ne pas expirer une proposition dont la négociation est
  ouverte (ou traiter l'expiration comme un refus).

---

## 11. Ordre d'implémentation conseillé

1. `PropositionEmbaucheRepository` + `RevendicationRepository` + `WorkflowService.idStatut/designationStatut`
2. `WorkflowService.changerStatut` (+ statut revendication) + `HistoriqueRepository` + `AuditService`
3. `GrilleService.remunerationValide`
4. `PropositionEmbaucheService.creerDepuisCandidature` (contrôle statut **Retenue**)
5. `envoyer` / `enregistrerReponse` / `marquerExpiree`
6. `RevendicationService.soumettre` → proposition **En négociation**
7. `repondre` / `retirer` / `appliquerAcceptees` / `contreProposer`
8. `consulter`, `lister`, `historique` (proposition + revendications)
9. `PropositionEmbaucheController` + vues (fiche + onglet Négociation)
10. Enchaînement vers Salarié/Contrat à l'acceptation
