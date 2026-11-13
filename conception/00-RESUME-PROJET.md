# Résumé de conception — Projet **Gestion RH**

> **But de ce document** : expliquer simplement l'ensemble du projet, **module par module**, sans
> entrer dans le code. Il sert de support de présentation (soutenance, revue, onboarding d'un
> nouveau développeur) et renvoie aux documents détaillés quand il faut creuser.

**Sommaire**

1. [Le projet en une page](#1-le-projet-en-une-page)
2. [Socle technique et conventions](#2-socle-technique-et-conventions)
3. [Les modules](#3-les-modules)
   - [3.1 Authentification, comptes et habilitations](#31-authentification-comptes-et-habilitations)
   - [3.2 Référentiels et organisation](#32-référentiels-et-organisation)
   - [3.3 Tableau de bord](#33-tableau-de-bord)
   - [3.4 Demande de recrutement](#34-demande-de-recrutement)
   - [3.5 Offre d'emploi](#35-offre-demploi)
   - [3.6 Candidature](#36-candidature)
   - [3.7 Entretien](#37-entretien)
   - [3.8 Évaluation et sélection](#38-évaluation-et-sélection)
   - [3.9 Proposition d'embauche et négociation](#39-proposition-dembauche-et-négociation)
   - [3.10 Salarié et dossier administratif](#310-salarié-et-dossier-administratif)
   - [3.11 Contrat et période d'essai](#311-contrat-et-période-dessai)
   - [3.12 Onboarding et documents](#312-onboarding-et-documents)
   - [3.13 Congés et absences](#313-congés-et-absences)
   - [3.14 Temps de travail et pointage](#314-temps-de-travail-et-pointage)
   - [3.15 Paie et finances](#315-paie-et-finances)
   - [3.16 Sécurité, confidentialité et audit](#316-sécurité-confidentialité-et-audit)
   - [3.17 Département informatique](#317-département-informatique)
4. [Parcours complet de bout en bout](#4-parcours-complet-de-bout-en-bout)
5. [Matrice rôles × modules](#5-matrice-rôles--modules)
6. [Les règles d'or du projet](#6-les-règles-dor-du-projet)
7. [État d'avancement et points ouverts](#7-état-davancement-et-points-ouverts)
8. [Carte des fichiers](#8-carte-des-fichiers)
9. [Le projet expliqué en 5 minutes](#9-le-projet-expliqué-en-5-minutes)

---

## 1. Le projet en une page

**Gestion RH** est un SIRH (Système d'Information des Ressources Humaines) qui couvre tout le
cycle de vie du collaborateur :

```text
Besoin de recrutement
        │
        ▼
Demande de recrutement ──► Offre d'emploi ──► Candidature ──► Entretien ──► Évaluation
                                                                                 │
                                                                                 ▼
                                                                        Proposition d'embauche
                                                                          (+ négociation)
                                                                                 │
                                                                                 ▼
                                     Salarié ──► Contrat ──► Onboarding ──► Vie du salarié
                                                                                 │
                                          ┌──────────────────────────────────────┤
                                          ▼                    ▼                 ▼
                                     Congés/absences      Temps/pointage        Paie
```

Autour de cette chaîne, le projet fournit : un **tableau de bord**, des **référentiels**
(départements, services, postes, grilles salariales), la **gestion des comptes et des rôles**,
un **dossier salarié** complet et un **journal d'audit**.

### Les utilisateurs (rôles)

| Rôle | Code | Ce qu'il fait |
|---|---|---|
| Administrateur | `admin` | Accès complet, comptes, référentiels, support |
| Ressources humaines | `rh` | Recrutement, contrats, congés, documents, paie |
| Manager | `manager` | Demande de recrutement, validation des congés de son équipe |
| Direction | `direction` | Validation hiérarchique, congés, propositions |
| Contrôle de gestion | `controle_gestion` | Validation budgétaire, grille salariale |
| Recruteur | `recruteur` | Offres, candidatures, entretiens, sélection |
| Salarié | `salarie` | Ses congés, ses documents, ses tâches, son pointage |
| Finance | `finance` | Grilles salariales, bulletins |

### Les trois promesses du système

1. **Un seul fil conducteur** : une demande validée devient une offre, une candidature retenue
   devient un salarié, un contrat signé déclenche l'onboarding.
2. **Rien ne se perd** : les statuts changent, les historiques s'empilent, rien n'est supprimé
   physiquement (annulation, refus, archivage).
3. **Chacun voit ce qui le concerne** : le menu est filtré par rôle, mais **chaque action est
   revalidée côté serveur** (on ne peut pas contourner l'interface).

---

## 2. Socle technique et conventions

### 2.1 Stack

| Couche | Technologie |
|---|---|
| Back-end | **CodeIgniter 4.7** / PHP 8.5 |
| Base de données | **PostgreSQL** (base `gestion_rh`, compatible Neon / Lakebase) |
| Front-end | HTML + CSS + **JavaScript natif** (pas de framework), `fetch` vers l'API REST |
| Tests | PHPUnit, scripts SQL de vérification |
| Tâches planifiées | Commandes `php spark` + cron système |

### 2.2 Architecture en couches

```text
Vue (PHP/HTML + JS)
      │  fetch JSON
      ▼
Controller        → reçoit HTTP, valide l'entrée, renvoie JSON ou page
      ▼
Service           → logique métier, règles, orchestration, transactions
      ▼
Model / Entity    → accès aux données (requêtes SQL préparées)
      ▼
PostgreSQL
```

**Règle importante** : les services ne s'appellent pas entre modules métier « voisins ». Chaque
module lit l'état dont il a besoin **directement en base** (statuts, clés étrangères). Cela permet
de développer et livrer les modules indépendamment.

### 2.3 Conventions communes à tous les modules

| Convention | Contenu |
|---|---|
| **Statuts** | Chaque objet à cycle de vie a une table `status_*` (ou `statut_*`) + un service de workflow qui contrôle les transitions autorisées |
| **Historiques** | Table `<objet>_status_historique` : ancien statut, nouveau statut, date, utilisateur, commentaire |
| **Audit** | `journal_audit` : qui, quand, quelle action, quel objet, quelle IP |
| **Transactions** | Toute opération qui touche plusieurs tables est dans une transaction (ex. valider un congé = débiter + créer les absences + historiser) |
| **Suppression** | Jamais de `DELETE` métier : on annule, on refuse, on archive |
| **Erreurs** | Format JSON commun + exceptions métier (`StatutInvalide`, `ObjetIntrouvable`, `AccesRefuse`, `Conflit`, `DonneesIncoherentes`) |
| **Sécurité** | Filtres `auth`, `role:...`, `accesInformatique` ; mots de passe hachés (bcrypt) ; sessions |
| **Identité** | Deux espaces reliés : `utilisateur.id_employe → employe.id → salarie.id_salarie` |
| **Référentiels** | Statuts, types, rangs et grilles sont chargés depuis la base, jamais codés en dur dans les vues |

### 2.4 Le modèle de données par domaine

| Domaine | Tables principales |
|---|---|
| Organisation | `departement`, `service`, `poste`, `rang`, `grille_salariale`, `employe` |
| Comptes | `utilisateur`, `action`, `journal_audit` |
| Recrutement | `demande_recrutement`, `validation`, `offre_emploi` + statuts + historiques |
| Candidatures | `candidat`, `candidature` + statuts + historiques |
| Sélection | `entretien`, `evaluation_candidat`, `type_entrentien` + statuts + historiques |
| Embauche | `proposition_embauche`, `revendication`, `nature_revendication` + statuts + historiques |
| Personnel | `salarie`, `contrat`, `affectation`, `onboarding`, `onboarding_tache`, `document`, `type_document` + statuts + historiques |
| Congés | `type_conge`, `droit_conge`, `solde_conge`, `demande_conge`, `absence`, `justificatif_absence`, `mouvement_solde`, `jour_ferie`, `delegation_validation` |
| Temps | `temps_planning`, `temps_pointage` |
| Paie | `bulletin_paie`, `element_paie`, `taux_cotisation` |
| Vues | 15 vues Congés (`v_solde_conge_salarie`, `v_demandes_a_valider_*`, `v_planning_absences`…) + `v_bulletins_mois` |

> Le schéma complet est dans `script/db/01_migrations.sql` (installation neuve) et les migrations
> incrémentales `04_…`, `08_…`, `14_…`.

---

## 3. Les modules

Chaque fiche suit le même plan : **objectif → acteurs → données → services → règles → cycle de
vie → écrans/API → point à retenir**.

### 3.1 Authentification, comptes et habilitations

- **Objectif** : identifier l'utilisateur, ouvrir une session, contrôler ses droits et tracer
  chaque connexion.
- **Acteurs** : tous ; administration des comptes réservée à `admin` (et au département
  informatique selon les routes).
- **Données** : `utilisateur` (identifiant, mot de passe haché, rôle, actif, dernière connexion),
  `employe`, `journal_audit`, `action`.
- **Services** : `AuthService`, `PasswordHasher`, `SessionManager`, `AuditService`,
  `UtilisateurService`.
- **Règles clés** :
  - mot de passe **jamais en clair** (bcrypt), complexité minimale ;
  - compte désactivé = connexion refusée ;
  - message identique pour « identifiant inconnu » et « mot de passe faux » (anti-énumération) ;
  - protection contre les tentatives répétées (verrouillage / journalisation `REFUS`) ;
  - garde-fou « **dernier administrateur** » : impossible de désactiver le dernier compte admin.
- **Cycle de vie** : connexion → session → déconnexion ; changement de mot de passe ; activation /
  désactivation / réinitialisation.
- **Écrans / API** :
  - `/login`, `/profil/mot-de-passe` ;
  - `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/session`,
    `PATCH /api/auth/mot-de-passe` ;
  - `GET|POST /api/utilisateurs`, `PATCH /api/utilisateurs/{id}`,
    `POST /api/utilisateurs/{id}/activer|desactiver|reinitialiser-mot-de-passe`.
- **À retenir** : le rôle est la clé de tout le reste. Les filtres `auth` et `role:…` sont la
  première barrière ; les services refont le contrôle (`exigerRole`).

### 3.2 Référentiels et organisation

- **Objectif** : décrire l'entreprise et servir de **source de vérité** aux autres modules.
- **Acteurs** : `admin`, `rh`, `controle_gestion`, `finance` (selon référentiel).
- **Données** : `departement` → `service` → `poste` → `rang` ; `grille_salariale` ; `type_contrat`,
  `type_document`, `type_entrentien`, `nature_revendication`, toutes les tables `status_*`.
- **Services** : `ReferentielService`, `GrilleSalarialeService`.
- **Règles clés** :
  - un service est **toujours** rattaché à un département ;
  - un poste est rattaché à un service et porte un rang ;
  - la grille salariale donne `salaire_min` / `salaire_max` par poste et par période ;
  - les libellés de statut sont résolus par désignation (mapping désignation ↔ id).
- **Écrans / API** : `GET /api/referentiels/{departements,services,postes,rangs,types-contrat,
  types-document,types-entretien,statuts/{famille},natures-revendication,employes}` ;
  CRUD `api/finance/grilles-salariales`.
- **À retenir** : toute évolution du schéma ou d'un référentiel impacte plusieurs modules. Les
  scripts SQL sont **idempotents** et versionnés.

### 3.3 Tableau de bord

- **Objectif** : donner à chaque utilisateur une vue d'ensemble et ses actions en attente.
- **Acteurs** : tous les rôles (contenu adapté).
- **Données** : compteurs calculés **en SQL** sur les tables des autres modules (aucun appel de
  service inter-module).
- **Services** : `DashboardService`, `DashboardController`.
- **Indicateurs** : demandes en attente, offres publiées, candidatures actives, entretiens à
  venir, propositions en cours, tâches d'onboarding, documents expirant sous 30 jours, documents
  expirés.
- **Blocs de l'écran** : carte « Mon profil », 8 indicateurs, « Mes actions en attente », « Accès
  rapides », calendrier de pointage du salarié.
- **Règle clé** : un compteur en erreur ne casse pas la page (valeur de repli sécurisée).
- **API** : `GET /api/dashboard`.
- **À retenir** : c'est la page d'accueil qui « raconte » l'état du recrutement en un coup d'œil.

### 3.4 Demande de recrutement

- **Objectif** : formaliser un besoin, le contrôler, le budgéter et le valider avant toute annonce.
- **Acteurs** : `manager` (crée/soumet), `rh` (contrôle de cohérence), `controle_gestion`
  (budget), `direction` (validation hiérarchique), `recruteur` (lecture).
- **Données** : `demande_recrutement`, `validation`,
  `demande_recrutement_status_historique`, `poste`, `service`, `grille_salariale`.
- **Services** : `DemandeRecrutementService`, `CoherenceService`, `WorkflowService`,
  `NotificationService`, `AuditService`.
- **Règles clés** (`CoherenceService`) :
  - le poste existe dans l'organigramme ;
  - la rémunération est dans la grille et cohérente avec l'équité interne ;
  - le type de contrat est légalement compatible avec le motif (CDD = remplacement, accroissement,
    saisonnier ; durée maximale respectée) ;
  - une solution interne (mobilité) est étudiée avant l'ouverture externe.
- **Cycle de vie** :

```text
En cours ──soumettre──► En contrôle RH
   ▲                        │ VALIDER          ┌─ CORRIGER ─► À corriger ──┐
   └────────────────────────┼──────────────────┘                          │
                            ▼                                             │
                  En validation budgétaire ──VALIDER──► En validation hiérarchique
                                                                  │
                          ┌───────────── FEU VERT ────────────────┤
                          ▼                                       ▼
                      Validée                         En consultation interne ──► Validée / Refusée
```

  États finaux : **Validée**, **Refusée**, **Annulée** (annulation possible hors état final).
- **Écrans / API** : liste, formulaire, fiche, édition, file « à traiter » ;
  `GET|POST /api/demandes`, `PATCH /api/demandes/{id}`, `GET …/coherence`,
  `GET …/validations`, `GET …/historique`, `POST /api/demandes/{id}/{action}`.
- **À retenir** : le **feu vert** crée l'offre d'emploi dans la même transaction. C'est le premier
  chaînage fort du projet.

### 3.5 Offre d'emploi

- **Objectif** : transformer une demande validée en annonce diffusable, puis piloter sa
  publication.
- **Acteurs** : `rh`, `recruteur`, `admin` ; lecture publique pour les candidats.
- **Données** : `offre_emploi`, `status_offre_emploi`, `offre_emploi_status_historique`,
  `demande_recrutement`, `candidature`.
- **Services** : `OffreEmploiService`, `WorkflowService`.
- **Règles clés** :
  - une offre naît **Brouillon** depuis une demande **Validée** (titre unique) ;
  - seules les offres **Publiées** acceptent des candidatures ;
  - une seule offre active par demande (contrôle applicatif) ;
  - la clôture fixe `date_cloture` et ferme les candidatures.
- **Cycle de vie** :

```text
Brouillon ──publier──► Publiée ──suspendre──► Suspendue ──reprendre──► Publiée
     │                    │                      │
     └──annuler──► Annulée └──clore──► Clôturée ◄┘
```

  États finaux : **Clôturée**, **Annulée**.
- **Écrans / API** : liste, fiche, formulaire, vitrine publique ;
  `GET /api/offres`, `PATCH /api/offres/{id}`, `POST /api/offres/{id}/{action}`,
  `GET /api/public/offres`, `GET /api/public/offres/{id}`, page `/candidatures`.
- **À retenir** : l'offre est le **point de rencontre** entre l'entreprise et les candidats.

### 3.6 Candidature

- **Objectif** : recevoir et suivre les candidatures, externes comme internes (mobilité).
- **Acteurs** : candidat (public), `rh`, `recruteur` ; le salarié en mobilité passe par le canal
  interne.
- **Données** : `candidat` (email unique), `candidature`,
  `candidature_status_historique`, `offre_emploi`, `salarie` (mobilité).
- **Services** : `CandidatureService`, `EligibiliteService`.
- **Règles clés** :
  - l'offre doit être **Publiée** ;
  - un candidat est identifié par son **email** : `trouverOuCreerCandidat` ;
  - anti-doublon : une candidature par candidat et par offre (contrôle applicatif) ;
  - **mobilité interne** : ancienneté minimale, compétences transférables, absence de contrainte ;
  - on ne sélectionne pas selon le statut de la personne, mais selon les **critères** ;
  - les candidatures refusées sont **conservées** (possibilité de changement d'avis).
- **Cycle de vie** :

```text
Reçue ─► En présélection ─► Présélectionnée ─► Entretien prévu ─► En évaluation ─► Retenue
  └──────────────┴──────────────┴──────────────────┴────────────────┴──► Refusée
  Tout état non final ──► Retirée
```

  États finaux : **Retenue**, **Refusée**, **Retirée**.
- **Écrans / API** : `/candidatures`, `/candidatures/postuler/{id}`, liste RH, fiche, suivi ;
  `POST /api/public/offres/{id}/candidatures`, `GET /api/candidatures`,
  `GET /api/candidatures/{id}`, `POST /api/candidatures/{id}/{action}`.
- **À retenir** : la candidature est le **pivot** entre le recrutement et la sélection.

### 3.7 Entretien

- **Objectif** : planifier, suivre et réaliser les entretiens d'une candidature présélectionnée.
- **Acteurs** : `rh`, `recruteur`, `direction` ; le recruteur est un `employe`.
- **Données** : `entretien`, `status_entretien`, `type_entrentien`,
  `entretien_status_historique`, `candidature`.
- **Services** : `EntretienService`, `WorkflowService`.
- **Règles clés** :
  - un entretien se planifie pour une candidature **Présélectionnée** → la candidature passe à
    **Entretien prévu** ;
  - une candidature peut avoir **plusieurs entretiens** ;
  - la réalisation enregistre un **compte rendu** et passe la candidature à **En évaluation** ;
  - annulation possible avec motif.
- **Cycle de vie** :

```text
Planifié ──confirmer──► Confirmé
   │  └──reporter──► Reporté ──► Planifié
   ├──realiser──► Réalisé
   └──annuler──► Annulé
```

  États finaux : **Réalisé**, **Annulé**.
- **Écrans / API** : agenda, fiche, formulaire ;
  `GET|POST /api/entretiens`, `POST /api/entretiens/{id}/{action}`,
  `GET /api/candidatures/{id}/entretiens`, `POST /api/offres/{id}/entretiens`.
- **À retenir** : l'entretien est le **déclencheur** de l'évaluation.

### 3.8 Évaluation et sélection

- **Objectif** : noter les candidats de façon structurée, produire une synthèse et **décider** qui
  est retenu.
- **Acteurs** : `rh`, `recruteur`, `direction`, `admin`.
- **Données** : `evaluation_candidat` (critères JSON, note, commentaire), `candidature`.
- **Services** : `EvaluationService`, `SelectionService`.
- **Règles clés** :
  - critères : compétences techniques, expérience, communication, motivation, adéquation au poste ;
  - note **0 à 5**, moyenne calculée si non saisie ; plusieurs évaluateurs possibles ;
  - **seuil de rétention** paramétrable (`EVALUATION_SEUIL_RETENTION`, défaut **3,0**) ;
  - on ne peut retenir qu'une candidature **En évaluation**, évaluée et au-dessus du seuil ;
  - classement décroissant des candidats par offre ;
  - une candidature **Refusée** ou **Retirée** ne peut **jamais** recevoir de proposition.
- **Cycle de vie (côté candidature)** :

```text
En évaluation ──retenir──► Retenue ──► (Proposition d'embauche)
En évaluation ──refuser──► Refusée
```

- **Écrans / API** : grille d'évaluation, synthèse, classement ;
  `POST /api/candidatures/{id}/evaluations`, `GET …/evaluations/synthese`,
  `GET /api/offres/{id}/classement`, `POST /api/candidatures/{id}/retenir|refuser|decision`.
- **À retenir** : c'est ici que se joue la **règle centrale** du projet : *seule une candidature
  Retenue peut recevoir une proposition*.

### 3.9 Proposition d'embauche et négociation

- **Objectif** : envoyer une offre au candidat retenu, gérer sa réponse et les **contre-propositions**
  (rémunération, date d'embauche, avantages…).
- **Acteurs** : `rh`, `recruteur`, `direction` (côté entreprise) ; le candidat (côté public).
- **Données** : `proposition_embauche`, `revendication`, `nature_revendication`,
  `status_proposition_embauche`, `status_revendication` + historiques, `grille_salariale`.
- **Services** : `PropositionEmbaucheService`, `RevendicationService`, `GrilleService`,
  `WorkflowService`.
- **Règles clés** :
  - création uniquement depuis une candidature **Retenue** ;
  - la rémunération proposée doit rester dans la **grille du poste** (y compris après contre-proposition) ;
  - une **revendication ouverte** (Soumise / En discussion) **bloque** l'acceptation ;
  - chaque tour de négociation est conservé (fil de discussion) ;
  - expiration possible si le délai est dépassé, sauf négociation en cours.
- **Cycle de vie de la proposition** :

```text
À envoyer ──envoyer──► Envoyée ──revendication──► En négociation
                          │                          │
                          ├──accepter──► Acceptée ◄──┤ (si aucune revendication ouverte)
                          ├──refuser──► Refusée      │
                          └──expirer──► Expirée ◄────┘
```

- **Cycle de vie d'une revendication** : `Soumise → En discussion → Acceptée / Refusée / Retirée`.
- **Écrans / API** : liste, fiche, formulaire, onglet « Négociation », page publique de réponse ;
  `GET|POST /api/propositions`, `POST /api/propositions/{id}/envoyer|repondre|contre-proposer|expirer`,
  `GET|POST /api/propositions/{id}/revendications`, `POST /api/revendications/{id}/repondre|retirer`.
- **À retenir** : à l'acceptation, le système enchaîne **création du salarié → création du
  contrat**. C'est le deuxième chaînage fort du projet.

### 3.10 Salarié et dossier administratif

- **Objectif** : faire du dossier salarié le **référentiel central** : identité, coordonnées,
  situation professionnelle, contrats, affectations, rémunération, documents, carrière, historique.
- **Acteurs** : `rh`, `admin` ; `manager` en lecture selon les droits ; le salarié voit ses
  propres pièces.
- **Données** : `salarie`, `status_salarie`, `salarie_status_historique`, `affectation`,
  `contrat`, `document`, `onboarding`, `employe`, `utilisateur`.
- **Services** : `SalarieService`, `MatriculeService`, `DossierService` (lecture agrégée),
  `DocumentService`, `DocumentPolicy`, `StockageService`.
- **Règles clés** :
  - un salarié naît d'une proposition **Acceptée** ; doublon interdit par candidat ;
  - matricule **unique** au format `MAT-AAAA-00001` ;
  - le statut du salarié suit le contrat **actif** : en attente → période d'essai ou actif → parti ;
  - `affectation` matérialise l'occupation d'un poste dans le temps ;
  - 4 niveaux de confidentialité des documents : `public`, `interne`, `rh`, `restreint` ;
  - pièces obligatoires : contrat, pièce d'identité, certificat médical, RIB ;
  - toute lecture de document sensible est **auditée**.
- **Cycle de vie** :

```text
En attente d'embauche ──contrat avec essai──► En période d'essai ──► Actif
En attente d'embauche ──contrat sans essai──► Actif
Tout état ──rupture──► Suspendu ──► Parti
```

- **Écrans / API** : liste, fiche à onglets (Identité, Contrats, Affectations, Documents,
  Carrière, Historique) ; `GET /api/salaries`, `GET /api/salaries/{id}`,
  `GET /api/salaries/{id}/dossier`, `GET /api/salaries/{id}/dossier/alertes`,
  `GET /api/salaries/{id}/carriere`, `POST /api/salaries/{id}/affectations`.
- **À retenir** : le dossier est **agrégé en lecture** ; toutes les écritures passent par les
  services spécialisés (Salarie, Contrat, Onboarding, Document).

### 3.11 Contrat et période d'essai

- **Objectif** : rédiger, signer et faire vivre le contrat de travail (CDI, CDD, stage,
  alternance…), avec ou sans période d'essai, avenants et renouvellements.
- **Acteurs** : `rh`, `admin`.
- **Données** : `contrat`, `type_contrat`, `status_contrat`, `contrat_status_historique`,
  `salarie`, `poste`, `grille_salariale`.
- **Services** : `ContratService`, `PeriodeEssaiService`, `GrilleService`.
- **Règles clés** :
  - le salaire de base doit rester dans la **grille du poste** ;
  - un contrat non permanent exige une **date de fin** ;
  - la période d'essai est **optionnelle** : sans mention écrite, il n'y en a pas ;
  - plafonds légaux :

| Contrat | Catégorie | Essai maximum |
|---|---|---|
| CDD / Stage ≤ 6 mois | — | 2 semaines |
| CDD / Stage > 6 mois | — | 1 mois |
| CDI | Ouvrier / employé | 2 mois |
| CDI | Agent de maîtrise / technicien | 3 mois |
| CDI | Cadre | 4 mois |

  - les stages antérieurs dans l'entreprise peuvent **réduire** l'essai ;
  - un seul contrat **En cours** à la fois par salarié ; plusieurs contrats successifs possibles ;
  - l'**avenant** couvre la mobilité interne (rattachement, missions, intitulé).
- **Cycle de vie** :

```text
En préparation ──soumettre──► En attente de signature ──signer──► Signé ──activer──► En cours
                                                                                   │
                                                              ┌──terme──► Terminé  │
                                                              └──rupture─► Résilié ┘
```

- **Écrans / API** : liste, fiche, formulaire (interrupteur période d'essai), aperçu, avenant ;
  `GET|POST /api/contrats`, `POST /api/contrats/{id}/soumettre|signer|activer|renouveler|avenant|mettre-fin`.
- **À retenir** : c'est l'activation du contrat qui **positionne le salarié** (essai ou actif) et
  qui **déclenche l'onboarding**.

### 3.12 Onboarding et documents

- **Objectif** : organiser l'intégration du nouveau salarié et vérifier que son dossier est complet.
- **Acteurs** : `rh`, `admin` ; le salarié voit ses tâches et ses documents.
- **Données** : `onboarding`, `onboarding_tache`, `document`, `type_document` + statuts +
  historiques.
- **Services** : `OnboardingService`, `DocumentService`, `JustificatifService` (congés),
  `StockageService`.
- **Règles clés** :
  - l'onboarding démarre à la **signature du contrat** ;
  - tâches par défaut : contrat signé, badge, compte, matériel, formation, inscription paie ;
  - une tâche suit `À faire → En cours → Terminée` (ou `Bloquée` / `Annulée`) ;
  - la progression = % de tâches terminées ;
  - clôture possible quand tout est terminé ; le salarié devient pleinement **Actif** ;
  - documents stockés hors `public/`, nom UUID, type MIME vérifié, 10 Mo max, checksum SHA-256 ;
  - validité des pièces calculée : `valide`, `expire_bientôt` (≤ 30 j), `expiré`, `sans expiration`.
- **Cycle de vie** :

```text
À démarrer ──► En cours ──► Terminé
     │            │  └──mettre en attente──► En attente ──► En cours
     └────────────┴──annuler──► Annulé
```

- **Écrans / API** : checklist, fiche, formulaire de tâche, dépôt de document, « Mes tâches »,
  « Mes documents » ; `GET|POST /api/onboardings`, `POST /api/onboardings/{id}/taches`,
  `POST /api/onboardings/taches/{id}/{action}`, `GET /api/salaries/{id}/documents`,
  `POST /api/salaries/{id}/documents`.
- **À retenir** : l'onboarding est le **pont** entre le recrutement et la vie quotidienne du
  salarié.

### 3.13 Congés et absences

- **Objectif** : gérer les droits, les demandes, les validations, le planning et l'impact paie.
- **Acteurs** : salarié (demande), manager (1er niveau), RH (validation finale), direction/admin.
- **Données** :
  - référentiels : `type_conge`, `statut_demande_conge`, `type_absence`, `jour_ferie`,
    `droit_conge`, `delegation_validation` ;
  - métier : `solde_conge`, `demande_conge`, `absence`, `justificatif_absence`,
    `mouvement_solde` ;
  - lecture : **15 vues** (`v_solde_conge_salarie`, `v_planning_absences`, `v_alertes_solde`…).
- **Services** : `CalendrierService`, `ControleDemandeService`, `SoldeService`, `CongeService`,
  `AbsenceService`, `PaieCongeService`, `JustificatifService`, `NotificationService`,
  `AuditService`.
- **Règles clés** (RG01 → RG26) :
  - contrat **actif** obligatoire ;
  - dates cohérentes, demi-journées autorisées selon le type ;
  - `nb_jours` = jours **ouvrés** (hors week-ends et jours fériés) ;
  - délai de prévenance, ancienneté minimale, durée maximale, non-chevauchement ;
  - solde disponible suffisant ; jours **réservés** à la demande, **débités** à la validation,
    **recrédités** au refus/annulation ;
  - acquisition **mensuelle**, report limité, expiration du surplus, année à cheval répartie ;
  - validation en **deux niveaux** (manager puis RH), jamais sa propre demande ;
  - refus avec **motif obligatoire** ;
  - congé validé → création d'**absences** + blocage du planning + variables de paie ;
  - congé payé = pas de retenue ; sans solde / injustifié = retenue ;
  - maladie non justifiée à temps → requalifiée en **absence injustifiée**.
- **Cycle de vie** :

```text
EN_ATTENTE_MANAGER ──valider manager──► EN_ATTENTE_RH ──valider RH──► VALIDEE
        │                                    │                          │
        └──refuser──► REFUSEE ◄──────────────┘                          │
        └──annuler──► ANNULEE ◄─────────────────────────────────────────┘ (avant le début)
```

- **Écrans / API** : « Mes congés », nouvelle demande, validations, planning, soldes (RH),
  absences (RH) ; `GET|POST /api/conges/demandes`, `POST /api/conges/demandes/{id}/valider|refuser|annuler`,
  `GET /api/conges/a-valider`, `GET /api/conges/planning`, `GET /api/conges/soldes`,
  `POST /api/conges/soldes/ajuster`, `POST /api/conges/absences`.
- **Tâches planifiées** : `php spark conges:taches`, chaque jour, enchaîne relance, requalification,
  acquisition (1er du mois) et clôture d'année (1er janvier).
- **À retenir** : c'est le module le plus **règlementaire** ; il ne supprime jamais rien, il
  historise chaque mouvement de solde.

### 3.14 Temps de travail et pointage

- **Objectif** : suivre les horaires prévus et les pointages réels, calculer les écarts.
- **Acteurs** : `rh`, `admin` (vue globale) ; chaque salarié (son calendrier).
- **Données** : `temps_planning` (jour, début, fin, pause, régime), `temps_pointage`
  (entrée / sortie).
- **Services** : `PointageAutomatique` (entrée à la connexion, sortie à la déconnexion, heure de
  Madagascar), `TempsController`.
- **Règles clés** :
  - un seul planning par salarié et par jour ; `fin > début` ; pause cohérente ;
  - un pointage est `entree` ou `sortie`, unique par salarié et instant ;
  - les anomalies (retard, absence de sortie, écart) sont signalées dans le calendrier.
- **Écrans / API** : `/temps/calendrier`, « Mon calendrier de pointage » sur le dashboard ;
  `GET /api/temps/calendrier`, `GET /api/temps/mon-calendrier`,
  `POST /api/temps/planning`, `POST /api/temps/pointage`, `GET|POST /api/temps/taux`.
- **À retenir** : le pointage est **automatique** mais reste consultable et corrigeable par les RH.

### 3.15 Paie et finances

- **Objectif** : calculer et figer les bulletins mensuels, gérer les éléments variables et les
  taux de cotisation.
- **Acteurs** : `rh`, `admin`, `finance`.
- **Données** : `bulletin_paie`, `element_paie`, `taux_cotisation`, vue `v_bulletins_mois`.
- **Services** : `BulletinService`, `CotisationService`, `ElementPaieService`,
  `PaieCongeService`, `GrilleSalarialeService`.
- **Formule de calcul** (documentée dans le service) :

```text
base_proratée    = salaire_base × couverture / jours du mois
taux_journalier  = base_proratée / jours ouvrés du mois
retenue_congés   = taux_journalier × (jours sans solde + jours injustifiés)
brut             = base_proratée + Σ éléments de paie − retenue_congés
cotisations      = brut × taux employé / total / 100
net              = brut − cotisations
coût patronal    = brut × taux patronal / 100
```

- **Règles clés** :
  - un bulletin est unique par salarié et par mois ;
  - une fois **validé**, le bulletin est **figé** (snapshot des éléments) ;
  - les congés/absences du mois alimentent automatiquement les retenues ;
  - la grille salariale encadre le salaire de base.
- **Écrans / API** : « Gestion paie », aperçu détaillé imprimable ;
  `GET /api/paie/bulletins`, `POST /api/paie/bulletins/calculer`,
  `GET /api/paie/bulletins/{id}/detail`, `POST /api/paie/bulletins/{id}/valider`,
  `GET|POST /api/paie/elements`, `GET /api/paie/taux`.
- **À retenir** : le calcul est **reproductible** et la validation **irréversible** (on ne modifie
  pas un bulletin validé, on en crée un nouveau).

### 3.16 Sécurité, confidentialité et audit

- **Objectif** : garantir que chacun ne voit que ce qu'il doit voir, et garder une trace de tout.
- **Acteurs** : `admin`, `rh` ; tout utilisateur pour ses propres données.
- **Données** : `journal_audit`, `action`, `document` (niveau de confidentialité), `utilisateur`.
- **Services / bibliothèques** : `AuditService`, `DocumentPolicy`, `SessionUtilisateur`,
  `AuthFilter`, `RoleFilter`, `AccesInformatiqueFilter`.
- **Règles clés** :
  - matrice documentaire : `admin`/`rh` = tout ; `direction` = public/interne/rh ;
    `manager`/`recruteur`/`controle` = public/interne ; `salarié` = ses propres pièces ;
  - appartenance résolue par `utilisateur.id_employe → employe.id_salarie` ;
  - téléchargement de document contrôlé et journalisé ;
  - suppression = archivage logique, jamais d'effacement brutal ;
  - audit des lectures sensibles et de toutes les actions de gestion.
- **Écrans / API** : `/securite/audit`, `/securite/acces` ;
  `GET /api/documents/expirant`, `GET /api/documents/{id}/telecharger`,
  `DELETE /api/documents/{id}`.
- **À retenir** : la sécurité est **à deux niveaux** : interface (menu filtré) + serveur (filtre et
  service). Le second est le seul qui compte.

### 3.17 Département informatique

- **Objectif** : donner au service informatique un espace de support pour les comptes.
- **Acteurs** : `admin`, `rh`, salariés du département **Informatique**.
- **Données** : `utilisateur`, `employe`, `departement`, `journal_audit`.
- **Services** : `UtilisateurService`, `AuthService`.
- **Règles clés** :
  - accès conditionné par le **département** de l'employé (filtre `accesInformatique`) ;
  - création de compte, réinitialisation de mot de passe ;
  - toute action est auditée.
- **Écrans / API** : `/departement/informatique`,
  `/departement/informatique/creer-utilisateur`,
  `/departement/informatique/reinitialiser-mdp`.
- **À retenir** : c'est un **guichet délégué** ; il ne contourne pas les règles de sécurité, il les
  applique dans un périmètre restreint.

---

## 4. Parcours complet de bout en bout

Voici l'histoire d'un recrutement, avec les **statuts** à chaque étape :

| # | Étape | Module | Acteur | Résultat |
|---|---|---|---|---|
| 1 | Le manager exprime un besoin | Demande | `manager` | Demande **En cours** |
| 2 | Contrôle de cohérence | Demande | `rh` | **En validation budgétaire** |
| 3 | Validation du budget | Demande | `controle_gestion` | **En validation hiérarchique** |
| 4 | Feu vert | Demande → Offre | `direction` | Demande **Validée** + offre **Brouillon** |
| 5 | Publication | Offre | `rh`/`recruteur` | Offre **Publiée** |
| 6 | Dépôt de candidature | Candidature | candidat | Candidature **Reçue** |
| 7 | Présélection | Candidature | `rh` | **Présélectionnée** |
| 8 | Planification | Entretien | `recruteur` | **Entretien prévu** |
| 9 | Entretien réalisé | Entretien | `recruteur` | Candidature **En évaluation** |
| 10 | Évaluation + décision | Évaluation | `rh`/`direction` | Candidature **Retenue** |
| 11 | Proposition | Proposition | `rh` | **Envoyée** |
| 12 | Négociation éventuelle | Proposition | candidat + RH | **En négociation** → **Envoyée** |
| 13 | Acceptation | Proposition → Salarié | candidat | Proposition **Acceptée**, salarié créé |
| 14 | Contrat | Contrat | `rh` | **En préparation** → **Signé** → **En cours** |
| 15 | Onboarding | Onboarding | `rh` | Tâches créées, progression, **Terminé** |
| 16 | Vie du salarié | Congés / Temps / Paie | tous | Demandes, pointages, bulletins |

**Les deux verrous à retenir** :

- `Proposition` refuse toute candidature qui n'est pas **Retenue**.
- `Congé` refuse toute demande si le **solde disponible** ne couvre pas les jours.

---

## 5. Matrice rôles × modules

Légende : **✔** accès métier, **L** lecture seule, **–** aucun accès.

| Module | admin | rh | manager | direction | controle | recruteur | salarie | finance |
|---|---|---|---|---|---|---|---|---|
| Tableau de bord | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Demandes de recrutement | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | – | – |
| Offres d'emploi | ✔ | ✔ | – | – | – | ✔ | – | – |
| Candidatures | ✔ | ✔ | – | – | – | ✔ | – | – |
| Entretiens | ✔ | ✔ | – | – | – | ✔ | – | – |
| Évaluations / sélection | ✔ | ✔ | – | ✔ | – | ✔ | – | – |
| Propositions | ✔ | ✔ | – | ✔ | L | ✔ | – | – |
| Salariés / dossier | ✔ | ✔ | L | – | – | – | Ses données | – |
| Contrats | ✔ | ✔ | – | L | – | – | Ses contrats | – |
| Onboarding | ✔ | ✔ | – | – | – | – | Ses tâches | – |
| Congés (demande) | ✔ | ✔ | ✔ | ✔ | – | – | ✔ | – |
| Congés (validation) | ✔ | ✔ | ✔ | ✔ | – | – | – | – |
| Congés (soldes RH) | ✔ | ✔ | – | – | – | – | – | – |
| Temps / pointage | ✔ | ✔ | – | – | – | – | Son calendrier | – |
| Paie | ✔ | ✔ | – | – | – | – | – | ✔ |
| Sécurité / audit | ✔ | ✔ | – | – | – | – | – | – |
| Département informatique | ✔ | ✔ | – | – | – | – | Si département | – |

> Cette matrice est **indicative** : la source de vérité reste les routes (`app/Config/Routes.php`)
> et les filtres (`auth`, `role:…`, `accesInformatique`), revalidés dans les services.

---

## 6. Les règles d'or du projet

1. **Une seule porte d'entrée par action** : chaque action passe par un service, jamais directement
   par un modèle depuis un contrôleur.
2. **Le statut est une machine à états** : toute transition est contrôlée par un `WorkflowService`
   et historisée.
3. **Transaction ou rien** : une opération multi-tables réussit entièrement ou se rollback.
4. **On n'efface pas** : refus, annulation, archivage ; l'historique reste.
5. **Le serveur décide** : l'interface masque, le serveur vérifie.
6. **Les chiffres viennent de la base** : pas de compteur codé en dur.
7. **Les référentiels sont partagés** : un seul endroit pour les statuts, types et grilles.
8. **Les documents sont sensibles** : stockage hors racine web, contrôle d'accès, audit des lectures.
9. **La paie est figée** : un bulletin validé n'est plus modifié.
10. **Chaque livraison est traçable** : PR, tests, `todo.md` coché.

---

## 7. État d'avancement et points ouverts

### Acquis

- Socle CodeIgniter + PostgreSQL + authentification + rôles + audit.
- Recrutement complet : demande → offre → candidature → entretien → évaluation → proposition.
- Embauche : salarié → contrat → onboarding, avec période d'essai et matricule.
- Dossier administratif : identité, contrats, documents, confidentialité, alertes d'expiration.
- Congés : workflow deux niveaux, soldes, absences, planning, impact paie, commandes planifiées.
- Temps : planning, pointage automatique, calendrier.
- Paie : bulletins, éléments, taux, retenues issues des congés.
- Interface : design system centralisé (`personnel.css`), layout responsive, pages publiques.

### Points ouverts / dettes identifiées

| Sujet | État | Piste |
|---|---|---|
| Notifications | Journalisées / service présent | Ajouter une table `notification` ou un envoi e-mail réel |
| Upload définitif des documents | Fonctionnel | Purge physique réservée admin, rétention à définir |
| Lien avenant ↔ contrat parent | Partiel | Ajouter `id_contrat_parent` |
| Taux de présence bloquant | Alerte | Décider bloquant ou non (RG19) |
| Demi-journées en paie | Approximées | Compter 0,5 jour dans `v_variables_paie_conges` |
| Tests de bout en bout | Partiels | Scénario complet demande → bulletin |
| Rôles fins par département | Filtre `accesInformatique` | Étendre à d'autres départements si besoin |

> Les documents `todo*.md` restent la référence pour le suivi détaillé des tâches.

---

## 8. Carte des fichiers

```text
app/
├── Config/Routes.php                 → toutes les routes (pages + API)
├── Controllers/                      → HTTP : Auth, Admin, Recrutement, Selection,
│                                       Personnel, Finance, Paie, Temps, Dashboard, Pages
├── Services/                         → logique métier, un dossier par domaine
├── Models/                           → accès aux données (un modèle par table)
├── Entities/                         → objets métier typés
├── Libraries/                        → Auth (hash, session), Common (audit, rôles,
│                                       workflow, tri), Personnel (documents),
│                                       Temps (pointage)
├── Filters/                          → AuthFilter, RoleFilter, AccesInformatiqueFilter
├── Views/
│   ├── layouts/                      → app, auth, public
│   ├── partials/                     → head, header, sidebar, breadcrumb, filtres, tabs
│   └── pages/                        → un dossier par domaine
├── Commands/                         → tâches planifiées Congés
└── Database/                         → migrations / seeds (si utilisés)

public/assets/
├── css/personnel.css                 → design system complet
└── js/                               → api.js, ui.js, table.js, pages/*

conception/                           → conception détaillée par module + vues
script/db/                            → schéma, seeds, migrations, correctifs
todo*.md                              → suivi des blocs et des tâches
```

### Documents de référence

| Document | Contenu |
|---|---|
| `RH.md` | Règles métier initiales (recrutement, contrats, essai) |
| `conception/Auth.md` | Login, sessions, audit |
| `conception/DemandeRecrutement.md` | Workflow de validation |
| `conception/OffreEmploi.md` | Cycle de vie de l'offre |
| `conception/Candidature.md` | Dépôt, mobilité, transitions |
| `conception/Entretien.md` | Planification et comptes rendus |
| `conception/EvaluationCandidat.md` | Grille, moyenne, seuil |
| `conception/PropositionEmbauche.md` | Proposition + négociation |
| `conception/Salarie.md` | Création et statuts |
| `conception/Contrat.md` | Contrats et période d'essai |
| `conception/Onboarding.md` | Intégration et documents |
| `todo-bloc5-dossier-administratif.md` | Dossier, confidentialité, stockage |
| `todo-bloc7-gestion-Congés.md` | Congés : tables, RG01→RG26, services |
| `script/db/README.md` | Ordre d'exécution SQL, comptes de démo |
| `script/taches-planifiees.md` | Cron des tâches Congés |
| `conception/vues/*.md` | Maquettes et conventions d'écran |

---

## 9. Le projet expliqué en 5 minutes

**1. Le problème.** Une entreprise doit recruter, intégrer, administrer et payer ses salariés.
Chaque étape produit des données qui doivent rester cohérentes : un candidat retenu devient un
salarié, un contrat signé déclenche un onboarding, un congé validé impacte la paie.

**2. La réponse.** Un SIRH modulaire, en trois temps :

- **Recruter** : demande → offre → candidature → entretien → évaluation → proposition.
- **Intégrer** : salarié → contrat → onboarding → dossier administratif.
- **Faire vivre** : congés, temps, paie, documents, audit.

**3. L'architecture.** Chaque module suit le même schéma : une **vue**, un **contrôleur**, un
**service** qui porte les règles, des **modèles** qui parlent à PostgreSQL. Les statuts sont des
machines à états historisées. Les modules communiquent par les **données** (statuts, clés
étrangères), pas par des appels croisés.

**4. Les garanties.** Rien ne se perd (historiques, audit), rien ne se fait sans contrôle (rôles,
transactions, seuils), et chaque décision est explicable (qui, quand, pourquoi).

**5. La démonstration type.**

```text
1. Manager : créer une demande de recrutement
2. RH : contrôler la cohérence et valider
3. Contrôle de gestion : valider le budget
4. Direction : donner le feu vert  → une offre est créée
5. RH : publier l'offre
6. Candidat : postuler en ligne
7. RH : présélectionner, planifier un entretien
8. Recruteur : réaliser l'entretien → candidature en évaluation
9. RH : évaluer, retenir
10. RH : envoyer une proposition, le candidat négocie puis accepte
11. Système : crée le salarié (matricule) et le contrat
12. RH : signer et activer le contrat → onboarding démarré
13. RH : valider les tâches d'onboarding
14. Salarié : demander un congé → validation manager → validation RH
15. RH : calculer et valider le bulletin du mois
```

**La phrase à retenir** : *« Gestion RH transforme un besoin de recrutement en un salarié intégré,
payé et suivi, avec une trace fiable de chaque décision. »*

---

*Document de synthèse — à maintenir à jour à chaque évolution majeure. Les détails d'implémentation
restent dans `conception/*.md`, `app/Services/*` et `script/db/*`.*
