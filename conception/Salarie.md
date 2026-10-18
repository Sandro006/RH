# Conception du module Salarié — Gestion RH

Ce document décrit une conception possible du module **salarié** (`RH.md` §II.B.2/§II.B.3),
basée sur les tables `salarie`, `status_salarie`, `salarie_status_historique`, `candidature`,
`proposition_embauche`, `affectation`, `poste`, `utilisateur`, `journal_audit` et `Action`.
Un salarié est créé lorsqu'un candidat **accepte** une proposition d'embauche.
Les signatures utilisent une notation neutre (`nom(param: Type): TypeRetour`).

---

## 1. Rappel des tables utilisées

| Table | Colonnes utiles |
|---|---|
| `salarie` | `id_salarie`, `id_candidat`, `matricule` (UNIQUE), `nom`, `prenom`, `email`, `telephone`, `date_embauche`, `statut` |
| `status_salarie` | `id`, `designation` (En attente d'embauche, Actif, En période d'essai, Suspendu, Parti) |
| `salarie_status_historique` | `id_salarie`, `statut_ancien`, `statut_nouveau`, `date_changement`, `id_utilisateur`, `commentaire` |
| `candidature` | `id_candidature`, `id_candidat`, `id_offre`, `statut` (Retenue) |
| `proposition_embauche` | `id_proposition`, `id_candidature`, `statut` (Acceptée) |
| `affectation` | `id_affectation`, `id_salarie`, `id_poste`, `date_debut`, `date_fin`, `motif` |
| `poste` | `id_poste`, `intitule`, `id_service`, `rang_id` |
| `utilisateur` / `journal_audit` / `Action` | traçabilité |

---

## 2. Architecture en couches

```
Vue (fiche salarié, dossier)
        │
Controller   ──►  SalarieController
        │
Service      ──►  SalarieService
        │            ├─►  WorkflowService        (transitions de statut)
        │            ├─►  MatriculeService      (génération du matricule)
        │            ├─►  NotificationService    (compte, badge…)
        │            └─►  AuditService           (journal_audit)
Repository   ──►  SalarieRepository
        │            └─►  HistoriqueRepository    (salarie_status_historique)
        │
Base de données (salarie, affectation, ...)
```

**Lien amont** : `PropositionEmbaucheService.enregistrerReponse` (accepté) appelle
`SalarieService.creerDepuisCandidature`, puis `ContratService.creerContrat`.

---

## 3. Types de données (DTO / modèles)

```text
StatutSalarie = EN_ATTENTE_EMBAUCHE | ACTIF | EN_PERIODE_ESSAI | SUSPENDU | PARTI

Salarie {
  idSalarie: long
  idCandidat: long | null
  matricule: string
  nom: string
  prenom: string
  email: string | null
  telephone: string | null
  dateEmbauche: date | null
  statut: StatutSalarie
}

NouveauSalarie {
  idCandidat: long | null
  nom: string
  prenom: string
  email: string | null
  telephone: string | null
  dateEmbauche: date
}

FiltreSalaries {
  statut: StatutSalarie | null
  idDepartement: long | null
  idPoste: long | null
}
```

---

## 4. `SalarieController` — point d'entrée HTTP

| Signature | Utilité |
|---|---|
| `creerDepuisCandidature(idCandidature: long, requete: Requete): Reponse` | Crée la fiche salarié à partir d'une proposition acceptée. |
| `afficherFiche(idSalarie: long, requete: Requete): Vue` | Dossier du salarié (contrats, affectations, documents). |
| `modifier(idSalarie: long, requete: Requete): Reponse` | Met à jour les informations du salarié. |
| `affecter(idSalarie: long, requete: Requete): Reponse` | Affecte le salarié à un poste. |
| `lister(requete: Requete): Vue` | Liste filtrée des salariés. |
| `afficherHistorique(idSalarie: long, requete: Requete): Vue` | Journal des changements de statut. |

---

## 5. `SalarieService` — logique métier

| Signature | Utilité |
|---|---|
| `creerDepuisCandidature(idCandidature: long, utilisateur: SessionUtilisateur): long` | Crée le salarié au statut **En attente d'embauche** à partir d'une proposition **Acceptée** : copie l'identité du candidat, génère le matricule. Retourne `idSalarie`. |
| `genererMatricule(dateEmbauche: date): string` | Produit un matricule **unique** (ex. `MAT-AAAA-00001`). |
| `modifier(idSalarie: long, modifications: NouveauSalarie, utilisateur: SessionUtilisateur): void` | Met à jour les informations du salarié. |
| `changerStatut(idSalarie: long, statutCible: StatutSalarie, utilisateur: SessionUtilisateur, commentaire: string \| null): void` | Change `salarie.statut`, écrit l'historique et l'audit (appelé par le module Contrat). |
| `affecter(idSalarie: long, idPoste: long, dateDebut: date, motif: string \| null, utilisateur: SessionUtilisateur): long` | Crée une `affectation` (le salarié occupe un poste) ; clôt l'affectation précédente si besoin. |
| `listerAffectations(idSalarie: long): Affectation[]` | Historique des affectations. |
| `consulter(idSalarie: long): Salarie \| null` | Détail d'un salarié. |
| `lister(filtre: FiltreSalaries): Salarie[]` | Liste filtrée. |
| `historique(idSalarie: long): LigneHistorique[]` | Journal des statuts. |

---

## 6. `SalarieRepository` — accès aux données

| Signature | Utilité |
|---|---|
| `inserer(salarie: Salarie): long` | `INSERT` dans `salarie` (statut **En attente d'embauche**). |
| `trouverParId(idSalarie: long): Salarie \| null` | `SELECT` d'un salarié. |
| `trouverParMatricule(matricule: string): Salarie \| null` | `SELECT` par matricule (UNIQUE). |
| `trouverParCandidat(idCandidat: long): Salarie \| null` | Salarié issu d'un candidat (évite les doublons). |
| `lister(filtre: FiltreSalaries): Salarie[]` | `SELECT` filtré. |
| `mettreAJour(salarie: Salarie): void` | `UPDATE` des informations. |
| `mettreAJourStatut(idSalarie: long, idStatut: long): void` | `UPDATE` du seul champ `statut`. |

---

## 7. Règles de transition (statut salarié)

| État courant | Déclencheur | État suivant |
|---|---|---|
| (aucun) | `creerDepuisCandidature` | En attente d'embauche |
| En attente d'embauche | contrat activé **avec** période d'essai | En période d'essai |
| En attente d'embauche | contrat activé **sans** période d'essai | Actif |
| En période d'essai | fin d'essai concluante | Actif |
| Tout état | rupture | Suspendu → Parti |

---

## 8. Déroulé de `creerDepuisCandidature` (pseudo-code)

```text
creerDepuisCandidature(idCandidature, utilisateur):
  exigerRole(utilisateur, [RH, recruteur])
  candidature = candidatureRepo.trouverParId(idCandidature)
  si candidature est null                                  → erreur OBJET_INTROUVABLE
  proposition = propositionRepo.trouverParCandidature(idCandidature)
  si proposition.statut != ACCEPTEE                        → erreur STATUT_INVALIDE
  si salarieRepo.trouverParCandidat(candidature.idCandidat) != null → erreur CONFLIT

  candidat = candidatRepo.trouverParId(candidature.idCandidat)

  début transaction
      matricule = genererMatricule(aujourd'hui)
      idSalarie = salarieRepo.inserer(salarie(candidat, matricule, EN_ATTENTE_EMBAUCHE))
      changerStatut(idSalarie, EN_ATTENTE_EMBAUCHE, utilisateur, 'Embauche acceptée')
      // suite : Contrat et Onboarding
      contratService.creerContrat(idSalarie, candidature.idOffre, utilisateur)
  fin transaction
  retourner idSalarie
```

---

## 9. Points d'attention sur le modèle

- `salarie.matricule` est **UNIQUE** : générer et vérifier l'unicité (`genererMatricule`).
- `salarie.id_candidat` est nullable : un salarié peut exister sans candidature (embauche directe
  ancienne) ; ne pas l'imposer.
- Plusieurs **contrats** par salarié (CDD puis CDI, etc.) : le statut du salarié reflète le contrat
  **actif** ; le détail est dans le module Contrat.
- `affectation` matérialise « occupe un poste » dans le temps (historique des affectations).
- Statut entier (FK vers `status_salarie.id`) : prévoir le mapping désignation ↔ id.
- Règle `RH.md` : le passage en **En période d'essai** ou **Actif** dépend de l'existence d'une
  période d'essai dans le **contrat** (voir module Contrat).

---

## 10. Ordre d'implémentation conseillé

1. `SalarieRepository` + `WorkflowService.idStatut/designationStatut`
2. `WorkflowService.changerStatut` + `HistoriqueRepository` + `AuditService`
3. `MatriculeService.genererMatricule`
4. `SalarieService.creerDepuisCandidature` (contrôle proposition **Acceptée**)
5. `modifier`, `consulter`, `lister`, `historique`
6. `affecter` / `listerAffectations`
7. `SalarieController` + vues
8. Branchement depuis `PropositionEmbaucheService.enregistrerReponse`
