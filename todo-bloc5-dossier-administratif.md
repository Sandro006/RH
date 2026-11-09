# Todo — Bloc 5 : Dossier administratif du salarié

Branche : `module-Dossier-Administartif-Salarie`
Références : `RH.md` §II.B.2–3, `conception/Salarie.md`, `conception/vues/08-salarie.md`,
`script/db/01_migrations.sql`, `script/db/02_seeds.sql`.

Objectif : faire du dossier salarié le **référentiel central du SIRH** en agrégeant
identité, coordonnées, situation professionnelle, contrats, affectations, rémunération,
documents et historique de carrière — avec confidentialité, habilitations et audit.

Légende :
- **P0** = bloquant pour la démo / la sécurité
- **P1** = nécessaire pour un dossier complet
- **P2** = amélioration si le temps le permet
- `[x]` déjà fait, `[~]` partiellement fait, `[ ]` à faire

---

## 0. État des lieux

- [x] D1 — `SalarieService` : création depuis candidature, modification, affectation, statut, historique.
- [x] D2 — `MatriculeService` : format `MAT-AAAA-00001` + unicité.
- [x] D3 — `ContratService` : création, signature, activation, fin, renouvellement, avenant.
- [x] D4 — `PeriodeEssaiService` : calcul et validation de la période d'essai.
- [x] D5 — Transitions salarié : `En période d'essai` / `Actif` / `Parti`.
- [ ] D6 — `OnboardingService` complet (hors cœur du Bloc 5, mais le dossier doit afficher l'onboarding).
- [~] D7 — `DocumentService` : ajout / liste / suppression existent, mais **sans upload réel ni validité**.
- [~] D8 — Endpoints salarié/contrat existent ; il manque le dossier agrégé et les documents.
- [~] D9 — Onglets présents dans `salarie.php`, mais `salarie.js` affiche un tableau générique.

Constats à corriger :
- [x] **P0** — `document-form.js` envoie désormais un vrai `multipart/form-data` avec le fichier.
- [x] **P0** — Les routes documents sont protégées par le filtre `auth`.
- [x] **P1** — `DocumentService` journalise les lectures de documents sensibles (`rh`/`restreint`).
- [x] **P1** — Contrôle d'appartenance via `DocumentPolicy` (employé → salarié).
- [x] **P1** — `salarie` contient l'état civil complet et l'adresse.
- [x] **P1** — `type_document` porte le caractère obligatoire et la durée de validité.
- [ ] **P2** — Pas de lien explicite entre un avenant et son contrat parent.

---

## 1. Décisions à prendre avant de coder

- [x] **B5-01 (P0)** — Champs d'identité retenus par défaut : `date_naissance`, `lieu_naissance`,
  `nationalite`, `situation_familiale`, `adresse`, `ville`, `pays`, `date_sortie`.
- [x] **B5-02 (P0)** — Matrice de confidentialité retenue : `public`, `interne`, `rh`, `restreint`.
- [x] **B5-03 (P0)** — Pièces obligatoires retenues : contrat de travail, pièce d'identité,
  certificat médical, RIB. Les autres types restent facultatifs.
- [x] **B5-04 (P0)** — Stockage retenu : `writable/uploads/documents/` + nom UUID, hors `public/`.
- [ ] **B5-05 (P1)** — Définir la rétention/archivage (suppression logique, durée de conservation).
- [ ] **B5-06 (P0)** — Figer le contrat d'API du dossier (sections et noms JSON).
- [ ] **B5-07 (P1)** — Écrire le scénario de démonstration (voir §8).

> Tant que B5-01 à B5-04 ne sont pas tranchés, ne pas modifier le schéma.

---

## 2. Base de données et modèles

### 2.1 Migration

- [x] **B5-10 (P0)** — Créer `script/db/04_migration_dossier_administratif.sql` (idempotent).
- [x] **B5-11 (P0)** — Répercuter les changements dans `script/db/01_migrations.sql` (installation neuve).
- [x] **B5-12 (P0)** — Ajouter les colonnes `salarie` (identité, coordonnées, `date_sortie`).
- [x] **B5-13 (P0)** — Ajouter à `type_document` : `obligatoire`, `duree_validite_mois`,
  `niveau_confidentialite`, `actif`.
- [x] **B5-14 (P0)** — Ajouter à `document` : `date_emission`, `date_expiration`,
  `niveau_confidentialite`, `id_utilisateur_depot`, `mime_type`, `taille_octets`,
  `checksum_sha256`, `remplace_par`, `archive`.
- [x] **B5-15 (P1)** — Ajouter les FK manquantes (`id_utilisateur_depot`, `remplace_par`) et
  les index (`id_salarie`, `date_expiration`, `type_document`).
- [x] **B5-16 (P1)** — Mettre à jour `script/db/02_seeds.sql` : types de documents avec
  obligation, validité et confidentialité par défaut.
- [x] **B5-17 (P2)** — Script de migration pour les bases existantes : `04_migration_dossier_administratif.sql`.

### 2.2 Entités et modèles

- [x] **B5-20 (P0)** — `app/Entities/Personnel/Salarie.php` : casts des nouveaux champs.
- [x] **B5-21 (P0)** — `app/Models/Personnel/SalarieModel.php` : `allowedFields`.
  `SalarieService::modifier` accepte désormais les champs d'identité ; `consulter`/`lister`
  les retournent.
- [x] **B5-22 (P0)** — `app/Entities/Personnel/Document.php` : casts dates/entiers + `toPublicArray()`.
- [x] **B5-23 (P0)** — `app/Models/Personnel/DocumentModel.php` : `allowedFields`,
  `documentsActifs`, `documentsExpirant`, `documentsExpires`, `documentsParType`, `listerAvecType`.
- [x] **B5-24 (P0)** — `app/Models/Personnel/TypeDocumentModel.php` : lecture des métadonnées
  (obligatoire, validité, confidentialité).
- [ ] **B5-25 (P2)** — Table `habilitation` si B5-02 retient des habilitations nominatives.

---

## 3. Documents : stockage, validité, accès

### 3.1 Stockage

- [x] **B5-30 (P0)** — `app/Libraries/Personnel/StockageService.php` créé.
  Méthodes : `stocker`, `supprimer`, `existe`, `cheminAbsolu`, `typesAutorises`.
- [x] **B5-31 (P0)** — Nom UUID v4 + extension contrôlée ; le nom client n'est qu'un libellé.
- [x] **B5-32 (P0)** — MIME réel (finfo + repli signatures binaires), 10 Mo max, PDF/JPEG/PNG/WEBP.
- [x] **B5-33 (P0)** — `checksum_sha256` et `taille_octets` calculés à l'enregistrement.
- [~] **B5-34 (P1)** — `StockageService::supprimer()` existe ; l'archivage courant conserve le fichier.
  La purge physique définitive reste à exposer (réservée admin).

### 3.2 Service documentaire

- [x] **B5-40 (P0)** — `DocumentService::ajouter` reçoit un `UploadedFile` ; plus de `cheminFichier` client.
- [x] **B5-41 (P0)** — `DocumentService::remplacer` : nouveau fichier, `remplace_par`, ancien archivé.
- [x] **B5-42 (P0)** — `archiver` / `supprimer` : suppression logique + audit `ARCHIVE`.
- [x] **B5-43 (P0)** — Statuts calculés : `valide`, `expire_bientot` (≤ 30 j), `expire`,
  `sans_expiration`, `archive` (`Document::statutValidite()`).
- [x] **B5-44 (P0)** — `piecesManquantes(idSalarie)` : types obligatoires sans pièce active.
- [x] **B5-45 (P1)** — `lister()` joint `type_document` et expose `statut_validite` via `toPublicArray()`.
- [x] **B5-46 (P1)** — `tauxCompletude(idSalarie)` : attendus / présents / manquants / pourcentage.
- [ ] **B5-47 (P2)** — Historique des remplacements d'un document.

### 3.3 Politique d'accès

- [x] **B5-50 (P0)** — `app/Libraries/Personnel/DocumentPolicy.php` créé.
- [x] **B5-51 (P0)** — Règles : `admin`/`rh` = tout ; `direction` = public/interne/rh ;
  `manager`/`recruteur`/`controle` = public/interne ; `salarie` = ses propres pièces, tous niveaux.
- [x] **B5-52 (P0)** — Appartenance résolue via `utilisateur.id_employe` → `employe.id_salarie`.
- [x] **B5-53 (P0)** — Accès direct refusé en 403 ; les listes filtrent selon la politique.
- [x] **B5-54 (P0)** — Audit `READ` sur consultation/téléchargement de pièces `rh`/`restreint`.
- [ ] **B5-55 (P1)** — Chiffrer les données très sensibles (numéro national, RIB) si elles sont ajoutées.

---

## 4. Dossier agrégé

- [x] **B5-60 (P0)** — `app/Services/Personnel/DossierService.php` créé (lecture seule).
- [x] **B5-61 (P0)** — Section `identite` : état civil, coordonnées, matricule, dates d'embauche/sortie.
- [x] **B5-62 (P0)** — Section `situationProfessionnelle` : statut, poste, service, département,
  ancienneté (`manager` non modélisé en base, exposé à `null`).
- [x] **B5-63 (P0)** — Section `contrats` : contrats successifs, type, dates, salaire, statut.
- [x] **B5-64 (P0)** — Section `affectations` : postes occupés, dates, motif, `en_cours`.
- [x] **B5-65 (P0)** — Section `remuneration` : salaire courant + historique des contrats.
- [x] **B5-66 (P0)** — Section `documents` : pièces filtrées, `statut_validite`, complétude, manquants.
- [x] **B5-67 (P0)** — Section `carriere` : timeline contrats + affectations + changements de statut,
  triée du plus récent au plus ancien.
- [x] **B5-68 (P0)** — Section `alertes` : expirés, expirant bientôt, pièces manquantes + compteurs.
- [x] **B5-69 (P1)** — Section `onboarding` : statut, dates, tâches terminées / total, progression.
- [x] **B5-70 (P1)** — Aucun `chemin_fichier` exposé (test `testAucunCheminFichierDansLeDossier`).

---

## 5. API et contrôleurs

- [x] **B5-80 (P0)** — `app/Controllers/Personnel/DossierController.php` créé.
- [x] **B5-81 (P0)** — `GET /api/salaries/{id}/dossier` → dossier complet.
- [x] **B5-82 (P0)** — `GET /api/salaries/{id}/dossier/alertes` → alertes documents.
- [x] **B5-83 (P1)** — `GET /api/salaries/{id}/carriere` → timeline.
- [x] **B5-84 (P0)** — `POST /api/salaries/{id}/documents` en `multipart/form-data`
  (champ `fichier`, métadonnées optionnelles).
- [x] **B5-85 (P0)** — `GET /api/documents/{id}/telecharger` → téléchargement contrôlé.
- [x] **B5-86 (P0)** — `POST /api/documents/{id}/remplacer` → nouveau fichier + ancien archivé.
- [x] **B5-87 (P0)** — `DELETE /api/documents/{id}` → archivage logique.
- [x] **B5-88 (P1)** — `GET /api/documents/expirant?jours=30` → dashboard RH.
- [x] **B5-89 (P0)** — Ajouter `filter => 'auth'` aux groupes de routes documents.
- [x] **B5-90 (P0)** — `stockageService`, `documentPolicy`, `documentService` et
  `dossierService` enregistrés dans `app/Config/Services.php`.
- [x] **B5-91 (P1)** — Format d'erreur homogène (`error`, `message`, codes 400/403/404).

---

## 6. Vues et expérience utilisateur

- [x] **B5-100 (P0)** — En-tête du dossier : nom, matricule, statut, poste, taux de complétude.
- [x] **B5-101 (P0)** — Onglet Identité : lecture + formulaire de modification enrichi.
- [x] **B5-102 (P0)** — Onglet Documents : tableau type, nom, date d'émission, expiration,
  statut (badge), actions.
- [x] **B5-103 (P0)** — Formulaire de dépôt : type, fichier, date d'émission, date d'expiration,
  confidentialité (pré-remplie depuis le type).
- [x] **B5-104 (P0)** — Actions Télécharger / Remplacer / Archiver.
- [x] **B5-105 (P0)** — Afficher les pièces obligatoires manquantes.
- [x] **B5-106 (P1)** — Onglet Carrière : timeline lisible (contrats, affectations, statuts).
- [x] **B5-107 (P1)** — Onglet Contrats : lien vers la fiche contrat existante.
- [x] **B5-108 (P1)** — Dashboard RH : compteur de documents expirant / expirés.
- [x] **B5-109 (P1)** — États vide / chargement / erreur sur chaque onglet.
- [ ] **B5-110 (P2)** — Impression / export PDF du dossier.
- [ ] **B5-111 (P2)** — Filtrer les documents par statut et par type.

---

## 7. Tests et qualité

- [ ] **B5-120 (P0)** — Test unitaire du calcul de validité (`valide`, `expire_bientôt`, `expiré`).
- [ ] **B5-121 (P0)** — Test unitaire de la complétude (types obligatoires manquants).
- [ ] **B5-122 (P0)** — Test d'intégration `DossierService` : dossier complet pour un salarié.
- [ ] **B5-123 (P0)** — Test d'intégration upload : fichier réel, MIME, taille, checksum.
- [ ] **B5-124 (P0)** — Tests d'accès : salarié ≠ autre salarié, recruteur ≠ document `restreint`.
- [ ] **B5-125 (P0)** — Test d'audit : `READ` sur document confidentiel et `CREATE`/`DELETE`.
- [ ] **B5-126 (P1)** — Test de non-régression `tests/integration/SalarieServiceTest.php`.
- [ ] **B5-127 (P1)** — Test des endpoints : 200/201/400/403/404.
- [ ] **B5-128 (P0)** — `composer test` vert.
- [ ] **B5-129 (P0)** — `composer lint` vert.
- [ ] **B5-130 (P1)** — Vérifier qu'aucun `chemin_fichier` n'apparaît dans les réponses JSON.

---

## 8. Documentation et démonstration

- [ ] **B5-140 (P1)** — Mettre à jour `conception/Salarie.md` (nouveaux champs, dossier agrégé).
- [ ] **B5-141 (P1)** — Mettre à jour `conception/vues/08-salarie.md` (onglets, documents, alertes).
- [ ] **B5-142 (P1)** — Créer `conception/Document.md` (stockage, validité, confidentialité).
- [ ] **B5-143 (P1)** — Mettre à jour `todo.md` : cocher D6/D7/D8/D9 selon l'avancement.
- [ ] **B5-144 (P1)** — Ajouter des fixtures de démonstration (documents valides/expirés/manquants).
- [ ] **B5-145 (P1)** — Écrire le scénario de démo ci-dessous.
- [ ] **B5-146 (P1)** — Revue croisée + PR mergée.

### Scénario de démo

1. Créer un salarié depuis une proposition acceptée.
2. Ouvrir son dossier : identité + poste + contrat + salaire.
3. Déposer une pièce d'identité avec expiration proche → badge « Expire bientôt ».
4. Déposer un diplôme → la complétude passe de 2/4 à 3/4.
5. Tenter de télécharger un document `restreint` avec un compte recruteur → 403.
6. Vérifier la trace dans `journal_audit`.
7. Dashboard RH : alerte « 1 document expire dans 30 jours ».
8. Remplacer la pièce expirée → ancienne version archivée, nouvelle valide.

---

## 9. Découpage en PR

| PR | Contenu | Dépend de | Priorité |
|---|---|---|---|
| **PR 1 — Schéma & modèles** | B5-10 → B5-24 | Décisions B5-01 → B5-04 | P0 |
| **PR 2 — Documents v2** | B5-30 → B5-55, B5-84 → B5-90 | PR 1 | P0 |
| **PR 3 — Dossier agrégé** | B5-60 → B5-70, B5-80 → B5-83 | PR 1, PR 2 | P0 |
| **PR 4 — UI dossier** | B5-100 → B5-109 | PR 3 | P0 |
| **PR 5 — Tests, doc, démo** | B5-120 → B5-146 | PR 1 → PR 4 | P1 |

Ordre conseillé : **PR 1 → PR 2 → PR 3 → PR 4 → PR 5**.
Les PR 1 et 2 sont les plus rentables : sans upload réel ni validité, le dossier reste cosmétique.

---

## 10. Hors périmètre de ce bloc

- [ ] Onboarding complet (checklist, tâches) → D6, peut être traité en parallèle.
- [ ] Paie, bulletins, pointage.
- [ ] Signature électronique des contrats.
- [ ] Gestion des congés.
- [ ] Portail salarié complet en libre-service.

---

## 11. Tests — PR 1 (schéma & modèles)

### A. Migration sur une base existante

```powershell
psql -U postgres -d gestion_rh -f script/db/04_migration_dossier_administratif.sql
psql -U postgres -d gestion_rh -f script/db/02_seeds.sql
psql -U postgres -d gestion_rh -f script/db/verif_bloc5.sql
```

Attendu :
- section 1 : 8 colonnes sur `salarie` ;
- section 2 : 9 colonnes sur `document` ;
- section 3 : 4 colonnes sur `type_document` ;
- section 4 : `document_fk3`, `document_fk4` et les 3 contraintes `CHECK` ;
- section 5 : `RIB` = `restreint`, `Pièce d'identité` = 120 mois, etc.

### B. Installation neuve

```powershell
createdb -U postgres gestion_rh_test
psql -U postgres -d gestion_rh_test -f script/db/01_migrations.sql
psql -U postgres -d gestion_rh_test -f script/db/02_seeds.sql
psql -U postgres -d gestion_rh_test -f script/db/verif_bloc5.sql
```

Attendu : mêmes sections que ci-dessus, sans exécuter `04` (colonnes déjà dans `01`).

### C. Modèles et entités

```powershell
php -l app/Entities/Personnel/Salarie.php
php -l app/Entities/Personnel/Document.php
php -l app/Models/Personnel/SalarieModel.php
php -l app/Models/Personnel/DocumentModel.php
php -l app/Models/Personnel/TypeDocumentModel.php
php vendor/phpunit/phpunit/phpunit --filter DocumentDossierTest --no-coverage
```

Attendu : `OK (8 tests, 28 assertions)`.

### D. Non-régression API documents

```powershell
php spark serve
```

Puis, connecté avec un compte RH :

```powershell
curl -b cookies.txt http://localhost:8080/api/salaries/1/documents
```

Attendu :
- réponse JSON 200 ;
- aucun champ `chemin_fichier` dans la réponse ;
- les champs `date_expiration`, `niveau_confidentialite`, `archive` sont présents.

### E. Test de la contrainte de confidentialité

```sql
-- Doit échouer : valeur interdite
INSERT INTO type_document (designation, niveau_confidentialite)
VALUES ('Test interdit', 'secret');
```

Attendu : erreur `type_document_confidentialite_check`.

---

## 11 bis. Tests — PR 2 (documents v2)

### A. Lint et tests automatisés

```powershell
php -l app/Libraries/Personnel/StockageService.php
php -l app/Libraries/Personnel/DocumentPolicy.php
php -l app/Services/Personnel/DocumentService.php
php -l app/Controllers/Personnel/DocumentController.php
php vendor/phpunit/phpunit/phpunit --filter DocumentServiceTest --no-coverage
```

Attendu : `OK (20 tests, 71 assertions)`.

### B. Upload réel via l'interface

1. Se connecter avec un compte `rh`.
2. Ouvrir `/personnel/salarie?id={idSalarie}` → onglet **Documents**.
3. Cliquer **Déposer un document**, choisir un PDF/PNG/JPEG/WEBP, renseigner une date d'émission.
4. Valider.

Attendu :
- message « Document ajouté » ;
- la pièce apparaît avec son type, ses dates et un badge de validité ;
- un fichier UUID existe dans `writable/uploads/documents/` ;
- le nom du fichier sur disque n'est pas le nom d'origine.

### C. Upload multipart via API

```powershell
curl.exe -b cookies.txt -X POST `
  -F "typeDocument=3" `
  -F "fichier=@C:\chemin\piece-identite.pdf" `
  -F "dateEmission=2025-01-15" `
  http://localhost:8080/api/salaries/{idSalarie}/documents
```

Attendu : `201` avec `{"idDocument": N}`.

### D. Refus d'un fichier non autorisé

```powershell
curl.exe -b cookies.txt -X POST `
  -F "typeDocument=3" `
  -F "fichier=@C:\chemin\script.php" `
  http://localhost:8080/api/salaries/{idSalarie}/documents
```

Attendu : `400` `DONNEES_INCOHERENTES` et aucun fichier créé.

### E. Confidentialité

Avec un compte `recruteur`, sur un salarié dont le RIB est déposé :

```powershell
curl.exe -b cookies_recruteur.txt http://localhost:8080/api/documents/{idRib}/telecharger
```

Attendu : `403 FORBIDDEN`.

Avec un compte `salarie` lié au salarié :

```powershell
curl.exe -b cookies_salarie.txt http://localhost:8080/api/documents/{idRib}/telecharger
```

Attendu : `200` et le fichier est téléchargé.

### F. Téléchargement et absence de fuite

```powershell
curl.exe -b cookies.txt http://localhost:8080/api/documents/{idDocument}/telecharger -o piece.pdf
curl.exe -b cookies.txt http://localhost:8080/api/salaries/{idSalarie}/documents
```

Attendu :
- le fichier téléchargé est identique à l'original ;
- la réponse JSON ne contient **jamais** `chemin_fichier` ;
- chaque pièce expose `statut_validite`.

### G. Remplacement et archivage

```powershell
curl.exe -b cookies.txt -X POST `
  -F "fichier=@C:\chemin\nouvelle-piece.pdf" `
  http://localhost:8080/api/documents/{idDocument}/remplacer
```

Attendu :
- `201` avec un nouvel `idDocument` ;
- l'ancien document a `archive = true` et `remplace_par = nouvel id` ;
- l'ancien fichier reste sur disque (traçabilité).

```powershell
curl.exe -b cookies.txt -X DELETE http://localhost:8080/api/documents/{idDocument}
```

Attendu : `{"ok":true,"archive":true}` et le fichier reste sur disque.

### H. Audit

```sql
SELECT ja.id_audit, a.action, ja.entite, ja.id_entite, ja.details
FROM journal_audit ja
JOIN action a ON a.id = ja.action
WHERE ja.entite IN ('document', 'dossier_documentaire')
ORDER BY ja.id_audit DESC
LIMIT 20;
```

Attendu : présence de `CREATE` au dépôt, `UPDATE` au remplacement,
`ARCHIVE` à la suppression, et `READ` lors de la consultation d'une pièce `rh`/`restreint`.

### I. Échéances et complétude

```powershell
curl.exe -b cookies.txt "http://localhost:8080/api/documents/expirant?jours=30"
```

Attendu : liste JSON des pièces actives expirant sous 30 jours (compte `rh`/`admin` uniquement).

L'API dossier (PR 3) expose `piecesManquantes()` et `tauxCompletude()` ;
les tests unitaires couvrent déjà ces calculs.

---

## 11 ter. Tests — PR 3 (dossier agrégé)

### A. Lint et tests automatisés

```powershell
php -l app/Services/Personnel/DossierService.php
php -l app/Controllers/Personnel/DossierController.php
php vendor/phpunit/phpunit/phpunit --filter DossierServiceTest --no-coverage
php vendor/phpunit/phpunit/phpunit --filter 'DocumentDossierTest|DocumentServiceTest|DossierServiceTest' --no-coverage
```

Attendu :
- `OK (14 tests, 57 assertions)` pour `DossierServiceTest` ;
- `OK (42 tests, 156 assertions)` pour la suite documents + dossier.

### B. Dossier complet via l'API

```powershell
curl.exe -b cookies.txt http://localhost:8080/api/salaries/{idSalarie}/dossier
```

Attendu : `200` avec les sections
`identite`, `situationProfessionnelle`, `contrats`, `affectations`,
`remuneration`, `documents`, `carriere`, `alertes`, `onboarding`.

### C. Absence de fuite de stockage

```powershell
curl.exe -b cookies.txt http://localhost:8080/api/salaries/{idSalarie}/dossier | Select-String 'chemin_fichier'
```

Attendu : **aucune ligne**. Les pièces exposent `statut_validite` mais jamais le chemin disque.

### D. Alertes documentaires

```powershell
curl.exe -b cookies.txt http://localhost:8080/api/salaries/{idSalarie}/dossier/alertes
```

Attendu : `documents_expires`, `documents_expirant_bientot`, `pieces_manquantes`,
ainsi que `nombre_expires`, `nombre_expirant_bientot`, `nombre_manquants`, `total`.

### E. Timeline de carrière

```powershell
curl.exe -b cookies.txt http://localhost:8080/api/salaries/{idSalarie}/carriere
```

Attendu : liste d'événements `contrat`, `affectation`, `statut`, triés du plus récent au plus ancien.

### F. Confidentialité et accès

Avec un compte `salarie` lié au salarié : `200` sur son propre dossier.

Avec un compte `salarie` lié à un autre salarié :

```powershell
curl.exe -b cookies_salarie_autre.txt http://localhost:8080/api/salaries/{idSalarie}/dossier
```

Attendu : `403 FORBIDDEN`.

Avec un compte `recruteur`, les pièces `rh`/`restreint` sont absentes de `documents.pieces`
(la politique documentaire filtre) mais le dossier reste consultable.

### G. Comptes inexistants

```powershell
curl.exe -b cookies.txt http://localhost:8080/api/salaries/999999/dossier
```

Attendu : `404 NOT_FOUND`.

### H. Audit

```sql
SELECT ja.id_audit, a.action, ja.entite, ja.id_entite, ja.details
FROM journal_audit ja
JOIN action a ON a.id = ja.action
WHERE ja.entite = 'dossier'
ORDER BY ja.id_audit DESC
LIMIT 10;
```

Attendu : une ligne `READ dossier #<idSalarie>` par consultation par un utilisateur
autre que le salarié propriétaire (la consultation de son propre dossier n'est pas journalisée).

---

## 11 quater. Tests — PR 4 (UI dossier)

### A. Lint et tests automatisés

```powershell
php -l app/Views/pages/personnel/salarie.php
php -l app/Views/pages/personnel/document-form.php
php -l app/Services/Common/DashboardService.php
node --check public/assets/js/pages/personnel/salarie.js
node --check public/assets/js/pages/personnel/document-form.js
node --check public/assets/js/pages/dashboard.js

php vendor/phpunit/phpunit/phpunit --filter 'DocumentDossierTest|DocumentServiceTest|DossierServiceTest' --no-coverage
php vendor/phpunit/phpunit/phpunit --filter DashboardServiceTest --no-coverage
```

Attendu :
- `OK (42 tests, 156 assertions)` pour la suite documents + dossier ;
- `DashboardServiceTest` vérifie les 8 clés d'indicateurs, dont `documentsExpirant`
  et `documentsExpires`.

> Note : lancé seul sur la base SQLite `:memory:`, `DashboardServiceTest` peut échouer
> au chargement de `02_seeds.sql` (`near "("`) car aucun schéma n'a encore été créé.
> C'est un comportement préexistant ; exécuter la suite complète, ou un test qui crée
> le schéma en premier.

### B. Démarrage et connexion

```powershell
php spark serve --port 8080
```

Se connecter avec `rh` / `rh123`, puis ouvrir :

```text
http://localhost:8080/personnel/salarie?id=1
```

### C. En-tête et onglets

Attendu :
- nom complet, matricule, poste, département et statut ;
- badge de complétude (`0 %` à `100 %`), barre de progression et alertes
  (`pièces manquantes`, `documents expirés`, `à renouveler`) ;
- onglets `Identité`, `Contrats`, `Affectations`, `Documents`, `Carrière`, `Historique` ;
- boutons `Affecter`, `Nouveau contrat`, `Déposer un document` visibles pour `rh`/`admin`
  (et `recruteur` pour l'identité et le dépôt), masqués pour `salarie`/`manager`.

### D. Identité

1. Cliquer sur `Modifier`.
2. Changer le téléphone, puis `Enregistrer`.
3. Attendu : toast `Identité mise à jour.`, retour en lecture, valeur actualisée.
4. Vérifier en base :

```sql
SELECT telephone FROM salarie WHERE id_salarie = <idSalarie>;
```

### E. Documents (dépôt, pré-remplissage, actions)

1. Cliquer sur `Déposer un document`.
2. Choisir le type `Pièce d'identité`.
3. Attendu : la confidentialité passe à `RH`, le message indique
   `Pièce obligatoire · Validité : 120 mois · Confidentialité par défaut : RH`.
4. Saisir une date d'émission : la date d'expiration est calculée automatiquement
   (+120 mois) ; elle reste modifiable manuellement.
5. Déposer un PDF/JPEG/PNG/WEBP < 10 Mo.
6. Attendu : retour au dossier, pièce listée avec type, nom, dates, confidentialité,
   badge de validité et complétude recalculée.
7. Cliquer sur `Remplacer` : le modal `.modal` s'ouvre pré-rempli ; déposer un nouveau
   fichier. Attendu : l'ancienne pièce est archivée (`archive = true`,
   `remplace_par = <nouvel id>`) et la nouvelle est seule affichée.
8. Cliquer sur `Archiver` puis confirmer : la pièce disparaît de l'onglet et la
   complétude baisse.

Contrôle SQL après remplacement :

```sql
SELECT id_document, archive, remplace_par, niveau_confidentialite
FROM document WHERE id_salarie = <idSalarie> ORDER BY id_document DESC;
```

### F. Pièces manquantes et complétude

Avec un dossier vide, attendu : `0/4`, `0 %` et les quatre pièces obligatoires listées
(`Contrat de travail`, `Pièce d'identité`, `Certificat médical`, `RIB`).
Chaque dépôt fait progresser le compteur.

### G. Contrats et affectations

- Onglet `Contrats` : type, poste, période, salaire, signature, statut, lien
  `Voir la fiche` vers `/personnel/contrat?id=<idContrat>`.
- Onglet `Affectations` : poste, département, période, motif, statut `En cours`/`Terminée`.
- Dossier vide : message d'état vide, jamais de tableau cassé.

### H. Carrière et historique

- Onglet `Carrière` : timeline triée du plus récent au plus ancien avec badges
  `Contrat`, `Affectation`, `Statut`.
- Onglet `Historique` : changements de statut et, si présent, résumé onboarding
  (`x/y tâches terminées`, barre de progression).

### I. Dashboard RH

Se connecter en `rh` et ouvrir `/dashboard`.

Attendu : 8 métriques, dont `Documents expirant (30 j)` et `Documents expirés`.
Créer une pièce avec `date_expiration` proche (< 30 jours) puis vérifier
l'incrément du compteur. Les rôles non RH voient `0`.

### J. États d'erreur

| URL | Attendu |
|---|---|
| `/personnel/salarie` | message « Identifiant salarié manquant », onglets désactivés |
| `/personnel/salarie?id=0` | message « Identifiant salarié manquant » |
| `/personnel/salarie?id=999999` | message d'erreur `404`, bouton retour à la liste |
| API dossier d'un salarié non autorisé (compte `salarie`) | `403 FORBIDDEN` |

### K. Audit

```sql
SELECT ja.id_audit, a.action, ja.entite, ja.id_entite, ja.details
FROM journal_audit ja
JOIN action a ON a.id = ja.action
WHERE ja.entite IN ('dossier', 'document')
ORDER BY ja.id_audit DESC
LIMIT 20;
```

Attendu :
- une ligne `READ dossier` par consultation du dossier par un tiers ;
- une ligne `READ document` pour chaque téléchargement d'une pièce `rh`/`restreint` ;
- `CREATE`, `UPDATE` (remplacement) et `ARCHIVE` pour les actions documents.

---

## 12. Definition of Done (rappel)

Pour chaque tâche :

- [ ] `composer test` et `composer lint` passent.
- [ ] Règles métier et rôles appliqués côté serveur.
- [ ] Action tracée dans l'historique et `journal_audit`.
- [ ] Format d'erreur commun respecté.
- [ ] Tests unitaires + intégration.
- [ ] Vue branchée si applicable.
- [ ] Aucun chemin de fichier exposé.
- [ ] Revue par un pair, PR mergée, cette todo mise à jour.
