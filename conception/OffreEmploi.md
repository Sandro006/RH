# Conception du module Offre d'emploi — Gestion RH

Ce document décrit une conception possible du module **offre d'emploi** (`RH.md` §II.B.1),
basée sur les tables `offre_emploi`, `status_offre_emploi`, `demande_recrutement`, `poste`,
`offre_emploi_status_historique`, `candidature`, `utilisateur`, `journal_audit` et `Action`.
Il précise le **lien automatique** : une demande de recrutement validée entraîne la création
d'une offre d'emploi.
Les signatures utilisent une notation neutre (`nom(param: Type): TypeRetour`).

---

## 1. Rappel des tables utilisées

| Table | Colonnes utiles |
|---|---|
| `offre_emploi` | `id_offre`, `statut`, `id_demande`, `titre`, `description`, `date_publication`, `date_cloture`, `canal_diffusion`, `date_creation` |
| `status_offre_emploi` | `id`, `designation` (Brouillon, Publiée, Suspendue, Clôturée, Annulée) |
| `demande_recrutement` | `id_demande`, `id_poste`, `type_contrat`, `duree_contrat`, `missions`, `date_souhaitee`, `remuneration_min`, `remuneration_max`, `statut` |
| `poste` | `id_poste`, `intitule`, `id_service`, `rang_id` |
| `offre_emploi_status_historique` | `id_offre`, `statut_ancien`, `statut_nouveau`, `date_changement`, `id_utilisateur`, `commentaire` |
| `candidature` | `id_candidature`, `id_offre`, `statut` (suivi des postulants) |
| `utilisateur` / `journal_audit` / `Action` | traçabilité (`CREATE`, `UPDATE`…) |

---

## 2. Architecture en couches

```
Vue (fiche offre, formulaire de publication)
        │
Controller   ──►  OffreEmploiController
        │
Service      ──►  OffreEmploiService
        │            ├─►  WorkflowService          (transitions de statut)
        │            ├─►  NotificationService       (info interne / canaux)
        │            └─►  AuditService              (journal_audit)
Repository   ──►  OffreEmploiRepository
        │            └─►  HistoriqueRepository      (offre_emploi_status_historique)
        │
Base de données (offre_emploi, status_offre_emploi, ...)
```

**Lien amont** : `DemandeRecrutementService.donnerFeuVert` appelle
`OffreEmploiService.creerDepuisDemande` dans la **même transaction**.

---

## 3. Types de données (DTO / modèles)

```text
StatutOffre = BROUILLON | PUBLIEE | SUSPENDUE | CLOTUREE | ANNULEE

OffreEmploi {
  idOffre: long
  statut: StatutOffre
  idDemande: long
  titre: string
  description: string | null
  datePublication: date | null
  dateCloture: date | null
  canalDiffusion: string | null
  dateCreation: datetime
}

NouvelleOffre {                 // dérivée de la demande validée et complétée par les RH
  idDemande: long
  titre: string
  description: string | null
  canalDiffusion: string | null
  dateCloture: date | null
}

FiltreOffres {
  statut: StatutOffre | null
  idDemande: long | null
  idPoste: long | null
}
```

---

## 4. `OffreEmploiController` — point d'entrée HTTP

| Signature | Utilité |
|---|---|
| `afficherFiche(idOffre: long, requete: Requete): Vue` | Affiche l'offre et ses actions selon statut/rôle. |
| `creerDepuisDemande(idDemande: long, requete: Requete): Reponse` | Crée (ou complète) l'offre associée à une demande validée. |
| `modifier(idOffre: long, requete: Requete): Reponse` | Modifie une offre **Brouillon**. |
| `publier(idOffre: long, requete: Requete): Reponse` | Publie l'offre sur les canaux choisis. |
| `suspendre(idOffre: long, requete: Requete): Reponse` | Suspend une offre publiée. |
| `reprendre(idOffre: long, requete: Requete): Reponse` | Reprend une offre suspendue. |
| `clore(idOffre: long, requete: Requete): Reponse` | Clôture les candidatures. |
| `annuler(idOffre: long, requete: Requete): Reponse` | Annule l'offre. |
| `lister(requete: Requete): Vue` | Liste filtrée des offres. |
| `listerCandidatures(idOffre: long, requete: Requete): Vue` | Liste des candidatures reçues pour une offre. |

---

## 5. `OffreEmploiService` — logique métier

| Signature | Utilité |
|---|---|
| `creerDepuisDemande(idDemande: long, utilisateur: SessionUtilisateur): long` | Crée l'offre au statut **Brouillon** à partir d'une demande **Validée** : récupère le poste, le type de contrat, les missions et la fourchette, génère un titre unique. Retourne `idOffre`. |
| `modifier(idOffre: long, modifications: NouvelleOffre, utilisateur: SessionUtilisateur): void` | Met à jour une offre **Brouillon** (titre, description, canaux, date de clôture). |
| `publier(idOffre: long, canalDiffusion: string, dateCloture: date \| null, utilisateur: SessionUtilisateur): void` | Passe l'offre à **Publiée**, renseigne `date_publication = aujourd'hui` et le(s) canal(aux). |
| `suspendre(idOffre: long, motif: string \| null, utilisateur: SessionUtilisateur): void` | Passe à **Suspendue** (suspension temporaire). |
| `reprendre(idOffre: long, utilisateur: SessionUtilisateur): void` | Repasse à **Publiée** depuis **Suspendue**. |
| `clore(idOffre: long, utilisateur: SessionUtilisateur): void` | Passe à **Clôturée**, renseigne `date_cloture`. |
| `annuler(idOffre: long, motif: string \| null, utilisateur: SessionUtilisateur): void` | Passe à **Annulée**. |
| `consulter(idOffre: long): OffreEmploi \| null` | Détail d'une offre. |
| `lister(filtre: FiltreOffres): OffreEmploi[]` | Liste filtrée. |
| `listerCandidatures(idOffre: long): Candidature[]` | Candidatures associées (délégué au module Candidature). |
| `offresOuvertes(): OffreEmploi[]` | Offres **Publiées** et non clôturées (visibles par les candidats). |

---

## 6. `WorkflowService` — transitions de statut

| Signature | Utilité |
|---|---|
| `idStatut(designation: StatutOffre): long` | Résout l'`id` dans `status_offre_emploi` par désignation. |
| `designationStatut(idStatut: long): StatutOffre` | Opération inverse. |
| `transitionAutorisee(statutActuel: StatutOffre, statutCible: StatutOffre): boolean` | Valide la table des transitions (§8). |
| `estOuverte(statut: StatutOffre): boolean` | `true` si l'offre accepte des candidatures (**Publiée** uniquement). |
| `changerStatut(idOffre: long, statutCible: StatutOffre, utilisateur: SessionUtilisateur, commentaire: string \| null): void` | Met à jour `offre_emploi.statut`, écrit dans `offre_emploi_status_historique` et l'audit. |

---

## 7. `OffreEmploiRepository` — accès aux données

| Signature | Utilité |
|---|---|
| `inserer(offre: OffreEmploi): long` | `INSERT` dans `offre_emploi` (statut **Brouillon**), retourne `id_offre`. |
| `mettreAJour(offre: OffreEmploi): void` | `UPDATE` des champs modifiables. |
| `trouverParId(idOffre: long): OffreEmploi \| null` | `SELECT` d'une offre. |
| `trouverParDemande(idDemande: long): OffreEmploi \| null` | Offre liée à une demande (unicité de fait). |
| `lister(filtre: FiltreOffres): OffreEmploi[]` | `SELECT` filtré. |
| `mettreAJourStatut(idOffre: long, idStatut: long): void` | `UPDATE` du seul champ `statut`. |

---

## 8. Table des transitions autorisées

| État courant | Action | État suivant |
|---|---|---|
| (aucun) | `creerDepuisDemande` | Brouillon |
| Brouillon | `modifier` | (inchangé) |
| Brouillon | `publier` | Publiée |
| Brouillon | `annuler` | Annulée |
| Publiée | `suspendre` | Suspendue |
| Publiée | `clore` | Clôturée |
| Publiée | `annuler` | Annulée |
| Suspendue | `reprendre` | Publiée |
| Suspendue | `clore` | Clôturée |
| Suspendue | `annuler` | Annulée |

États **finaux** : Clôturée, Annulée.

---

## 9. Déroulé de `creerDepuisDemande` (pseudo-code)

```text
creerDepuisDemande(idDemande, utilisateur):
  exigerRole(utilisateur, [RH, recruteur])
  demande = demandeRepo.trouverParId(idDemande)
  si demande est null                              → erreur OBJET_INTROUVABLE
  si demande.statut != VALIDEE                     → erreur STATUT_INVALIDE
  si offreRepo.trouverParDemande(idDemande) != null → erreur CONFLIT

  poste = posteService.consulter(demande.idPoste)
  titre = construireTitreUnique(poste.intitule, idDemande)
  offre = NouvelleOffre(idDemande, titre, demande.missions, null, demande.dateSouhaitee)

  début transaction
      idOffre = offreRepo.inserer(offre)                 // statut Brouillon
      workflow.changerStatut(idOffre, BROUILLON, utilisateur, 'Création depuis demande validée')
      auditService.journaliser(utilisateur.idUtilisateur, 'CREATE',
                               'offre_emploi', idOffre, null, adresseIp)
  fin transaction
  retourner idOffre
```

> Automatisation : `DemandeRecrutementService.donnerFeuVert` appelle
> `creerDepuisDemande` dans la même transaction, de sorte que « demande validée ⇒ offre créée ».

---

## 10. Points d'attention sur le modèle

- `offre_emploi.titre` est **UNIQUE** : prévoir une génération de titre non conflictuelle
  (ex. `intitule + " - #" + idDemande`).
- `canal_diffusion` est un `varchar(120)` unique : si plusieurs canaux sont nécessaires,
  stocker une liste sérialisée ou créer une table `offre_canal`.
- La relation `offre_emploi.id_demande` → `demande_recrutement` n'est plus **UNIQUE** :
  garantir l'unicité « une demande validée ⇒ une seule offre » dans `OffreEmploiService`.
- `statut` est un entier (FK vers `status_offre_emploi.id`) : prévoir le mapping désignation ↔ id.
- La publication « interne » (information aux salariés, délai de priorité) est une **règle métier**
  à porter dans `NotificationService` (pas de table dédiée).

---

## 11. Ordre d'implémentation conseillé

1. `OffreEmploiRepository` + `WorkflowService.idStatut/designationStatut`
2. `WorkflowService.changerStatut` + `HistoriqueRepository` + `AuditService`
3. `OffreEmploiService.creerDepuisDemande` (branché sur `DemandeRecrutementService.donnerFeuVert`)
4. `modifier`, `consulter`, `lister`
5. `publier`, `suspendre`, `reprendre`, `clore`, `annuler`
6. `OffreEmploiController` + vues
7. `listerCandidatures` (intégration module Candidature)
