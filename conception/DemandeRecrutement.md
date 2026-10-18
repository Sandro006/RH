# Conception du module Demande de recrutement — Gestion RH

Ce document décrit une conception possible du module **demande de recrutement** (workflow
de validation du recrutement, `RH.md` §II.A), basée sur les tables `demande_recrutement`,
`validation`, `poste`, `service`, `departement`, `grille_salariale`, `status`,
`demande_recrutement_status_historique`, `Employe`, `utilisateur`, `journal_audit` et `Action`.
Les signatures sont écrites dans une notation neutre (`nom(param: Type): TypeRetour`) : elles se
traduisent directement en PHP, Java, Python, etc.

---

## 1. Rappel des tables utilisées

| Table | Colonnes utiles pour la demande de recrutement |
|---|---|
| `demande_recrutement` | `id_demande`, `id_poste`, `id_demandeur`, `motif`, `id_profil`, `nom_personne_remplacee`, `type_contrat`, `duree_contrat`, `missions`, `date_souhaitee`, `remuneration_min`, `remuneration_max`, `statut`, `date_creation` |
| `status` | `id`, `designation` (En cours, En contrôle RH, En validation budgétaire, En validation hiérarchique, En consultation interne, Validée, Refusée, À corriger, Annulée) |
| `poste` | `id_poste`, `id_service`, `intitule`, `rang_id` |
| `service` | `id_service`, `id_departement`, `nom` |
| `departement` | `id_departement`, `nom` |
| `grille_salariale` | `id_grille`, `id_poste`, `rang`, `salaire_min`, `salaire_max`, `date_debut`, `date_fin` |
| `validation` | `id_validation`, `id_demande`, `id_validateur`, `statut_id`, `date_validation`, `commentaire` |
| `demande_recrutement_status_historique` | `id_demande`, `statut_ancien`, `statut_nouveau`, `date_changement`, `id_utilisateur`, `commentaire` |
| `Employe` | `id`, `Nom`, `poste_id` (le demandeur et les valideurs) |
| `utilisateur` | `id_utilisateur`, `role` (auteur des actions, pour l'audit) |
| `journal_audit` / `Action` | traçabilité des actions (`CREATE`, `UPDATE`, `VALIDATION`, `REFUS`…) |

---

## 2. Architecture en couches

```
Vue (formulaires : création, fiche, validation)
        │
Controller   ──►  DemandeRecrutementController
        │
Service      ──►  DemandeRecrutementService
        │            ├─►  CoherenceService       (règles métier / contrôle RH)
        │            ├─►  WorkflowService        (transitions de statut)
        │            ├─►  NotificationService     (alertes valideurs)
        │            └─►  AuditService            (journal_audit)
Repository   ──►  DemandeRecrutementRepository
        │            ├─►  ValidationRepository
        │            └─►  HistoriqueRepository
        │
Base de données (demande_recrutement, validation, ...)
```

Chaque couche a une seule responsabilité : le contrôleur gère HTTP, le service porte la
logique métier et l'orchestration du workflow, les repositories ne font que des requêtes SQL.

---

## 3. Types de données (DTO / modèles)

```text
StatutDemande = EN_COURS | CONTROLE_RH | VALIDATION_BUDGET | VALIDATION_HIERARCHIE
              | CONSULTATION_INTERNE | VALIDEE | REFUSEE | A_CORRIGER | ANNULEE

MotifDemande = CREATION | REMPLACEMENT | RENFORT | SAISONNIER

DecisionValidation = VALIDER | CORRIGER | REFUSER | FEU_VERT

DemandeRecrutement {
  idDemande: long
  idPoste: long
  idDemandeur: long            // Employe.id
  motif: MotifDemande
  idProfil: int | null
  nomPersonneRemplacee: string | null
  typeContrat: long            // Type_contrat.id
  dureeContrat: string | null
  missions: string
  dateSouhaitee: date
  remunerationMin: decimal | null
  remunerationMax: decimal | null
  statut: StatutDemande
  dateCreation: datetime
}

NouvelleDemande {              // données saisies par le manager
  idPoste: long
  idDemandeur: long
  motif: MotifDemande
  idProfil: int | null
  nomPersonneRemplacee: string | null
  typeContrat: long
  dureeContrat: string | null
  missions: string
  dateSouhaitee: date
  remunerationMin: decimal | null
  remunerationMax: decimal | null
}

LigneAnomalie {                // résultat du contrôle de cohérence
  regle: string
  conforme: boolean
  message: string
}

ResultatCoherence {
  conforme: boolean
  anomalies: LigneAnomalie[]
}

LigneValidation {
  idValidation: long
  idValidateur: long
  decision: DecisionValidation
  statutResultat: StatutDemande
  dateValidation: datetime
  commentaire: string | null
}

LigneHistorique {
  statutAncien: StatutDemande | null
  statutNouveau: StatutDemande
  dateChangement: datetime
  idUtilisateur: long
  commentaire: string | null
}

FiltreDemandes {
  statut: StatutDemande | null
  idDepartement: long | null
  idPoste: long | null
  idDemandeur: long | null
}
```

---

## 4. `DemandeRecrutementController` — point d'entrée HTTP

| Signature | Utilité |
|---|---|
| `afficherFormulaireCreation(requete: Requete): Vue` | Affiche le formulaire de nouvelle demande (postes, types de contrat). |
| `creer(requete: Requete): Reponse` | Récupère les champs saisis, appelle `DemandeRecrutementService.creer`, redirige vers la fiche de la demande. |
| `afficherFiche(idDemande: long, requete: Requete): Vue` | Affiche le détail d'une demande et les actions possibles selon son statut et le rôle. |
| `modifier(idDemande: long, requete: Requete): Reponse` | Enregistre les modifications d'une demande **En cours** / **À corriger**. |
| `soumettre(idDemande: long, requete: Requete): Reponse` | Le manager soumet la demande au contrôle RH. |
| `controlerRh(idDemande: long, requete: Requete): Reponse` | Enregistre la décision du contrôleur RH. |
| `validerBudget(idDemande: long, requete: Requete): Reponse` | Enregistre la décision budgétaire. |
| `validerHierarchie(idDemande: long, requete: Requete): Reponse` | Enregistre la décision hiérarchique (ou déclenche la consultation interne). |
| `demanderConsultation(idDemande: long, requete: Requete): Reponse` | Lance la consultation interne éventuelle. |
| `cloturerConsultation(idDemande: long, requete: Requete): Reponse` | Clôture la consultation interne. |
| `feuVert(idDemande: long, requete: Requete): Reponse` | Finalise le recrutement (passage à l'annonce). |
| `annuler(idDemande: long, requete: Requete): Reponse` | Annule la demande. |
| `lister(requete: Requete): Vue` | Liste filtrée des demandes (tableau de bord). |
| `afficherHistorique(idDemande: long, requete: Requete): Vue` | Affiche le journal des changements de statut. |

---

## 5. `DemandeRecrutementService` — logique métier

| Signature | Utilité |
|---|---|
| `creer(nouvelle: NouvelleDemande, utilisateur: SessionUtilisateur): long` | Crée la demande au statut **En cours**, vérifie les préconditions, écrit la première ligne d'historique et l'audit. Retourne `idDemande`. |
| `modifier(idDemande: long, modifications: NouvelleDemande, utilisateur: SessionUtilisateur): void` | Met à jour une demande **En cours** / **À corriger** (auteur uniquement) ; journalise ancienne/nouvelle valeur. |
| `soumettre(idDemande: long, utilisateur: SessionUtilisateur): void` | Passe la demande de **En cours** / **À corriger** à **En contrôle RH** ; notifie le contrôleur RH. |
| `controlerRh(idDemande: long, decision: DecisionValidation, commentaire: string, validateur: long, utilisateur: SessionUtilisateur): void` | Applique la décision RH : **En validation budgétaire** (VALIDER), **À corriger** (CORRIGER) ou **Refusée** (REFUSER) ; enregistre dans `validation`. |
| `validerBudget(idDemande: long, montantApprouve: decimal \| null, decision: DecisionValidation, commentaire: string, validateur: long, utilisateur: SessionUtilisateur): void` | Applique la décision budgétaire : **En validation hiérarchique** / **À corriger** / **Refusée**. |
| `validerHierarchie(idDemande: long, decision: DecisionValidation, consultation: boolean, commentaire: string, validateur: long, utilisateur: SessionUtilisateur): void` | Décision DS/DG : **Validée** (FEU_VERT), **En consultation interne** (VALIDER + consultation), **À corriger** ou **Refusée**. |
| `demanderConsultation(idDemande: long, delai: interval \| null, utilisateur: SessionUtilisateur): void` | Passe la demande à **En consultation interne** et diffuse l'information en interne. |
| `cloturerConsultation(idDemande: long, decision: DecisionValidation, commentaire: string, validateur: long, utilisateur: SessionUtilisateur): void` | Clôture la consultation : **Validée** (VALIDER) ou **Refusée** (REFUSER). |
| `donnerFeuVert(idDemande: long, canalDiffusion: string \| null, utilisateur: SessionUtilisateur): void` | Finalise la demande validée et prépare l'annonce (entrée du module Offre d'emploi). |
| `annuler(idDemande: long, motif: string \| null, utilisateur: SessionUtilisateur): void` | Passe la demande à **Annulée** si elle n'est pas dans un état final. |
| `verifierCoherence(idDemande: long): ResultatCoherence` | Délègue à `CoherenceService` ; lecture seule. |
| `lister(filtre: FiltreDemandes): DemandeRecrutement[]` | Liste filtrée pour les tableaux de bord. |
| `consulter(idDemande: long): DemandeRecrutement \| null` | Détail d'une demande. |
| `historique(idDemande: long): LigneHistorique[]` | Journal des changements de statut. |
| `demandesEnAttente(role: string): DemandeRecrutement[]` | File d'attente par rôle (RH, budget, hiérarchie). |

---

## 6. `CoherenceService` — contrôle de cohérence (RH)

| Signature | Utilité |
|---|---|
| `verifierPosteExiste(idPoste: long): boolean` | Vérifie que le poste figure dans l'organigramme (et est rattaché à un service). |
| `verifierRemunerationDansGrille(idPoste: long, min: decimal, max: decimal): boolean` | Vérifie que la fourchette est incluse dans `grille_salariale` du poste (équité interne). |
| `verifierContratConformeMotif(typeContrat: long, motif: MotifDemande, duree: string \| null): boolean` | Contrôle le cadre légal (CDD réservé au remplacement / accroissement / saisonnier ; durée maximale respectée). |
| `resolutionInternePossible(idPoste: long): boolean` | Indique si une mobilité interne peut éviter l'ouverture externe. |
| `verifierCoherence(idDemande: long): ResultatCoherence` | Agrège toutes les règles et renvoie le détail des anomalies. |

---

## 7. `WorkflowService` — transitions de statut

| Signature | Utilité |
|---|---|
| `idStatut(designation: StatutDemande): long` | Résout l'`id` d'un statut depuis le référentiel `status` par sa désignation. |
| `designationStatut(idStatut: long): StatutDemande` | Opération inverse. |
| `transitionAutorisee(statutActuel: StatutDemande, statutCible: StatutDemande): boolean` | Valide la table de transitions (voir §11). |
| `estFinal(statut: StatutDemande): boolean` | Indique si le statut est final (**Validée**, **Refusée**, **Annulée**). |
| `changerStatut(idDemande: long, statutCible: StatutDemande, utilisateur: SessionUtilisateur, commentaire: string \| null): void` | Point unique de mise à jour du statut : contrôle la transition, met à jour `demande_recrutement.statut`, écrit l'historique et l'audit. |

---

## 8. `DemandeRecrutementRepository` — accès aux données

| Signature | Utilité |
|---|---|
| `inserer(demande: DemandeRecrutement): long` | `INSERT` dans `demande_recrutement` (statut initial), retourne `id_demande`. |
| `mettreAJour(demande: DemandeRecrutement): void` | `UPDATE` des champs modifiables (requête préparée). |
| `trouverParId(idDemande: long): DemandeRecrutement \| null` | `SELECT` d'une demande par son identifiant. |
| `lister(filtre: FiltreDemandes): DemandeRecrutement[]` | `SELECT` filtré (statut, département, poste, demandeur). |
| `mettreAJourStatut(idDemande: long, idStatut: long): void` | `UPDATE` du seul champ `statut`. |

---

## 9. `ValidationRepository` — décisions de validation

| Signature | Utilité |
|---|---|
| `enregistrer(idDemande: long, idValidateur: long, statutResultat: StatutDemande, commentaire: string \| null): long` | `INSERT` dans `validation` (`statut_id` = statut résultant), retourne `id_validation`. |
| `listerParDemande(idDemande: long): LigneValidation[]` | Historique des validations d'une demande. |

---

## 10. `HistoriqueRepository` — journal des statuts

| Signature | Utilité |
|---|---|
| `enregistrer(idDemande: long, statutAncien: StatutDemande \| null, statutNouveau: StatutDemande, idUtilisateur: long, commentaire: string \| null): void` | `INSERT` dans `demande_recrutement_status_historique`. |
| `listerParDemande(idDemande: long): LigneHistorique[]` | `SELECT` ordonné par `date_changement`. |

---

## 11. Table des transitions autorisées

| État courant | Action | État suivant |
|---|---|---|
| (aucun) | `creer` | En cours |
| En cours / À corriger | `modifier` | (inchangé) |
| En cours / À corriger | `soumettre` | En contrôle RH |
| En contrôle RH | `controlerRh` (VALIDER) | En validation budgétaire |
| En contrôle RH | `controlerRh` (CORRIGER) | À corriger |
| En contrôle RH | `controlerRh` (REFUSER) | Refusée |
| En validation budgétaire | `validerBudget` (VALIDER) | En validation hiérarchique |
| En validation budgétaire | `validerBudget` (CORRIGER / REFUSER) | À corriger / Refusée |
| En validation hiérarchique | `validerHierarchie` (FEU_VERT) | Validée |
| En validation hiérarchique | `validerHierarchie` (VALIDER + consultation) | En consultation interne |
| En validation hiérarchique | `validerHierarchie` (CORRIGER / REFUSER) | À corriger / Refusée |
| En consultation interne | `cloturerConsultation` (VALIDER / REFUSER) | Validée / Refusée |
| Tout état non final | `annuler` | Annulée |

États **finaux** : Validée, Refusée, Annulée.

---

## 12. Contrôle d'accès

| Signature | Utilité |
|---|---|
| `exigerConnexion(requete: Requete): SessionUtilisateur` | Refuse l'accès si aucune session valide (délègue à `AuthService`). |
| `exigerRole(requete: Requete, roles: string[]): void` | Contrôle d'habilitation par rôle avant chaque action (voir matrice du module Login). |

Rôles attendus : `MANAGER` (créer/soumettre/annuler), `RH` (contrôle, feu vert),
`CONTROLE_GESTION` (budget), `DIRECTION` (hiérarchie), `recruteur` (lecture).

---

## 13. Déroulé de `controlerRh` (pseudo-code)

```text
controlerRh(idDemande, decision, commentaire, validateur, utilisateur):
  demande = repo.trouverParId(idDemande)
  si demande est null                              → erreur OBJET_INTROUVABLE
  exigerRole(utilisateur, [RH])
  si demande.statut != CONTROLE_RH                 → erreur STATUT_INVALIDE

  si decision == VALIDER:
      coherence = coherenceService.verifierCoherence(idDemande)
      si non coherence.conforme                    → erreur DONNEES_INCOHERENTES
      statutCible = VALIDATION_BUDGET
  sinon si decision == CORRIGER: statutCible = A_CORRIGER
  sinon si decision == REFUSER:  statutCible = REFUSEE
  sinon                                          → erreur DONNEES_INCOHERENTES

  début transaction
      validationRepo.enregistrer(idDemande, validateur, statutCible, commentaire)
      workflowService.changerStatut(idDemande, statutCible, utilisateur, commentaire)
      notificationService.notifierEtapeSuivante(idDemande, statutCible)
      auditService.journaliser(utilisateur.idUtilisateur, 'VALIDATION',
                               'demande_recrutement', idDemande, commentaire, adresseIp)
  fin transaction
```

---

## 14. Points d'attention sur le modèle

- `demande_recrutement.statut` est un **entier** (FK vers `status.id`) : prévoir un mapping
  désignation ↔ id (rôle de `WorkflowService`), ou charger le référentiel `status` au démarrage.
- `id_demandeur` et `id_validateur` référencent `Employe.id`, alors que l'audit et
  l'historique utilisent `utilisateur.id_utilisateur` : **deux espaces d'identité** à relier
  (`utilisateur.id_employe`).
- `validation` enregistre une ligne par décision ; sa colonne `statut_id` porte le **statut
  résultant** de la décision (et non le statut courant).
- `grille_salariale` est liée au poste et à un rang : préciser lequel sert de référence pour
  le contrôle de rémunération (poste par défaut, ou rang du poste).
- Le modèle n'a **pas de table de notifications** : `NotificationService` est à implémenter
  côté application (ou ajouter une table `notification`).
- Aucune table dédiée à la **consultation interne** : l'état est porté par `status`, et le délai
  de priorité interne doit être stocké (ajouter une colonne ou une table si nécessaire).
- Les transitions ne sont **pas garanties** par la base : les faire respecter dans
  `WorkflowService` (voire un trigger `BEFORE UPDATE` sur `demande_recrutement.statut`).

---

## 15. Ordre d'implémentation conseillé

1. `DemandeRecrutementRepository` + `WorkflowService.idStatut/designationStatut`
2. `WorkflowService.changerStatut` (+ `HistoriqueRepository`) et `AuditService`
3. `DemandeRecrutementService.creer` puis `modifier` / `consulter` / `lister`
4. `CoherenceService.verifierCoherence`
5. `soumettre` puis `controlerRh`
6. `validerBudget`, `validerHierarchie`
7. `demanderConsultation` / `cloturerConsultation`, `feuVert`, `annuler`
8. `DemandeRecrutementController` + vues
9. Contrôle d'accès (`exigerRole`) et notifications
