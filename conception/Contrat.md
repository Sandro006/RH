# Conception du module Contrat — Gestion RH

Ce document décrit une conception possible du module **contrat** (`RH.md` §II.B.3 et §III),
basée sur les tables `contrat`, `type_contrat`, `status_contrat`, `contrat_status_historique`,
`salarie`, `poste`, `grille_salariale`, `affectation`, `utilisateur`, `journal_audit` et `Action`.
Il couvre la conclusion d'un contrat de travail, **avec ou sans période d'essai**, ainsi que
ses avenants et son cycle de vie.
Les signatures utilisent une notation neutre (`nom(param: Type): TypeRetour`).

---

## 1. Rappel des tables utilisées

| Table | Colonnes utiles |
|---|---|
| `contrat` | `id_contrat`, `id_salarie`, `id_poste`, `type_contrat_id`, `date_debut`, `date_fin`, `salaire_base`, `periode_essai_debut`, `periode_essai_fin`, `date_signature`, `statut` |
| `type_contrat` | `id`, `designation` (CDI, CDD, Stage, Interim, Consultant, Essai, Alternance) |
| `status_contrat` | `id`, `designation` (En préparation, En attente de signature, Signé, En cours, Terminé, Résilié) |
| `contrat_status_historique` | `id_contrat`, `statut_ancien`, `statut_nouveau`, `date_changement`, `id_utilisateur`, `commentaire` |
| `salarie` / `status_salarie` | statut du salarié (En période d'essai, Actif…) |
| `poste` | `id_poste`, `rang_id` (sert à déduire la catégorie pour l'essai) |
| `grille_salariale` | cadrage de `salaire_base` |
| `utilisateur` / `journal_audit` / `Action` | traçabilité |

---

## 2. Architecture en couches

```
Vue (rédaction du contrat, signature, avenants)
        │
Controller   ──►  ContratController
        │
Service      ──►  ContratService
        │            ├─►  PeriodeEssaiService   (calcul et règles d'essai)
        │            ├─►  GrilleService         (contrôle du salaire)
        │            ├─►  WorkflowService       (transitions de statut)
        │            └─►  AuditService          (journal_audit)
Repository   ──►  ContratRepository
        │            └─►  HistoriqueRepository   (contrat_status_historique)
        │
Base de données (contrat, ...)
```

**Liens** : amont = module Salarié (`creerDepuisCandidature`) ; aval = module Onboarding
(à la signature) ; le statut du salarié suit le contrat actif.

---

## 3. Types de données (DTO / modèles)

```text
StatutContrat = EN_PREPARATION | EN_ATTENTE_SIGNATURE | SIGNE | EN_COURS | TERMINE | RESILIE

CategorieEssai = OUVRIER_EMPLOYE | AGENT_MAITRISE | CADRE

PeriodeEssai {
  debut: date
  fin: date
  dureeJours: int
}

Contrat {
  idContrat: long
  idSalarie: long
  idPoste: long
  typeContratId: long
  dateDebut: date
  dateFin: date | null
  salaireBase: decimal | null
  periodeEssai: PeriodeEssai | null   // null => pas de période d'essai
  dateSignature: date | null
  statut: StatutContrat
}

NouveauContrat {
  idSalarie: long
  idPoste: long
  typeContratId: long
  dateDebut: date
  dateFin: date | null                // requis pour CDD / Stage / Interim
  salaireBase: decimal
  avecPeriodeEssai: boolean           // OUI ou NON
  dureeEssaiJours: int | null         // si non fourni, calculée par PeriodeEssaiService
  categorie: CategorieEssai | null    // pour CDI si non déductible du poste
}

FiltreContrats {
  statut: StatutContrat | null
  idSalarie: long | null
  typeContratId: long | null
}
```

---

## 4. `ContratController` — point d'entrée HTTP

| Signature | Utilité |
|---|---|
| `creer(idSalarie: long, requete: Requete): Reponse` | Rédige un nouveau contrat (avec/sans période d'essai). |
| `afficherFiche(idContrat: long, requete: Requete): Vue` | Détail du contrat et actions. |
| `modifier(idContrat: long, requete: Requete): Reponse` | Modifie un contrat **En préparation**. |
| `soumettrePourSignature(idContrat: long, requete: Requete): Reponse` | Envoie le contrat pour signature. |
| `signer(idContrat: long, requete: Requete): Reponse` | Enregistre la signature et active le contrat. |
| `renouveler(idContrat: long, requete: Requete): Reponse` | Crée un nouveau contrat (renouvellement CDD). |
| `creerAvenant(idContrat: long, requete: Requete): Reponse` | Avenant (mobilité interne, changement d'intitulé/mission). |
| `mettreFin(idContrat: long, requete: Requete): Reponse` | Clôture (fin de contrat) ou résilie. |
| `lister(requete: Requete): Vue` | Liste filtrée des contrats. |

---

## 5. `PeriodeEssaiService` — règles de la période d'essai

| Signature | Utilité |
|---|---|
| `categorieDepuisPoste(idPoste: long): CategorieEssai` | Déduit la catégorie (ouvrier/employé, agent de maîtrise, cadre) depuis le poste/rang. |
| `calculer(typeContratId: long, dateDebut: date, dateFin: date \| null, categorie: CategorieEssai): PeriodeEssai` | Calcule la durée **maximale** légale et la borne de fin d'essai. |
| `dureeMaxJours(typeContratId: long, dateFin: date \| null, categorie: CategorieEssai): int` | Retourne le plafond légal selon le type de contrat. |
| `deduireStages(idSalarie: long, dureeEssaiJours: int): int` | Déduit d'éventuelles périodes de stage antérieures dans l'entreprise. |
| `validerDuree(typeContratId: long, dureeJours: int, categorie: CategorieEssai): boolean` | Vérifie que la durée demandée respecte le plafond légal. |

**Rappel des plafonds (`RH.md` §III)** :

| Contrat | Catégorie | Durée maximale d'essai |
|---|---|---|
| CDD / Stage (≤ 6 mois) | — | 1 jour par semaine, max **2 semaines** |
| CDD / Stage (> 6 mois) | — | **1 mois** |
| CDI | Ouvriers / employés | **2 mois** |
| CDI | Agents de maîtrise / techniciens | **3 mois** |
| CDI | Cadres | **4 mois** |
| CDI sans mention écrite | — | **aucune** période d'essai |

---

## 6. `ContratService` — logique métier

| Signature | Utilité |
|---|---|
| `creerContrat(nouveau: NouveauContrat, utilisateur: SessionUtilisateur): long` | Crée le contrat au statut **En préparation**. Si `avecPeriodeEssai`, calcule `periode_essai_debut` / `periode_essai_fin` via `PeriodeEssaiService`, sinon les laisse à `NULL`. Contrôle le salaire vs grille. Retourne `idContrat`. |
| `modifier(idContrat: long, modifications: NouveauContrat, utilisateur: SessionUtilisateur): void` | Modifie un contrat **En préparation** (y compris ajouter/retirer la période d'essai). |
| `soumettrePourSignature(idContrat: long, utilisateur: SessionUtilisateur): void` | Passe à **En attente de signature**. |
| `signer(idContrat: long, dateSignature: date, utilisateur: SessionUtilisateur): void` | Enregistre `date_signature` et passe à **Signé**, puis active le contrat. |
| `activer(idContrat: long, utilisateur: SessionUtilisateur): void` | Passe à **En cours** ; met le salarié **En période d'essai** si le contrat en prévoit une, sinon **Actif**. |
| `mettreFin(idContrat: long, motif: string, utilisateur: SessionUtilisateur): void` | Passe à **Terminé** (arrivée du terme) ou **Résilié** (rupture) ; met le salarié **Parti**. |
| `renouveler(idContrat: long, nouvelleDateFin: date, utilisateur: SessionUtilisateur): long` | Clôture le terme et crée un nouveau contrat (renouvellement CDD, dans la limite de 2). |
| `creerAvenant(idContrat: long, modifications: NouveauContrat, utilisateur: SessionUtilisateur): long` | Crée un contrat avenant (mobilité interne : rattachement, missions, intitulé). |
| `contratsEnCours(idSalarie: long): Contrat[]` | Contrat(s) actif(s) d'un salarié. |
| `consulter(idContrat: long): Contrat \| null` | Détail d'un contrat. |
| `lister(filtre: FiltreContrats): Contrat[]` | Liste filtrée. |
| `historique(idContrat: long): LigneHistorique[]` | Journal des statuts. |

---

## 7. `ContratRepository` — accès aux données

| Signature | Utilité |
|---|---|
| `inserer(contrat: Contrat): long` | `INSERT` dans `contrat` (statut **En préparation**), période d'essai nullable. |
| `trouverParId(idContrat: long): Contrat \| null` | `SELECT` d'un contrat. |
| `listerParSalarie(idSalarie: long): Contrat[]` | Contrats (successifs) d'un salarié. |
| `lister(filtre: FiltreContrats): Contrat[]` | `SELECT` filtré. |
| `mettreAJour(contrat: Contrat): void` | `UPDATE` des champs modifiables. |
| `mettreAJourStatut(idContrat: long, idStatut: long): void` | `UPDATE` du seul champ `statut`. |
| `mettreAJourPeriodeEssai(idContrat: long, debut: date \| null, fin: date \| null): void` | Renseigne/supprime la période d'essai. |

---

## 8. Table des transitions autorisées

| État courant | Action | État suivant |
|---|---|---|
| (aucun) | `creerContrat` | En préparation |
| En préparation | `modifier` | (inchangé) |
| En préparation | `soumettrePourSignature` | En attente de signature |
| En attente de signature | `signer` | Signé |
| Signé | `activer` | En cours |
| En cours | `mettreFin` (terme) | Terminé |
| En cours | `mettreFin` (rupture) | Résilié |

États **finaux** : Terminé, Résilié.

---

## 9. Déroulé de `creerContrat` (pseudo-code)

```text
creerContrat(nouveau, utilisateur):
  exigerRole(utilisateur, [RH, recruteur])
  salarie = salarieRepo.trouverParId(nouveau.idSalarie)
  si salarie est null                              → erreur OBJET_INTROUVABLE
  si typeContrat non permanent et nouveau.dateFin est null → erreur DONNEES_INCOHERENTES
  si non grilleService.remunerationValide(nouveau.idPoste, nouveau.salaireBase)
                                                    → erreur DONNEES_INCOHERENTES

  // --- période d'essai optionnelle ---
  periodeEssai = null
  si nouveau.avecPeriodeEssai:
      categorie = nouveau.categorie ?? periodeEssaiService.categorieDepuisPoste(nouveau.idPoste)
      si nouveau.dureeEssaiJours est null:
          periodeEssai = periodeEssaiService.calculer(nouveau.typeContratId,
                                                      nouveau.dateDebut,
                                                      nouveau.dateFin, categorie)
      sinon:
          si non periodeEssaiService.validerDuree(nouveau.typeContratId,
                                                  nouveau.dureeEssaiJours, categorie)
                                                    → erreur DONNEES_INCOHERENTES
          periodeEssai = (nouveau.dateDebut, dateDebut + dureeEssaiJours)

  contrat = Contrat(..., periodeEssai, statut EN_PREPARATION)

  début transaction
      idContrat = contratRepo.inserer(contrat)
      workflow.changerStatut(idContrat, EN_PREPARATION, utilisateur, 'Création du contrat')
      auditService.journaliser(utilisateur.idUtilisateur, 'CREATE',
                               'contrat', idContrat, null, adresseIp)
  fin transaction
  retourner idContrat
```

---

## 10. Points d'attention sur le modèle

- La période d'essai est **optionnelle** : `periode_essai_debut` / `periode_essai_fin` restent
  `NULL` quand il n'y en a pas. Le booléen `avecPeriodeEssai` est un choix de saisie, non stocké.
- **Règle CDI** : sans mention écrite au contrat, il n'y a **pas** de période d'essai (`RH.md` §III).
- Les plafonds d'essai dépendent du **type de contrat** et de la **catégorie** (ouvrier/agent/cadre) ;
  la catégorie est déduite du `poste`/`rang` (à confirmer : ajouter une colonne `categorie` si besoin).
- Les périodes de **stage** antérieures peuvent réduire l'essai → `deduireStages`.
- `salaire_base` doit rester dans la `grille_salariale` du poste (`GrilleService`).
- Un salarié peut avoir **plusieurs contrats successifs** ; un seul **En cours** à la fois.
- L'**avenant** couvre la mobilité interne (`RH.md` §II.A.7) : changement de rattachement,
  missions, intitulé — matérialisé comme un contrat lié, plutôt qu'une modification destructive.
- Statut entier (FK vers `status_contrat.id`) : prévoir le mapping désignation ↔ id.

---

## 11. Ordre d'implémentation conseillé

1. `ContratRepository` + `WorkflowService.idStatut/designationStatut`
2. `WorkflowService.changerStatut` + `HistoriqueRepository` + `AuditService`
3. `GrilleService.remunerationValide`
4. `PeriodeEssaiService` (categorieDepuisPoste, calculer, dureeMaxJours, validerDuree)
5. `ContratService.creerContrat` (avec/sans période d'essai)
6. `soumettrePourSignature`, `signer`, `activer` (mise à jour du statut salarié)
7. `renouveler`, `creerAvenant`, `mettreFin`
8. `consulter`, `lister`, `historique`
9. `ContratController` + vues
