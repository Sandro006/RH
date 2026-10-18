# Conception du module Candidature — Gestion RH

Ce document décrit une conception possible du module **candidature** (`RH.md` §II.B.1),
basée sur les tables `candidat`, `candidature`, `status_candidature`, `offre_emploi`,
`candidature_status_historique`, `salarie`, `document`, `utilisateur`, `journal_audit` et `Action`.
Il couvre le dépôt d'une candidature sur une offre **publiée**, la gestion du candidat et
le suivi du cycle de vie de la candidature.
Les signatures utilisent une notation neutre (`nom(param: Type): TypeRetour`).

---

## 1. Rappel des tables utilisées

| Table | Colonnes utiles |
|---|---|
| `candidat` | `id_candidat`, `nom`, `prenom`, `email` (UNIQUE), `telephone`, `adresse`, `date_creation` |
| `candidature` | `id_candidature`, `id_candidat`, `id_offre`, `date_candidature`, `cv`, `lettre_motivation`, `origine_candidature`, `statut` |
| `status_candidature` | `id`, `designation` (Reçue, En présélection, Présélectionnée, Entretien prévu, En évaluation, Retenue, Refusée, Retirée) |
| `offre_emploi` / `status_offre_emploi` | statut de l'offre (seules les offres **Publiées** acceptent des candidatures) |
| `candidature_status_historique` | `id_candidature`, `statut_ancien`, `statut_nouveau`, `date_changement`, `id_utilisateur`, `commentaire` |
| `salarie` | `id_salarie`, `matricule`, `date_embauche` (mobilité interne) |
| `document` | pièces jointes (CV, lettre) éventuellement rattachées |
| `utilisateur` / `journal_audit` / `Action` | traçabilité |

---

## 2. Architecture en couches

```
Vue (formulaire de candidature publique / interne, suivi)
        │
Controller   ──►  CandidatureController
        │
Service      ──►  CandidatureService
        │            ├─►  WorkflowService          (transitions de statut)
        │            ├─►  EligibiliteService       (mobilité interne)
        │            ├─►  NotificationService      (accusé de réception, RH)
        │            └─►  AuditService             (journal_audit)
Repository   ──►  CandidatureRepository
        │            ├─►  CandidatRepository
        │            └─►  HistoriqueRepository      (candidature_status_historique)
        │
Base de données (candidat, candidature, ...)
```

**Lien amont** : le module Offre d'emploi expose `offresOuvertes()` ; une candidature n'est
acceptée que si l'offre est **Publiée**.

---

## 3. Types de données (DTO / modèles)

```text
StatutCandidature = RECUE | EN_PRESELECTION | PRESELECTIONNEE | ENTRETIEN_PREVU
                  | EN_EVALUATION | RETENUE | REFUSEE | RETIREE

OrigineCandidature = EXTERNE | INTERNE | MOBILITE | RECOMMANDATION

Candidat {
  idCandidat: long
  nom: string
  prenom: string
  email: string
  telephone: string | null
  adresse: string | null
  dateCreation: datetime
}

Candidature {
  idCandidature: long
  idCandidat: long
  idOffre: long
  dateCandidature: datetime
  cv: string | null
  lettreMotivation: string | null
  origine: OrigineCandidature
  statut: StatutCandidature
}

NouvelleCandidature {          // saisie par le candidat
  idOffre: long
  nom: string
  prenom: string
  email: string
  telephone: string | null
  adresse: string | null
  cv: string | null             // chemin/pièce jointe
  lettreMotivation: string | null
  origine: OrigineCandidature
}

ResultatEligibilite {
  eligible: boolean
  motifs: string[]
}

FiltreCandidatures {
  idOffre: long | null
  statut: StatutCandidature | null
  origine: OrigineCandidature | null
}
```

---

## 4. `CandidatureController` — point d'entrée HTTP

| Signature | Utilité |
|---|---|
| `afficherFormulaireCandidature(idOffre: long, requete: Requete): Vue` | Affiche le formulaire de candidature pour une offre publiée. |
| `postuler(idOffre: long, requete: Requete): Reponse` | Récupère les données saisies, appelle `CandidatureService.postuler`, affiche une confirmation. |
| `afficherFiche(idCandidature: long, requete: Requete): Vue` | Détail d'une candidature (profil candidat, statut, historique). |
| `listerParOffre(idOffre: long, requete: Requete): Vue` | Liste des candidatures d'une offre (RH/recruteur). |
| `mettreEnPreselection(idCandidature: long, requete: Requete): Reponse` | Démarre la présélection. |
| `preselectionner(idCandidature: long, requete: Requete): Reponse` | Retient le candidat pour entretien. |
| `planifierEntretien(idCandidature: long, requete: Requete): Reponse` | Passe la candidature à **Entretien prévu**. |
| `marquerEvaluee(idCandidature: long, requete: Requete): Reponse` | Passe à **En évaluation**. |
| `retenir(idCandidature: long, requete: Requete): Reponse` | Marque la candidature **Retenue**. |
| `refuser(idCandidature: long, requete: Requete): Reponse` | Marque la candidature **Refusée**. |
| `retirer(idCandidature: long, requete: Requete): Reponse` | Le candidat retire sa candidature (**Retirée**). |

---

## 5. `CandidatureService` — logique métier

| Signature | Utilité |
|---|---|
| `postuler(nouvelle: NouvelleCandidature): long` | Cœur du module : vérifie que l'offre est publiée, retrouve ou crée le candidat, crée la candidature au statut **Reçue**, journalise. Retourne `idCandidature`. |
| `trouverOuCreerCandidat(donnees: NouvelleCandidature): long` | Recherche le candidat par `email` (UNIQUE) ; le crée s'il n'existe pas. |
| `retirer(idCandidature: long, utilisateur: SessionUtilisateur \| null): void` | Passe la candidature à **Retirée** (par le candidat ou la RH). |
| `mettreEnPreselection(idCandidature: long, utilisateur: SessionUtilisateur): void` | Passe de **Reçue** à **En présélection**. |
| `preselectionner(idCandidature: long, utilisateur: SessionUtilisateur): void` | Passe à **Présélectionnée** (ou **Refusée** si non retenue). |
| `planifierEntretien(idCandidature: long, utilisateur: SessionUtilisateur): void` | Passe à **Entretien prévu** (déclenche le module Entretien). |
| `marquerEvaluee(idCandidature: long, utilisateur: SessionUtilisateur): void` | Passe à **En évaluation** après l'entretien. |
| `retenir(idCandidature: long, utilisateur: SessionUtilisateur): void` | Passe à **Retenue** (candidat proposé à l'embauche). |
| `refuser(idCandidature: long, motif: string \| null, utilisateur: SessionUtilisateur): void` | Passe à **Refusée** (la candidature est **conservée**, `RH.md` §II.B.1). |
| `consulter(idCandidature: long): Candidature \| null` | Détail d'une candidature. |
| `lister(filtre: FiltreCandidatures): Candidature[]` | Liste filtrée (par offre, statut, origine). |
| `historique(idCandidature: long): LigneHistorique[]` | Journal des changements de statut. |
| `verifierEligibiliteMobilite(idSalarie: long, idPoste: long): ResultatEligibilite` | Mobilité interne : ancienneté minimale, compétences transférables, absence de contraintes (`RH.md` §II.A.7). |

---

## 6. `WorkflowService` — transitions de statut

| Signature | Utilité |
|---|---|
| `idStatut(designation: StatutCandidature): long` | Résout l'`id` dans `status_candidature`. |
| `designationStatut(idStatut: long): StatutCandidature` | Opération inverse. |
| `transitionAutorisee(statutActuel: StatutCandidature, statutCible: StatutCandidature): boolean` | Valide la table des transitions (§8). |
| `estFinal(statut: StatutCandidature): boolean` | `true` pour **Retenue**, **Refusée**, **Retirée**. |
| `changerStatut(idCandidature: long, statutCible: StatutCandidature, utilisateur: SessionUtilisateur \| null, commentaire: string \| null): void` | Met à jour `candidature.statut`, écrit dans `candidature_status_historique` et l'audit. |

---

## 7. Repositories

### `CandidatRepository`

| Signature | Utilité |
|---|---|
| `trouverParEmail(email: string): Candidat \| null` | `SELECT` par `email` (UNIQUE). |
| `trouverParId(idCandidat: long): Candidat \| null` | `SELECT` d'un candidat. |
| `inserer(candidat: Candidat): long` | `INSERT` d'un nouveau candidat. |
| `mettreAJour(candidat: Candidat): void` | `UPDATE` des informations du candidat. |

### `CandidatureRepository`

| Signature | Utilité |
|---|---|
| `inserer(candidature: Candidature): long` | `INSERT` dans `candidature` (statut **Reçue**). |
| `trouverParId(idCandidature: long): Candidature \| null` | `SELECT` d'une candidature. |
| `existeDeja(idCandidat: long, idOffre: long): boolean` | Contrôle anti-doublon (une candidature par candidat et par offre). |
| `lister(filtre: FiltreCandidatures): Candidature[]` | `SELECT` filtré. |
| `mettreAJourStatut(idCandidature: long, idStatut: long): void` | `UPDATE` du seul champ `statut`. |

### `HistoriqueRepository`

| Signature | Utilité |
|---|---|
| `enregistrer(idCandidature: long, statutAncien: StatutCandidature \| null, statutNouveau: StatutCandidature, idUtilisateur: long \| null, commentaire: string \| null): void` | `INSERT` dans `candidature_status_historique`. |
| `listerParCandidature(idCandidature: long): LigneHistorique[]` | `SELECT` ordonné par `date_changement`. |

---

## 8. Table des transitions autorisées

| État courant | Action | État suivant |
|---|---|---|
| (aucun) | `postuler` | Reçue |
| Reçue | `mettreEnPreselection` | En présélection |
| Reçue | `refuser` | Refusée |
| En présélection | `preselectionner` | Présélectionnée |
| En présélection | `refuser` | Refusée |
| Présélectionnée | `planifierEntretien` | Entretien prévu |
| Présélectionnée | `refuser` | Refusée |
| Entretien prévu | `marquerEvaluee` | En évaluation |
| Entretien prévu | `refuser` | Refusée |
| En évaluation | `retenir` | Retenue |
| En évaluation | `refuser` | Refusée |
| Tout état non final | `retirer` | Retirée |

États **finaux** : Retenue, Refusée, Retirée.

---

## 9. Déroulé de `postuler` (pseudo-code)

```text
postuler(nouvelle):
  offre = offreRepo.trouverParId(nouvelle.idOffre)
  si offre est null                                → erreur OBJET_INTROUVABLE
  si non workflowOffre.estOuverte(offre.statut)    → erreur STATUT_INVALIDE  // offre non publiée
  si nouvelle.email est vide ou invalide           → erreur DONNEES_INCOHERENTES
  si !isMentionLegaleAcceptee(nouvelle)            → erreur DONNEES_INCOHERENTES

  début transaction
      idCandidat = trouverOuCreerCandidat(nouvelle)
      si candidatureRepo.existeDeja(idCandidat, nouvelle.idOffre) → erreur CONFLIT
      if nouvelle.origine == MOBILITE:
          elig = verifierEligibiliteMobilite(nouvelle.idSalarie, offre.idPoste)
          si non elig.eligible                     → erreur DONNEES_INCOHERENTES
      idCandidature = candidatureRepo.inserer(candidature(idCandidat, nouvelle.idOffre, RECUE, ...))
      workflow.changerStatut(idCandidature, RECUE, utilisateur, 'Dépôt de candidature')
      notificationService.accuserReception(nouvelle.email, offre)
      auditService.journaliser(utilisateur?.idUtilisateur, 'CREATE',
                               'candidature', idCandidature, null, adresseIp)
  fin transaction
  retourner idCandidature
```

Remarque : `BD` pas de contrainte d'unicité `(id_candidat, id_offre)` (elle a été retirée du
modèle) ; le contrôle anti-doublon est donc porté par `CandidatureRepository.existeDeja`.

---

## 10. Points d'attention sur le modèle

- `candidat.email` est **UNIQUE** : un même candidat réutilise sa fiche (d'où
  `trouverOuCreerCandidat`).
- Conserver les candidats non retenus : ne **jamais** supprimer une candidature, utiliser le
  statut **Refusée** (`RH.md` §II.B.1).
- `cv` et `lettre_motivation` sont des `varchar(255)` : y stocker un **chemin/identifiant de
  pièce jointe** ; les fichiers eux-mêmes sont gérés par le module `document`.
- `origine_candidature` est un texte libre : fixer la liste `EXTERNE | INTERNE | MOBILITE |
  RECOMMANDATION` côté application.
- La sélection ne doit **pas** se faire selon le statut du candidat mais selon les critères
  (`RH.md` §II.B.1) : pas de filtre automatique par statut.
- `statut` est un entier (FK vers `status_candidature.id`) : prévoir le mapping désignation ↔ id.
- Liens vers les modules suivants : `entretien` (statut **Entretien prévu**) et
  `evaluation_candidat` (statut **En évaluation**) ; `proposition_embauche` à la suite de
  **Retenue**.

---

## 11. Ordre d'implémentation conseillé

1. `CandidatRepository` (`trouverOuCreerCandidat`) + `CandidatureRepository`
2. `WorkflowService.idStatut/designationStatut/changerStatut` + `HistoriqueRepository`
3. `CandidatureService.postuler` (validation de l'offre + anti-doublon)
4. `consulter`, `lister`, `historique`
5. `mettreEnPreselection`, `preselectionner`, `refuser`, `retirer`
6. `planifierEntretien`, `marquerEvaluee`, `retenir` (liens entretien / évaluation)
7. `EligibiliteService.verifierEligibiliteMobilite`
8. `CandidatureController` + vues (formulaire public + suivi RH)
