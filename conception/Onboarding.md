# Conception du module Onboarding — Gestion RH

Ce document décrit une conception possible du module **onboarding** (`RH.md` §II.B.3),
basée sur les tables `onboarding`, `status_onboarding`, `onboarding_tache`,
`status_onboarding_tache`, `onboarding_status_historique`, `onboarding_tache_status_historique`,
`document`, `type_document`, `salarie`, `utilisateur`, `journal_audit` et `Action`.
L'onboarding démarre à la **signature du contrat** : il organise l'intégration du nouveau
salarié (badge, compte, matériel, formation, inscription paie…) et le suivi des documents.
Les signatures utilisent une notation neutre (`nom(param: Type): TypeRetour`).

---

## 1. Rappel des tables utilisées

| Table | Colonnes utiles |
|---|---|
| `onboarding` | `id_onboarding`, `statut`, `id_salarie`, `date_debut`, `date_fin` |
| `status_onboarding` | `id`, `designation` (À démarrer, En cours, En attente, Terminé, Annulé) |
| `onboarding_tache` | `id_tache`, `statut`, `id_onboarding`, `intitule`, `description`, `responsable`, `date_echeance`, `date_realisation` |
| `status_onboarding_tache` | `id`, `designation` (À faire, En cours, Bloquée, Terminée, Annulée) |
| `onboarding_status_historique` | `id_onboarding`, `statut_ancien`, `statut_nouveau`, `date_changement`, `id_utilisateur`, `commentaire` |
| `onboarding_tache_status_historique` | `id_tache`, `statut_ancien`, `statut_nouveau`, `date_changement`, `id_utilisateur`, `commentaire` |
| `document` | `id_document`, `id_salarie`, `type_document`, `nom_fichier`, `chemin_fichier`, `date_depot` |
| `type_document` | `id`, `designation` (CV, Contrat de travail, Pièce d'identité, Diplôme, RIB…) |
| `utilisateur` / `journal_audit` / `Action` | traçabilité |

---

## 2. Architecture en couches

```
Vue (checklist d'intégration, dossier documents)
        │
Controller   ──►  OnboardingController
        │
Service      ──►  OnboardingService
        │            ├─►  WorkflowService       (transitions onboarding + tâches)
        │            ├─►  DocumentService       (pièces jointes)
        │            ├─►  NotificationService   (rappels d'échéance)
        │            └─►  AuditService          (journal_audit)
Repository   ──►  OnboardingRepository
        │            ├─►  TacheRepository
        │            ├─►  DocumentRepository
        │            └─►  HistoriqueRepository   (onboarding*_status_historique)
        │
Base de données (onboarding, onboarding_tache, document, ...)
```

**Lien amont** : `ContratService.signer` génère le passage à l'onboarding
(`OnboardingService.demarrer`). À la fin, le salarié est pleinement **Actif**.

---

## 3. Types de données (DTO / modèles)

```text
StatutOnboarding = A_DEMARRER | EN_COURS | EN_ATTENTE | TERMINE | ANNULE
StatutTache      = A_FAIRE | EN_COURS | BLOQUEE | TERMINEE | ANNULEE

Onboarding {
  idOnboarding: long
  idSalarie: long
  statut: StatutOnboarding
  dateDebut: date
  dateFin: date | null
}

TacheOnboarding {
  idTache: long
  idOnboarding: long
  intitule: string
  description: string | null
  responsable: string | null
  dateEcheance: date | null
  dateRealisation: datetime | null
  statut: StatutTache
}

NouvelleTache {
  idOnboarding: long
  intitule: string
  description: string | null
  responsable: string | null
  dateEcheance: date | null
}

Document {
  idDocument: long
  idSalarie: long
  typeDocument: long
  nomFichier: string | null
  cheminFichier: string
  dateDepot: datetime
}

FiltreOnboardings {
  statut: StatutOnboarding | null
  idSalarie: long | null
}
```

---

## 4. `OnboardingController` — point d'entrée HTTP

| Signature | Utilité |
|---|---|
| `demarrer(idSalarie: long, requete: Requete): Reponse` | Démarre l'onboarding d'un nouveau salarié. |
| `afficherFiche(idOnboarding: long, requete: Requete): Vue` | Checklist, tâches, documents et progression. |
| `creerTache(idOnboarding: long, requete: Requete): Reponse` | Ajoute une tâche d'intégration. |
| `realiserTache(idTache: long, requete: Requete): Reponse` | Marque une tâche **Terminée**. |
| `bloquerTache(idTache: long, requete: Requete): Reponse` | Marque une tâche **Bloquée**. |
| `cloturer(idOnboarding: long, requete: Requete): Reponse` | Clôture l'onboarding. |
| `ajouterDocument(idSalarie: long, requete: Requete): Reponse` | Dépose une pièce du dossier salarié. |
| `lister(requete: Requete): Vue` | Liste filtrée des onboardings. |

---

## 5. `OnboardingService` — logique métier

| Signature | Utilité |
|---|---|
| `demarrer(idSalarie: long, dateDebut: date, utilisateur: SessionUtilisateur): long` | Crée l'onboarding au statut **À démarrer** et génère les tâches par défaut. Retourne `idOnboarding`. |
| `creerTachesParDefaut(idOnboarding: long, utilisateur: SessionUtilisateur): long[]` | Crée les tâches : **contrat signé**, **badge**, **compte**, **matériel**, **formation**, **inscription paie** (`RH.md` §II.B.3). |
| `creerTache(nouvelle: NouvelleTache, utilisateur: SessionUtilisateur): long` | Ajoute une tâche personnalisée (responsable, échéance). |
| `demarrerTache(idTache: long, utilisateur: SessionUtilisateur): void` | Passe une tâche **À faire** → **En cours**. |
| `realiserTache(idTache: long, utilisateur: SessionUtilisateur): void` | Passe une tâche → **Terminée**, renseigne `date_realisation`. |
| `bloquerTache(idTache: long, motif: string, utilisateur: SessionUtilisateur): void` | Passe une tâche → **Bloquée**. |
| `annulerTache(idTache: long, utilisateur: SessionUtilisateur): void` | Passe une tâche → **Annulée**. |
| `progression(idOnboarding: long): decimal` | Pourcentage de tâches terminées (pilotage). |
| `cloturer(idOnboarding: long, utilisateur: SessionUtilisateur): void` | Passe l'onboarding → **Terminé** (toutes les tâches terminées), renseigne `date_fin` ; confirme le salarié **Actif** si essai conclu. |
| `annuler(idOnboarding: long, motif: string, utilisateur: SessionUtilisateur): void` | Passe l'onboarding → **Annulé** (départ anticipé). |
| `consulter(idOnboarding: long): Onboarding \| null` | Détail d'un onboarding. |
| `lister(filtre: FiltreOnboardings): Onboarding[]` | Liste filtrée. |
| `historique(idOnboarding: long): LigneHistorique[]` | Journal des statuts (onboarding et tâches). |

### `DocumentService` — dossier documentaire

| Signature | Utilité |
|---|---|
| `ajouter(idSalarie: long, typeDocument: long, nomFichier: string, cheminFichier: string, utilisateur: SessionUtilisateur): long` | `INSERT` dans `document` (pièce du dossier). |
| `lister(idSalarie: long): Document[]` | Documents d'un salarié. |
| `supprimer(idDocument: long, utilisateur: SessionUtilisateur): void` | Retire un document (logique). |

---

## 6. `WorkflowService` — transitions

### Onboarding

| État courant | Action | État suivant |
|---|---|---|
| (aucun) | `demarrer` | À démarrer |
| À démarrer | (début effectif) | En cours |
| En cours | `mettreEnAttente` | En attente |
| En attente | (reprise) | En cours |
| En cours | `cloturer` | Terminé |
| Tout état non final | `annuler` | Annulé |

### Tâche d'onboarding

| État courant | Action | État suivant |
|---|---|---|
| (aucun) | `creerTache` | À faire |
| À faire | `demarrerTache` | En cours |
| En cours | `realiserTache` | Terminée |
| En cours / À faire | `bloquerTache` | Bloquée |
| Bloquée | (débloquer) | En cours |
| Tout état non final | `annulerTache` | Annulée |

États **finaux** onboarding : Terminé, Annulé. Tâche : Terminée, Annulée.

---

## 7. Déroulé de `realiserTache` (pseudo-code)

```text
realiserTache(idTache, utilisateur):
  tache = tacheRepo.trouverParId(idTache)
  si tache est null                                → erreur OBJET_INTROUVABLE
  si tache.statut pas dans {A_FAIRE, EN_COURS}     → erreur STATUT_INVALIDE

  début transaction
      tacheRepo.mettreAJourRealisation(idTache, maintenant)
      workflowTache.changerStatut(idTache, TERMINEE, utilisateur, 'Tâche réalisée')
      si progression(tache.idOnboarding) == 100:
          workflow.changerStatut(tache.idOnboarding, TERMINE, utilisateur, 'Intégration terminée')
      auditService.journaliser(utilisateur.idUtilisateur, 'UPDATE',
                               'onboarding_tache', idTache, null, adresseIp)
  fin transaction
```

---

## 8. Points d'attention sur le modèle

- Les contraintes d'unicité `onboarding.id_salarie` et `onboarding.date_debut` ont été
  **retirées** : garantir « un onboarding actif par salarié » dans `OnboardingService` si besoin.
- `onboarding_tache.responsable` est un `varchar` : y stocker le nom du responsable (ou un
  identifiant utilisateur si l'on veut un lien fort).
- `document.id_salarie` rattache les pièces au **salarié** (pas au candidat) : les CV de
  candidature relèvent du module Candidature.
- `type_document` sert de catalogue (Contrat de travail, Pièce d'identité, Diplôme, RIB…) :
  prévoir un `SELECT id FROM type_document WHERE designation = ?` (attention à la casse des
  libellés historiques).
- Statuts entiers (FK vers `status_onboarding.id` / `status_onboarding_tache.id`) : prévoir les
  mappings désignation ↔ id.
- L'onboarding peut être **automatique** suite à la signature du contrat, mais reste **modifiable** :
  la progression conditionne la clôture.

---

## 9. Ordre d'implémentation conseillé

1. `OnboardingRepository` + `TacheRepository` + `WorkflowService` (idStatut/designation/changer)
2. `HistoriqueRepository` (onboarding + tâches) + `AuditService`
3. `OnboardingService.demarrer` + `creerTachesParDefaut`
4. `creerTache`, `demarrerTache`, `realiserTache`, `bloquerTache`, `annulerTache`
5. `progression`, `cloturer`, `annuler`
6. `DocumentService.ajouter` / `lister`
7. `OnboardingController` + vues (checklist, dossier)
8. Branchement depuis `ContratService.signer`
