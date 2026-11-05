# Todo — Gestion RH (4 pistes indépendantes)

Répartition entre **Iavo**, **Bidy**, **Elisa** et **Princio**, organisée pour que **chaque piste
soit autonome** : aucune piste n'attend le code d'une autre.

## Principe d'indépendance

1. **Bootstrap commun, one-shot** : une seule séance collective au démarrage (squelette, entités,
   repositories, contrats d'API). Une fois mergé sur `main`, il ne bloque plus personne.
2. **Pistes verticales** : chaque personne fait *entités → services → API → vues → tests* sur son
   périmètre.
3. **Données d'entrée = fixtures SQL** : chaque piste insère elle-même ses données de départ
   (ex. une candidature « Retenue »), sans dépendre du code d'une autre piste.
4. **Pas d'appel de service entre pistes** : une piste lit l'état dont elle a besoin **directement
   en base** (son propre repository). Les échanges se font par l'état des tables, pas par du code
   partagé.
5. **Contrats figés** : DTO et endpoints définis au bootstrap ; chacun code contre ces contrats.
6. **Intégration = vérification**, pas un prérequis : les 4 pistes peuvent être livrées et démontrées
   séparément.

> Le workflow métier reste chaîné (Candidature → Proposition → Salarié), mais **le chaînage est
> porté par les données** (statuts en base), pas par une dépendance de tâches.

---

## Phase 0 — Bootstrap collectif (une seule fois, tous ensemble)

À faire en une séance, puis merger sur `main`. Chacun prend les tables de son domaine.

- [ ] **T0.1** — Relire `RH.md`, `conception/*.md`, `conception/vues/*.md` ; figer le **contrat d'API**
  (endpoints + DTO de chaque module).
- [ ] **T0.2** — `composer.json` (`codeigniter4/framework`, `codeigniter4/settings`, `phpunit`) +
  `.env` (datasource `gestion_rh`, `baseURL`, clé de chiffrement).
- [ ] **T0.3** — Arborescence CI4 : `app/Controllers`, `app/Models`, `app/Entities`,
  `app/Database/Migrations`, `app/Filters`, `app/Config`, `app/Libraries`, `app/Helpers`.
- [ ] **T0.4** — Servir `web/` en statique via `public/` ; configurer CORS (`Config\Cors` + filtre).
- [ ] **T0.5** — **Entités + Models (repositories) de toutes les tables**, répartis :
  - Iavo : référentiels (`departement`, `service`, `poste`, `grille_salariale`, `rang`, `type_*`,
    `status*`, `action`), `utilisateur`, `employe`, `journal_audit`.
  - Bidy : `demande_recrutement`, `validation`, `offre_emploi`, `candidat`, `candidature` + historiques.
  - Elisa : `entretien`, `evaluation_candidat`, `proposition_embauche`, `revendication`,
    `nature_revendication`, `status_revendication` + historiques.
  - Princio : `salarie`, `contrat`, `affectation`, `onboarding`, `onboarding_tache`, `document`
    + historiques.
- [ ] **T0.6** — `common` : format d'erreur (filtre + gestionnaire d'exceptions `Config\Exceptions`),
  `WorkflowService` générique, `HistoriqueModel` générique, `AuditService`, `SessionUtilisateur`.
- [ ] **T0.7** — Filtres `AuthFilter` + `RoleFilter` et hachage `password_hash`/`password_verify`
  (bcrypt, squelette) ; `api.js`, layout et composants CSS communs.
- [ ] **T0.8** — Merger le bootstrap sur `main` ; à partir d'ici, **plus aucune dépendance entre pistes**.
- [ ] **T0.9** — Créer `script/fixtures_auth.sql`, `fixtures_recrutement.sql`, `fixtures_selection.sql`,
  `fixtures_embauche.sql` (squelettes).

---

## Piste A — Iavo : Auth, utilisateurs, référentiels, dashboard

**Données d'entrée** : `script/fixtures_auth.sql` (comptes, employés). **Aucune dépendance externe.**

- [ ] **A1** — Filtres d'authentification complets + hachage `password_hash`/`password_verify`
  (bcrypt) + session/JWT.
- [ ] **A2** — `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/session`.
- [ ] **A3** — `PATCH /api/auth/mot-de-passe`.
- [ ] **A4** — CRUD `utilisateurs` (admin) : liste, création, modification, activer/désactiver,
  réinitialiser ; garde-fou « dernier admin ».
- [ ] **A5** — `exigerRole(...)` + matrice des rôles.
- [ ] **A6** — `AuditService` branché sur les actions d'auth/admin.
- [ ] **A7** — Endpoints référentiels en lecture (postes, services, types, statuts…) pour les autres pistes.
- [ ] **A8** — Dashboard agrégé : compteurs calculés **en SQL** sur les tables (pas d'appel aux autres pistes).
- [ ] **A9** — Vues : `login.html`, `mot-de-passe.html`, `admin/utilisateurs*.html`, `dashboard.html`.
- [ ] **A10** — Layout, menu par rôle, `api.js`/`auth.js`/`ui.js`, composants communs.
- [ ] **A11** — Tests : connexion, rôles, désactivation dernier admin.

**Livrable démontrable** : connexion + gestion des comptes + dashboard + référentiels.

---

## Piste B — Bidy : Recrutement (Demande → Offre → Candidature)

**Données d'entrée** : `script/fixtures_recrutement.sql` (départements, services, postes, grille,
employés, statuts). **Aucune dépendance externe.**

- [ ] **B1** — `DemandeRecrutementService` : `creer`, `modifier`, `soumettre`, `consulter`, `lister`,
  `annuler`, `historique`.
- [ ] **B2** — `CoherenceService` : poste, grille salariale, contrat/motif, mobilité interne.
- [ ] **B3** — Décisions : `controlerRh`, `validerBudget`, `validerHierarchie`, `demanderConsultation`,
  `cloturerConsultation`, `donnerFeuVert`.
- [ ] **B4** — `OffreEmploiService` : `creerDepuisDemande`, `modifier`, `publier`, `suspendre`,
  `reprendre`, `clore`, `annuler`, `offresOuvertes`.
- [ ] **B5** — `donnerFeuVert` → `creerDepuisDemande` (dans la même transaction, **piste interne**).
- [ ] **B6** — `CandidatureService` : `postuler`, `trouverOuCreerCandidat`, `retirer`,
  `mettreEnPreselection`, `preselectionner`, `planifierEntretien`, `marquerEvaluee`, `retenir`, `refuser`.
- [ ] **B7** — `EligibiliteService.verifierEligibiliteMobilite` + anti-doublon + contrôle offre publiée.
- [ ] **B8** — Endpoints `/api/demandes...`, `/api/offres...`, `/api/candidatures...`,
  `/api/public/offres...`.
- [ ] **B9** — Vues : demandes (liste/form/fiche/à traiter), offres (liste/fiche/form/vitrine),
  candidatures (form public/liste/fiche/suivi).
- [ ] **B10** — Tests : transitions, cohérence, anti-doublon, éligibilité, endpoints publics.

**Livrable démontrable** : parcours complet demande → offre publiée → candidatures.

---
 
## Piste C — Elisa : Sélection (Entretien → Évaluation → Proposition + Négociation)

**Données d'entrée** : `script/fixtures_selection.sql` — insère elle-même une **candidature
« Retenue »** (candidat, offre, candidature) et les employés évaluateurs.
**Aucune dépendance au code de Bidy** : Elisa lit `candidature` via son propre repository.

- [ ] **C1** — `EntretienService` : `planifier`, `confirmer`, `reporter`, `realiser`, `annuler`,
  `consulter`, `lister`, `historique`, `planifierPourOffre`.
- [ ] **C2** — Lien candidature (lecture/écriture directe en base) : `Présélectionnée → Entretien prévu` ;
  `realiser` → `En évaluation`.
  `listerParCandidature`, `classerPourOffre` (+ convention JSON des critères).
- [ ] **C4** — `SelectionService` : `seuilRetention`, `candidatsEligibles`, `retenir`, `refuser`, `decider`.
- [ ] **C5** — `PropositionEmbaucheService` : `creerDepuisCandidature` (contrôle **Retenue** direct en base),
  `envoyer`, `enregistrerReponse`, `contreProposer`, `marquerExpiree`, `lister`, `historique`.
- [ ] **C6** — `RevendicationService` : `soumettre`, `repondre`, `appliquerAcceptees`, `retirer`,
  `lister`, `ouvertes` + garde-fou d'acceptation.
- [ ] **C7** — `GrilleService.remunerationValide` (+ après contre-proposition).
- [ ] **C8** — Endpoints `/api/entretiens...`, `/api/candidatures/{id}/evaluations`,
  `/api/propositions...`, `/api/revendications...`, publics de réponse/revendication.
- [ ] **C9** — Vues : agenda entretiens, grille/synthèse/classement, propositions + onglet Négociation,
  page publique de réponse.
- [ ] **C10** — Tests : règle Retenue, seuil, cycle de négociation.

**Livrable démontrable** : entretien → évaluation → proposition acceptée (avec négociation).

---

## Piste D — Princio : Embauche (Salarié → Contrat → Onboarding)

**Données d'entrée** : `script/fixtures_embauche.sql` — insère elle-même une **proposition
« Acceptée »** (+ candidature Retenue, candidat, offre).
**Aucune dépendance au code d'Elisa** : Princio lit `proposition_embauche`/`candidature` via ses repos.

- [x] **D1** — `SalarieService` : `creerDepuisCandidature` (contrôle proposition **Acceptée** direct en base),
  `modifier`, `affecter`, `listerAffectations`, `changerStatut`, `consulter`, `lister`, `historique`.
- [x] **D2** — `MatriculeService.genererMatricule` (`MAT-AAAA-00001`, unicité).
- [x] **D3** — `ContratService` : `creerContrat`, `modifier`, `soumettrePourSignature`, `signer`,
  `activer`, `mettreFin`, `renouveler`, `creerAvenant`.
- [x] **D4** — `PeriodeEssaiService` : `categorieDepuisPoste`, `calculer`, `dureeMaxJours`,
  `deduireStages`, `validerDuree` (essai **optionnel**, plafonds CDD/CDI).
- [x] **D5** — `activer` → salarié **En période d'essai** ou **Actif** ; `mettreFin` → **Parti**.
- [ ] **D6** — `OnboardingService` : `demarrer`, `creerTachesParDefaut`, `creerTache`, `demarrerTache`,
  `realiserTache`, `bloquerTache`, `annulerTache`, `progression`, `cloturer`, `annuler`.
- [ ] **D7** — `DocumentService` : `ajouter`, `lister`, `supprimer`.
- [ ] **D8** — Endpoints `/api/salaries...`, `/api/contrats...`, `/api/onboardings...`,
  `/api/salaries/{id}/documents`.
- [ ] **D9** — Vues : salariés (dossier à onglets), contrats (form avec interrupteur essai, fiche,
  avenant), onboarding (checklist, progression, documents).
- [ ] **D10** — Tests : période d'essai, matricule, cycle contrat, onboarding.

**Livrable démontrable** : salarié créé → contrat signé/activé → onboarding terminé.

---

## Fichiers partagés (règles anti-conflit)

| Fichier / dossier | Propriétaire | Règle |
|---|---|---|
| `composer.json`, `.env`, `app/Config/`, `app/Filters/`, `app/Libraries/`, `app/Helpers/` | Iavo | Modifs après le bootstrap → PR + revue |
| `css/base.css`, `layout.css`, `components.css`, `js/api.js`, `js/auth.js`, `js/ui.js` | Iavo | Composants communs ; demandes via issue |
| `js/pages/personnel/` | Princio | — |
| `js/pages/recrutement/demandes*`, `offres*`, `candidatures*` | Bidy | — |
| `js/pages/recrutement/entretiens*`, `evaluations*`, `propositions*` | Elisa | — |
| `js/pages/admin/`, `login.html`, `dashboard.html` | Iavo | — |
| `script/fixtures_*.sql` | Piste concernée | Ne pas modifier celles des autres |
| `todo.md` | Tous | Cocher ses tâches à chaque PR |

> Conflit sur `RecrutementController`/dossier `recrutement` : chaque piste crée **ses propres
> fichiers** (`DemandeController`, `OffreController`, `EntretienController`, `PropositionController`).

---

## Intégration finale (vérification, pas dépendance)

- [ ] **J5.1** — Remplacer les fixtures par un parcours réel de bout en bout.
- [ ] **J5.2** — Vérifier les liens inter-pistes par l'état en base (ex. proposition Acceptée → salarié).
- [ ] **J5.3** — Dashboard d'Iavo : vérifier les compteurs sur données réelles.
- [ ] **J5.4** — Revue croisée + CI verte.

---

## Definition of Done (par tâche)

- [ ] `composer test` (PHPUnit) et `composer lint` (php-cs-fixer) passent.
- [ ] Règles métier et rôles (`exigerRole`) appliqués.
- [ ] Action tracée (historique + `journal_audit`).
- [ ] Format d'erreur commun respecté.
- [ ] Tests unitaires + intégration.
- [ ] Vue branchée (si applicable).
- [ ] Revue par un pair, PR mergée, `todo.md` mis à jour.

---

## Règles de collaboration

- 1 branche par tâche : `feat/<piste>-<tache>` ; PR obligatoire, revue croisée
  (Iavo ↔ Bidy, Elisa ↔ Princio).
- **Contrat d'API d'abord** (bootstrap), puis chacun code contre le contrat.
- Pas d'appel de service entre pistes : lecture/écriture via la base (statuts, FK).
- Ne pas modifier le schéma sans `script/migration_*.sql`.
- Les fichiers partagés ne changent qu'en PR dédiée.


je suis le roi du monde et tu  cr,


salaire brute * congee non prix /30