-- ============================================================================
-- 02_seeds.sql — DONNÉES RÉELLEMENT UTILISÉES par l'application
--
-- Contenu :
--   1. Référentiels (statuts, types, rangs, actions)
--   2. Départements, services et postes
--   3. Grilles salariales
--   4. Comptes de démonstration + salariés/employés liés
--
-- Ce fichier remplace :
--   • script/archive/Donne_status.sql
--   • script/archive/fixtures_auth.sql
--   • script/archive/fixtures_grille.sql
--
-- Prérequis : 01_migrations.sql exécuté.
-- Utilisation : psql -U postgres -d gestion_rh -f script/db/02_seeds.sql
-- Idempotent : peut être relancé sans dupliquer les lignes.
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. RÉFÉRENTIELS
-- ----------------------------------------------------------------------------

-- Statuts des demandes de recrutement
INSERT INTO status (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'En cours'),
    (2, 'En contrôle RH'),
    (3, 'En validation budgétaire'),
    (4, 'En validation hiérarchique'),
    (5, 'En consultation interne'),
    (6, 'Validée'),
    (7, 'Refusée'),
    (8, 'À corriger'),
    (9, 'Annulée')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status s WHERE s.id = v.id OR s.designation = v.designation
);

-- Statuts des candidatures
INSERT INTO status_candidature (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'Reçue'),
    (2, 'En présélection'),
    (3, 'Présélectionnée'),
    (4, 'Entretien prévu'),
    (5, 'En évaluation'),
    (6, 'Retenue'),
    (7, 'Refusée'),
    (8, 'Retirée')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_candidature s WHERE s.id = v.id OR s.designation = v.designation
);

-- Statuts des offres d'emploi
INSERT INTO status_offre_emploi (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'Brouillon'),
    (2, 'Publiée'),
    (3, 'Suspendue'),
    (4, 'Clôturée'),
    (5, 'Annulée')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_offre_emploi s WHERE s.id = v.id OR s.designation = v.designation
);

-- Statuts des entretiens
INSERT INTO status_entretien (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'Planifié'),
    (2, 'Confirmé'),
    (3, 'Réalisé'),
    (4, 'Reporté'),
    (5, 'Annulé')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_entretien s WHERE s.id = v.id OR s.designation = v.designation
);

-- Statuts des propositions d'embauche
INSERT INTO status_proposition_embauche (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'À envoyer'),
    (2, 'Envoyée'),
    (3, 'En négociation'),
    (4, 'Acceptée'),
    (5, 'Refusée'),
    (6, 'Expirée')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_proposition_embauche s WHERE s.id = v.id OR s.designation = v.designation
);

-- Statuts des salariés
INSERT INTO status_salarie (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'En attente d''embauche'),
    (2, 'Actif'),
    (3, 'En période d''essai'),
    (4, 'Suspendu'),
    (5, 'Parti')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_salarie s WHERE s.id = v.id OR s.designation = v.designation
);

-- Statuts des contrats
INSERT INTO status_contrat (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'En préparation'),
    (2, 'En attente de signature'),
    (3, 'Signé'),
    (4, 'En cours'),
    (5, 'Terminé'),
    (6, 'Résilié')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_contrat s WHERE s.id = v.id OR s.designation = v.designation
);

-- Statuts des dossiers d'intégration
INSERT INTO status_onboarding (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'À démarrer'),
    (2, 'En cours'),
    (3, 'En attente'),
    (4, 'Terminé'),
    (5, 'Annulé')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_onboarding s WHERE s.id = v.id OR s.designation = v.designation
);

-- Statuts des tâches d'intégration
INSERT INTO status_onboarding_tache (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'À faire'),
    (2, 'En cours'),
    (3, 'Bloquée'),
    (4, 'Terminée'),
    (5, 'Annulée')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_onboarding_tache s WHERE s.id = v.id OR s.designation = v.designation
);

-- Natures de revendication (négociation d'une proposition d'embauche)
INSERT INTO nature_revendication (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'Rémunération'),
    (2, 'Date d''embauche'),
    (3, 'Temps de travail'),
    (4, 'Avantages'),
    (5, 'Poste / Intitulé'),
    (6, 'Autre')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM nature_revendication s WHERE s.id = v.id OR s.designation = v.designation
);

-- Statuts d'une revendication
INSERT INTO status_revendication (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'Soumise'),
    (2, 'En discussion'),
    (3, 'Acceptée'),
    (4, 'Refusée'),
    (5, 'Retirée')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_revendication s WHERE s.id = v.id OR s.designation = v.designation
);

-- Types de contrat
INSERT INTO type_contrat (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'CDI'),
    (2, 'CDD'),
    (3, 'Stage'),
    (4, 'Interim'),
    (5, 'Consultant'),
    (6, 'Essai'),
    (7, 'Alternance')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM type_contrat s WHERE s.id = v.id OR s.designation = v.designation
);

-- Types d'entretien
INSERT INTO type_entrentien (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'Entretien d''embauche'),
    (2, 'Entretien de fin de période d''essai'),
    (3, 'Entretien d''intégration')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM type_entrentien s WHERE s.id = v.id OR s.designation = v.designation
);

-- Types de document
-- obligatoire : pièce attendue dans le dossier administratif
-- duree_validite_mois : NULL = pas d'expiration automatique
-- niveau_confidentialite : public | interne | rh | restreint
INSERT INTO type_document (id, designation, obligatoire, duree_validite_mois, niveau_confidentialite, actif)
SELECT v.id, v.designation, v.obligatoire, v.duree_validite_mois, v.niveau_confidentialite, true
FROM (VALUES
    (1, 'CV', false, NULL::integer, 'interne'),
    (2, 'Contrat de travail', true, NULL::integer, 'rh'),
    (3, 'Pièce d''identité', true, 120, 'rh'),
    (4, 'Diplôme', false, NULL::integer, 'interne'),
    (5, 'Attestation de travail', false, NULL::integer, 'interne'),
    (6, 'Certificat médical', true, 12, 'rh'),
    (7, 'RIB', true, NULL::integer, 'restreint'),
    (8, 'Attestation de formation', false, NULL::integer, 'interne'),
    (9, 'Autre', false, NULL::integer, 'interne')
) AS v(id, designation, obligatoire, duree_validite_mois, niveau_confidentialite)
WHERE NOT EXISTS (
    SELECT 1 FROM type_document s WHERE s.id = v.id OR s.designation = v.designation
);

-- Backfill des métadonnées pour les bases où les types existent déjà.
UPDATE type_document AS td
SET obligatoire = v.obligatoire,
    duree_validite_mois = v.duree_validite_mois,
    niveau_confidentialite = v.niveau_confidentialite,
    actif = true
FROM (VALUES
    (1, 'CV', false, NULL::integer, 'interne'),
    (2, 'Contrat de travail', true, NULL::integer, 'rh'),
    (3, 'Pièce d''identité', true, 120, 'rh'),
    (4, 'Diplôme', false, NULL::integer, 'interne'),
    (5, 'Attestation de travail', false, NULL::integer, 'interne'),
    (6, 'Certificat médical', true, 12, 'rh'),
    (7, 'RIB', true, NULL::integer, 'restreint'),
    (8, 'Attestation de formation', false, NULL::integer, 'interne'),
    (9, 'Autre', false, NULL::integer, 'interne')
) AS v(id, designation, obligatoire, duree_validite_mois, niveau_confidentialite)
WHERE td.id = v.id OR td.designation = v.designation;

-- Rangs hiérarchiques
INSERT INTO rang (id, designation)
SELECT v.id, v.designation
FROM (VALUES
    (1, 'Stagiaire'),
    (2, 'Junior'),
    (3, 'Intermédiaire'),
    (4, 'Senior'),
    (5, 'Chef d''équipe'),
    (6, 'Manager'),
    (7, 'Responsable'),
    (8, 'Directeur'),
    (9, 'Vice-président'),
    (10, 'Président'),
    (11, 'AUTH - Manager')
) AS v(id, designation)
WHERE NOT EXISTS (
    SELECT 1 FROM rang s WHERE s.id = v.id OR s.designation = v.designation
);

-- Actions du journal d'audit
INSERT INTO action (id, action)
SELECT v.id, v.action
FROM (VALUES
    (1, 'INSERT'),
    (2, 'UPDATE'),
    (3, 'DELETE'),
    (4, 'LOGIN'),
    (5, 'LOGOUT'),
    (6, 'CONSULTATION'),
    (7, 'VALIDATION'),
    (8, 'REFUS'),
    (9, 'EXPORT'),
    (10, 'UPLOAD'),
    (11, 'DOWNLOAD'),
    (12, 'CREATE')
) AS v(id, action)
WHERE NOT EXISTS (
    SELECT 1 FROM action s WHERE s.id = v.id OR s.action = v.action
);

-- ----------------------------------------------------------------------------
-- 2. DÉPARTEMENTS, SERVICES ET POSTES
-- ----------------------------------------------------------------------------

INSERT INTO departement (id_departement, nom, description)
SELECT v.id, v.nom, v.description
FROM (VALUES
    (1, 'Ressources humaines', 'Gestion du personnel et du recrutement'),
    (2, 'Informatique', 'Développement et maintenance des systèmes informatiques'),
    (3, 'Finance', 'Gestion financière et comptabilité'),
    (4, 'Commercial', 'Vente et relation client'),
    (5, 'Marketing', 'Communication et stratégie marketing'),
    (6, 'AUTH - Direction', 'Fixture Piste A'),
    (7, 'Direction', 'Direction générale et décisions stratégiques')
) AS v(id, nom, description)
WHERE NOT EXISTS (
    SELECT 1 FROM departement d WHERE d.id_departement = v.id OR d.nom = v.nom
);

INSERT INTO service (id_service, id_departement, nom, description)
SELECT v.id, v.id_departement, v.nom, v.description
FROM (VALUES
    (1, 1, 'Recrutement', 'Gestion des candidatures et des embauches'),
    (2, 1, 'Administration du personnel', 'Gestion administrative des salariés'),
    (3, 2, 'Développement', 'Développement des applications'),
    (4, 2, 'Infrastructure', 'Gestion des serveurs et du réseau'),
    (5, 3, 'Comptabilité', 'Gestion des opérations comptables'),
    (6, 4, 'Ventes', 'Gestion des ventes et des clients'),
    (7, 5, 'Communication', 'Gestion de la communication de l''entreprise'),
    (8, 6, 'AUTH - Service', 'Fixture Piste A'),
    (9, 7, 'Direction générale', 'Décisions stratégiques et validation hiérarchique')
) AS v(id, id_departement, nom, description)
WHERE NOT EXISTS (
    SELECT 1 FROM service s WHERE s.id_service = v.id OR s.nom = v.nom
);

INSERT INTO poste (id_poste, id_service, intitule, description, rang_id)
SELECT v.id, v.id_service, v.intitule, v.description, v.rang_id
FROM (VALUES
    (1,  1, 'Responsable Recrutement', 'Supervise les processus de recrutement', 7),
    (2,  1, 'Chargé de Recrutement', 'Gère les candidatures et les entretiens', 5),
    (3,  2, 'Responsable Administration du Personnel', 'Supervise la gestion administrative des salariés', 7),
    (4,  2, 'Assistant RH', 'Assiste dans la gestion administrative des salariés', 4),
    (5,  3, 'Développeur Senior', 'Développe et maintient les applications informatiques', 4),
    (6,  3, 'Développeur Junior', 'Assiste dans le développement des applications informatiques', 2),
    (7,  4, 'Administrateur Systèmes et Réseaux', 'Gère les serveurs et le réseau informatique', 5),
    (8,  5, 'Comptable Senior', 'Gère les opérations comptables complexes', 4),
    (9,  5, 'Comptable Junior', 'Assiste dans la gestion des opérations comptables', 2),
    (10, 6, 'Responsable Commercial', 'Supervise les activités commerciales et les ventes', 7),
    (11, 6, 'Commercial Terrain', 'Gère les relations avec les clients et effectue des ventes sur le terrain', 5),
    (12, 7, 'Responsable Communication', 'Supervise la communication interne et externe de l''entreprise', 7),
    (13, 7, 'Chargé de Communication', 'Assiste dans la gestion de la communication de l''entreprise', 5),
    (14, 8, 'AUTH - Administrateur', 'Fixture Piste A', 11),
    (15, 5, 'Contrôleur de gestion', 'Analyse les budgets et valide les demandes de recrutement', 4),
    (16, 9, 'Directeur général', 'Dirige l''entreprise et valide les décisions', 8)
) AS v(id, id_service, intitule, description, rang_id)
WHERE NOT EXISTS (
    SELECT 1 FROM poste p WHERE p.id_poste = v.id OR p.intitule = v.intitule
);

-- ----------------------------------------------------------------------------
-- 3. GRILLES SALARIALES (actives au 2025-01-01)
-- ----------------------------------------------------------------------------

INSERT INTO grille_salariale (id_poste, rang, salaire_min, salaire_max, date_debut)
SELECT p.id_poste, p.rang_id, v.salaire_min, v.salaire_max, DATE '2025-01-01'
FROM (VALUES
    ('Responsable Recrutement',                 42000.00, 58000.00),
    ('Chargé de Recrutement',                   30000.00, 42000.00),
    ('Responsable Administration du Personnel', 40000.00, 55000.00),
    ('Assistant RH',                            26000.00, 34000.00),
    ('Développeur Senior',                      45000.00, 62000.00),
    ('Développeur Junior',                      30000.00, 40000.00),
    ('Administrateur Systèmes et Réseaux',      38000.00, 52000.00),
    ('Comptable Senior',                        36000.00, 48000.00),
    ('Comptable Junior',                        27000.00, 35000.00),
    ('Responsable Commercial',                  43000.00, 60000.00),
    ('Commercial Terrain',                      28000.00, 40000.00),
    ('Responsable Communication',               40000.00, 55000.00),
    ('Chargé de Communication',                 28000.00, 38000.00),
    ('AUTH - Administrateur',                   45000.00, 65000.00),
    ('Contrôleur de gestion',                   38000.00, 52000.00),
    ('Directeur général',                       70000.00, 95000.00)
) AS v(intitule, salaire_min, salaire_max)
JOIN poste p ON p.intitule = v.intitule
WHERE NOT EXISTS (
    SELECT 1
    FROM grille_salariale g
    WHERE g.id_poste = p.id_poste
      AND g.date_debut = DATE '2025-01-01'
);

-- ----------------------------------------------------------------------------
-- 4. COMPTES DE DÉMONSTRATION
--
-- Identifiants / mots de passe :
--   admin      / admin123       (rôle admin)
--   rh         / rh123          (rôle rh)
--   manager    / manager123     (rôle manager)
--   recruteur  / recruteur123   (rôle recruteur)
--   direction  / direction123   (rôle direction)
--   controle   / controle123    (rôle controle_gestion)
--   salarie    / salarie123     (rôle salarie)
-- ----------------------------------------------------------------------------

-- Salariés
INSERT INTO salarie (matricule, nom, prenom, email, telephone, date_embauche, statut)
SELECT v.matricule, v.nom, v.prenom, v.email, v.telephone, v.date_embauche, ss.id
FROM (VALUES
    ('MAT-AUTH-0001', 'Admin',                  'Auth', 'admin.auth@example.test',             NULL,         DATE '1980-01-01'),
    ('MAT-AUTH-0002', 'Rh',                     'Auth', 'rh.auth@example.test',                NULL,         DATE '1985-01-01'),
    ('MAT-AUTH-0003', 'Manager',                'Auth', 'manager.auth@example.test',           NULL,         DATE '1986-01-01'),
    ('MAT-FIN-0001',  'Rakotomalala',           'Andry', 'andry.rakotomalala@example.test',     '0320000010', DATE '2021-03-01'),
    ('MAT-DIR-0001',  'Rakotondrabe',           'Fanja', 'fanja.rakotondrabe@example.test',     '0320000011', DATE '2018-01-15')
) AS v(matricule, nom, prenom, email, telephone, date_embauche)
JOIN status_salarie ss ON ss.designation = 'Actif'
WHERE NOT EXISTS (
    SELECT 1 FROM salarie s WHERE s.matricule = v.matricule
);

-- Employés rattachés aux postes
INSERT INTO employe (nom, date_de_naissance, poste_id, id_salarie)
SELECT v.nom, v.date_de_naissance, p.id_poste, s.id_salarie
FROM (VALUES
    ('Administrateur Auth',           DATE '1980-01-01', 'AUTH - Administrateur', 'MAT-AUTH-0001'),
    ('RH Auth',                       DATE '1985-01-01', 'AUTH - Administrateur', 'MAT-AUTH-0002'),
    ('Manager Auth',                  DATE '1986-01-01', 'AUTH - Administrateur', 'MAT-AUTH-0003'),
    ('Contrôleur de gestion - Andry', DATE '1989-09-12', 'Contrôleur de gestion', 'MAT-FIN-0001'),
    ('Directeur général - Fanja',     DATE '1978-05-04', 'Directeur général',     'MAT-DIR-0001')
) AS v(nom, date_de_naissance, poste, matricule)
JOIN poste p ON p.intitule = v.poste
JOIN salarie s ON s.matricule = v.matricule
WHERE NOT EXISTS (
    SELECT 1 FROM employe e WHERE e.id_salarie = s.id_salarie
);

-- Comptes utilisateurs
INSERT INTO utilisateur (id_employe, identifiant, mot_de_passe_hash, role, actif)
SELECT e.id, v.identifiant, v.mot_de_passe_hash, v.role, true
FROM (VALUES
    ('admin',     '$2y$10$es0U0VGtKgq2E.1dbvUN8uDTZbvahlPbRsnp4XZE21aXnP3NaxQAy', 'admin',             'Administrateur Auth'),
    ('rh',        '$2y$10$eQfviYz.MyfpAOsFIPZXS.r2gTXE0P.cDcXyqEdIK18yL4LX6k45.', 'rh',                'RH Auth'),
    ('manager',   '$2y$10$VZG2/Tz.Dw/oqqfYO9yI.u29wx7ziSRpcP8zgG6JOh9TW/o76HswW', 'manager',           'Manager Auth'),
    ('direction', '$2y$10$TpbiaWaJ7xni1PapMjF9YOq33mh78p.dIFTp0qD6Ijbh4LtwnoOkW', 'direction',         'Directeur général - Fanja'),
    ('controle',  '$2y$10$UBZOskUsVTfgm25nSgO9VeQjdg0D4/yzuIjitteXiz6dn8fwQlg6a', 'controle_gestion',  'Contrôleur de gestion - Andry')
) AS v(identifiant, mot_de_passe_hash, role, nom_employe)
JOIN employe e ON e.nom = v.nom_employe
WHERE NOT EXISTS (
    SELECT 1 FROM utilisateur u WHERE u.identifiant = v.identifiant
);

-- Comptes sans employé rattaché (accès de démonstration)
INSERT INTO utilisateur (id_employe, identifiant, mot_de_passe_hash, role, actif)
SELECT v.id_employe, v.identifiant, v.mot_de_passe_hash, v.role, true
FROM (VALUES
    (NULL::bigint, 'recruteur', '$2y$10$GXLr5yg5TtVJuSzDIfVsVO3rImUu640Ykl35kr63tmZNPpD7qWuHG', 'recruteur'),
    (NULL::bigint, 'salarie',   '$2y$10$ObzZ9nBNCg.ZU.3Rag7sh.P5hPrFl7VSRWPp7hy72kOSynTes0A3u', 'salarie')
) AS v(id_employe, identifiant, mot_de_passe_hash, role)
WHERE NOT EXISTS (
    SELECT 1 FROM utilisateur u WHERE u.identifiant = v.identifiant
);

-- Les comptes de démonstration sont directement utilisables.
UPDATE utilisateur
SET doit_changer_mot_de_passe = false
WHERE identifiant IN ('admin', 'rh', 'manager', 'recruteur', 'direction', 'controle', 'salarie');

-- ----------------------------------------------------------------------------
-- 5. REMISE À NIVEAU DES SÉQUENCES
--    (les identifiants ont été fournis explicitement pour les référentiels)
-- ----------------------------------------------------------------------------

SELECT setval(pg_get_serial_sequence('departement', 'id_departement'), COALESCE((SELECT MAX(id_departement) FROM departement), 1));
SELECT setval(pg_get_serial_sequence('service', 'id_service'), COALESCE((SELECT MAX(id_service) FROM service), 1));
SELECT setval(pg_get_serial_sequence('poste', 'id_poste'), COALESCE((SELECT MAX(id_poste) FROM poste), 1));
SELECT setval(pg_get_serial_sequence('grille_salariale', 'id_grille'), COALESCE((SELECT MAX(id_grille) FROM grille_salariale), 1));
SELECT setval(pg_get_serial_sequence('salarie', 'id_salarie'), COALESCE((SELECT MAX(id_salarie) FROM salarie), 1));
SELECT setval(pg_get_serial_sequence('employe', 'id'), COALESCE((SELECT MAX(id) FROM employe), 1));
SELECT setval(pg_get_serial_sequence('utilisateur', 'id_utilisateur'), COALESCE((SELECT MAX(id_utilisateur) FROM utilisateur), 1));
SELECT setval(pg_get_serial_sequence('status', 'id'), COALESCE((SELECT MAX(id) FROM status), 1));
SELECT setval(pg_get_serial_sequence('status_candidature', 'id'), COALESCE((SELECT MAX(id) FROM status_candidature), 1));
SELECT setval(pg_get_serial_sequence('status_offre_emploi', 'id'), COALESCE((SELECT MAX(id) FROM status_offre_emploi), 1));
SELECT setval(pg_get_serial_sequence('status_entretien', 'id'), COALESCE((SELECT MAX(id) FROM status_entretien), 1));
SELECT setval(pg_get_serial_sequence('status_proposition_embauche', 'id'), COALESCE((SELECT MAX(id) FROM status_proposition_embauche), 1));
SELECT setval(pg_get_serial_sequence('status_salarie', 'id'), COALESCE((SELECT MAX(id) FROM status_salarie), 1));
SELECT setval(pg_get_serial_sequence('status_contrat', 'id'), COALESCE((SELECT MAX(id) FROM status_contrat), 1));
SELECT setval(pg_get_serial_sequence('status_onboarding', 'id'), COALESCE((SELECT MAX(id) FROM status_onboarding), 1));
SELECT setval(pg_get_serial_sequence('status_onboarding_tache', 'id'), COALESCE((SELECT MAX(id) FROM status_onboarding_tache), 1));
SELECT setval(pg_get_serial_sequence('nature_revendication', 'id'), COALESCE((SELECT MAX(id) FROM nature_revendication), 1));
SELECT setval(pg_get_serial_sequence('status_revendication', 'id'), COALESCE((SELECT MAX(id) FROM status_revendication), 1));
SELECT setval(pg_get_serial_sequence('type_contrat', 'id'), COALESCE((SELECT MAX(id) FROM type_contrat), 1));
SELECT setval(pg_get_serial_sequence('type_entrentien', 'id'), COALESCE((SELECT MAX(id) FROM type_entrentien), 1));
SELECT setval(pg_get_serial_sequence('type_document', 'id'), COALESCE((SELECT MAX(id) FROM type_document), 1));
SELECT setval(pg_get_serial_sequence('rang', 'id'), COALESCE((SELECT MAX(id) FROM rang), 1));
SELECT setval(pg_get_serial_sequence('action', 'id'), COALESCE((SELECT MAX(id) FROM action), 1));

COMMIT;
