-- ============================================================================
-- 05_employe_informatique.sql — Employé du département Informatique (démo)
--
-- Insère un employé complet dans le département Informatique avec :
--   • 1 salarié (matricule, civilité, dates, statut Actif)
--   • 1 employé rattaché à un poste du service Développement
--   • 1 contrat de travail (CDI, signé, en cours)
--   • 1 affectation (poste actuel)
--   • 5 documents administratifs (CV, contrat, pièce d'identité, RIB, diplôme)
--   • 1 onboarding en cours avec 6 tâches par défaut
--   • 1 compte utilisateur (identifiant / mot de passe)
--   • Historiques de statut (salarie, contrat, onboarding, tâches)
--   • Entrées d'audit associées
--
-- Identifiants de connexion créés :
--   tdurand / informatique123   (rôle salarie)
--
-- Ce script est IDEMPOTENT : il peut être relancé sans erreur (WHERE NOT EXISTS).
--
-- Prérequis : 01_migrations.sql et 02_seeds.sql déjà exécutés.
-- Utilisation : psql -U postgres -d gestion_rh -f script/db/05_employe_informatique.sql
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 0. Variables pratiques (IDs des référentiels existants)
-- ----------------------------------------------------------------------------
-- Département : Informatique (id_departement = 2)
-- Service     : Développement      (id_service   = 3)
-- Poste       : Développeur Senior (id_poste     = 5)
-- Statut salarie : Actif           (id           = 2)
-- Statut contrat : En cours        (id           = 4)
-- Type contrat  : CDI              (id           = 1)
-- Rang          : Intermédiaire    (id           = 4)
-- ----------------------------------------------------------------------------

-- ----------------------------------------------------------------------------
-- 1. SALARIÉ
-- ----------------------------------------------------------------------------
INSERT INTO salarie (
    id_candidat, matricule, nom, prenom, email, telephone,
    date_naissance, lieu_naissance, nationalite, situation_familiale,
    adresse, ville, pays, date_embauche, date_sortie, statut
)
SELECT
    NULL,
    'MAT-INF-0001',
    'Durand',
    'Thomas',
    'thomas.durand@example.test',
    '0600000001',
    DATE '1990-06-15',
    'Antananarivo',
    'Malgache',
    'Célibataire',
    'Lot IIA 12 bis',
    'Antananarivo',
    'Madagascar',
    DATE '2024-09-01',
    NULL,
    ss.id
FROM status_salarie ss
WHERE ss.designation = 'Actif'
  AND NOT EXISTS (
      SELECT 1 FROM salarie s WHERE s.matricule = 'MAT-INF-0001'
  );

-- ----------------------------------------------------------------------------
-- 2. EMPLOYE (lien salarié <-> poste)
-- ----------------------------------------------------------------------------
INSERT INTO employe (nom, date_de_naissance, poste_id, id_salarie)
SELECT
    'Durand Thomas',
    DATE '1990-06-15',
    p.id_poste,
    s.id_salarie
FROM poste p
JOIN salarie s ON s.matricule = 'MAT-INF-0001'
WHERE p.intitule = 'Développeur Senior'
  AND NOT EXISTS (
      SELECT 1 FROM employe e WHERE e.id_salarie = s.id_salarie
  );

-- ----------------------------------------------------------------------------
-- 3. CONTRAT DE TRAVAIL (CDI, signé, en cours)
-- ----------------------------------------------------------------------------
INSERT INTO contrat (
    id_salarie, id_poste, type_contrat_id, date_debut, date_fin,
    salaire_base, periode_essai_debut, periode_essai_fin, date_signature, statut
)
SELECT
    s.id_salarie,
    p.id_poste,
    tc.id,
    DATE '2024-09-01',
    NULL,
    48000.00,
    DATE '2024-09-01',
    DATE '2025-03-01',
    DATE '2024-08-25',
    sc.id
FROM salarie s
JOIN poste p ON p.intitule = 'Développeur Senior'
JOIN type_contrat tc ON tc.designation = 'CDI'
JOIN status_contrat sc ON sc.designation = 'En cours'
WHERE s.matricule = 'MAT-INF-0001'
  AND NOT EXISTS (
      SELECT 1 FROM contrat c WHERE c.id_salarie = s.id_salarie
        AND c.type_contrat_id = tc.id
  );

-- ----------------------------------------------------------------------------
-- 4. AFFECTATION (poste actuel)
-- ----------------------------------------------------------------------------
INSERT INTO affectation (id_salarie, id_poste, date_debut, date_fin, motif)
SELECT
    s.id_salarie,
    p.id_poste,
    DATE '2024-09-01',
    NULL,
    'Affectation initiale au poste de Développeur Senior'
FROM salarie s
JOIN poste p ON p.intitule = 'Développeur Senior'
WHERE s.matricule = 'MAT-INF-0001'
  AND NOT EXISTS (
      SELECT 1 FROM affectation a
      WHERE a.id_salarie = s.id_salarie
        AND a.id_poste = p.id_poste
        AND a.date_fin IS NULL
  );

-- ----------------------------------------------------------------------------
-- 5. DOCUMENTS ADMINISTRATIFS (avec niveaux de confidentialité)
-- ----------------------------------------------------------------------------
INSERT INTO document (
    id_salarie, type_document, nom_fichier, chemin_fichier,
    date_depot, date_emission, date_expiration, niveau_confidentialite,
    id_utilisateur_depot, mime_type, taille_octets, checksum_sha256,
    archive
)
SELECT
    s.id_salarie,
    td.id,
    v.nom_fichier,
    v.chemin_fichier,
    v.date_depot,
    v.date_emission,
    v.date_expiration,
    td.niveau_confidentialite,
    NULL,
    'application/pdf',
    v.taille_octets,
    v.checksum_sha256,
    false
FROM salarie s
CROSS JOIN LATERAL (VALUES
    ('CV',                          'cv_thomas_durand.pdf',               '/stockage/documents/inf-0001/cv.pdf',                DATE '2024-08-20', DATE '2024-08-20', NULL,        245760, 'a1b2c3d4e5f60000000000000000000000000000000000000000000000000001'),
    ('Contrat de travail',          'contrat_td_inf.pdf',                 '/stockage/documents/inf-0001/contrat.pdf',             DATE '2024-08-25', DATE '2024-08-25', NULL,        512000, 'a1b2c3d4e5f60000000000000000000000000000000000000000000000000002'),
    ('Pièce d''identité',           'piece_identite_td.pdf',              '/stockage/documents/inf-0001/piece_identite.pdf',      DATE '2024-08-22', DATE '2024-08-22', DATE '2044-08-22', 384000,  'a1b2c3d4e5f60000000000000000000000000000000000000000000000000003'),
    ('RIB',                         'rib_thomas_durand.pdf',              '/stockage/documents/inf-0001/rib.pdf',                   DATE '2024-08-23', DATE '2024-08-23', NULL,        192000,  'a1b2c3d4e5f60000000000000000000000000000000000000000000000000004'),
    ('Diplôme',                     'diplome_ingenieur_td.pdf',           '/stockage/documents/inf-0001/diplome.pdf',               DATE '2024-08-21', DATE '2024-08-21', NULL,        768000,  'a1b2c3d4e5f60000000000000000000000000000000000000000000000000005')
) AS v(designation, nom_fichier, chemin_fichier, date_depot, date_emission, date_expiration, taille_octets, checksum_sha256)
JOIN type_document td ON td.designation = v.designation
WHERE s.matricule = 'MAT-INF-0001'
  AND NOT EXISTS (
      SELECT 1 FROM document d
      WHERE d.id_salarie = s.id_salarie
        AND d.type_document = td.id
        AND d.nom_fichier = v.nom_fichier
  );

-- ----------------------------------------------------------------------------
-- 6. ONBOARDING (en cours) avec tâches par défaut
-- ----------------------------------------------------------------------------
INSERT INTO onboarding (statut, id_salarie, date_debut, date_fin)
SELECT
    so.id,
    s.id_salarie,
    DATE '2024-09-01',
    NULL
FROM salarie s
JOIN status_onboarding so ON so.designation = 'En cours'
WHERE s.matricule = 'MAT-INF-0001'
  AND NOT EXISTS (
      SELECT 1 FROM onboarding o WHERE o.id_salarie = s.id_salarie
        AND o.statut = so.id
  );

-- Récupérer l'onboarding inséré pour les tâches
DO $$
DECLARE
    v_id_onboarding bigint;
    v_id_statut_faire bigint;
    v_id_utilisateur bigint;
BEGIN
    SELECT o.id_onboarding INTO v_id_onboarding
    FROM onboarding o
    JOIN salarie s ON s.id_salarie = o.id_salarie
    WHERE s.matricule = 'MAT-INF-0001'
      AND o.statut = (SELECT id FROM status_onboarding WHERE designation = 'En cours')
    LIMIT 1;

    IF v_id_onboarding IS NOT NULL THEN
        SELECT id INTO v_id_statut_faire
        FROM status_onboarding_tache
        WHERE designation = 'À faire'
        LIMIT 1;

        SELECT id_utilisateur INTO v_id_utilisateur
        FROM utilisateur
        WHERE identifiant = 'tdurand'
        LIMIT 1;

        -- Tâches d'onboarding (uniquement si aucune n'existe encore)
        IF NOT EXISTS (SELECT 1 FROM onboarding_tache WHERE id_onboarding = v_id_onboarding) THEN
            INSERT INTO onboarding_tache (statut, id_onboarding, intitule, description, responsable, date_echeance, date_realisation)
            SELECT v_id_statut_faire, v_id_onboarding, v.intitule, v.description, NULL, v.echeance, NULL
            FROM (VALUES
                ('Contrat signé',             'Vérifier la signature du contrat de travail',          DATE '2024-09-02'),
                ('Badge',                     'Création et activation du badge d''accès',            DATE '2024-09-03'),
                ('Compte informatique',       'Création du compte utilisateur et accès réseau',       DATE '2024-09-04'),
                ('Matériel',                  'Attribution du poste de travail et accessoires',       DATE '2024-09-05'),
                ('Formation',                 'Formation aux outils internes (Git, CI/CD, monitoring)', DATE '2024-09-10'),
                ('Inscription paie',          'Transmission des éléments bancaires (RIB) à la paie',  DATE '2024-09-06')
            ) AS v(intitule, description, echeance);
        END IF;
    END IF;
END $$;

-- ----------------------------------------------------------------------------
-- 7. COMPTE UTILISATEUR (identifiant / mot de passe)
-- ----------------------------------------------------------------------------
-- Hash bcrypt de "informatique123" (à changer à la première connexion)
INSERT INTO utilisateur (id_employe, identifiant, mot_de_passe_hash, role, actif, doit_changer_mot_de_passe)
SELECT
    e.id,
    'tdurand',
    '$2y$12$xlsAXH1floIbHu.Cb2McZutEs0ChL/aj5vPLMujpl2Hpq4VmP4pWW', -- hash de "informatique123" (à remplacer si besoin)
    'salarie',
    true,
    true
FROM employe e
WHERE e.id_salarie = (SELECT id_salarie FROM salarie WHERE matricule = 'MAT-INF-0001')
  AND NOT EXISTS (
      SELECT 1 FROM utilisateur u WHERE u.identifiant = 'tdurand'
  );

-- ----------------------------------------------------------------------------
-- 8. HISTORIQUES DE STATUT
-- ----------------------------------------------------------------------------
-- Historique salarie : Actif
DO $$
DECLARE
    v_id_salarie bigint;
    v_id_utilisateur bigint;
    v_id_statut_salarie bigint;
BEGIN
    SELECT id_salarie INTO v_id_salarie FROM salarie WHERE matricule = 'MAT-INF-0001';
    SELECT id_utilisateur INTO v_id_utilisateur FROM utilisateur WHERE identifiant = 'tdurand';
    SELECT id INTO v_id_statut_salarie FROM status_salarie WHERE designation = 'Actif';

    IF v_id_salarie IS NOT NULL AND v_id_utilisateur IS NOT NULL THEN
        INSERT INTO salarie_status_historique (id_salarie, statut_ancien, statut_nouveau, date_changement, id_utilisateur, commentaire)
        SELECT v_id_salarie, 1, v_id_statut_salarie, DATE '2024-09-01', v_id_utilisateur, 'Embauche initiale'
        WHERE NOT EXISTS (
            SELECT 1 FROM salarie_status_historique
            WHERE id_salarie = v_id_salarie AND statut_nouveau = v_id_statut_salarie
        );
    END IF;
END $$;

-- Historique contrat : En cours
DO $$
DECLARE
    v_id_contrat bigint;
    v_id_utilisateur bigint;
    v_id_statut_contrat bigint;
BEGIN
    SELECT c.id_contrat INTO v_id_contrat
    FROM contrat c
    JOIN salarie s ON s.id_salarie = c.id_salarie
    WHERE s.matricule = 'MAT-INF-0001' LIMIT 1;
    SELECT id_utilisateur INTO v_id_utilisateur FROM utilisateur WHERE identifiant = 'tdurand';
    SELECT id INTO v_id_statut_contrat FROM status_contrat WHERE designation = 'En cours';

    IF v_id_contrat IS NOT NULL AND v_id_utilisateur IS NOT NULL THEN
        INSERT INTO contrat_status_historique (id_contrat, statut_ancien, statut_nouveau, date_changement, id_utilisateur, commentaire)
        SELECT v_id_contrat, 1, v_id_statut_contrat, DATE '2024-09-01', v_id_utilisateur, 'Contrat signé et activé'
        WHERE NOT EXISTS (
            SELECT 1 FROM contrat_status_historique
            WHERE id_contrat = v_id_contrat AND statut_nouveau = v_id_statut_contrat
        );
    END IF;
END $$;

-- Historique onboarding : En cours
DO $$
DECLARE
    v_id_onboarding bigint;
    v_id_utilisateur bigint;
    v_id_statut_onboarding bigint;
BEGIN
    SELECT o.id_onboarding INTO v_id_onboarding
    FROM onboarding o
    JOIN salarie s ON s.id_salarie = o.id_salarie
    WHERE s.matricule = 'MAT-INF-0001' LIMIT 1;
    SELECT id_utilisateur INTO v_id_utilisateur FROM utilisateur WHERE identifiant = 'tdurand';
    SELECT id INTO v_id_statut_onboarding FROM status_onboarding WHERE designation = 'En cours';

    IF v_id_onboarding IS NOT NULL AND v_id_utilisateur IS NOT NULL THEN
        INSERT INTO onboarding_status_historique (id_onboarding, statut_ancien, statut_nouveau, date_changement, id_utilisateur, commentaire)
        SELECT v_id_onboarding, 1, v_id_statut_onboarding, DATE '2024-09-01', v_id_utilisateur, 'Démarrage de l''onboarding'
        WHERE NOT EXISTS (
            SELECT 1 FROM onboarding_status_historique
            WHERE id_onboarding = v_id_onboarding AND statut_nouveau = v_id_statut_onboarding
        );
    END IF;
END $$;

-- ----------------------------------------------------------------------------
-- 9. JOURNAL D'AUDIT (entrées liées à la création du dossier)
----------------------------------------------------------------------------
DO $$
DECLARE
    v_id_utilisateur bigint;
    v_id_action_create bigint;
    v_id_salarie bigint;
BEGIN
    SELECT id_utilisateur INTO v_id_utilisateur FROM utilisateur WHERE identifiant = 'tdurand';
    SELECT id INTO v_id_action_create FROM action WHERE action = 'CREATE';
    SELECT id_salarie INTO v_id_salarie FROM salarie WHERE matricule = 'MAT-INF-0001';

    IF v_id_utilisateur IS NOT NULL AND v_id_action_create IS NOT NULL THEN
        INSERT INTO journal_audit (id_utilisateur, action, entite, id_entite, details, adresse_ip)
        SELECT v_id_utilisateur, v_id_action_create, 'salarie', v_id_salarie::text,
               json_build_object('matricule', 'MAT-INF-0001', 'nom', 'Durand', 'prenom', 'Thomas', 'departement', 'Informatique'),
               '127.0.0.1'
        WHERE NOT EXISTS (
            SELECT 1 FROM journal_audit
            WHERE id_utilisateur = v_id_utilisateur
              AND entite = 'salarie'
              AND id_entite = v_id_salarie::text
              AND action = v_id_action_create
        );
    END IF;
END $$;

-- ----------------------------------------------------------------------------
-- 10. REMIS À NIVEAU DES SÉQUENCES
-- ----------------------------------------------------------------------------
SELECT setval(pg_get_serial_sequence('salarie', 'id_salarie'), COALESCE((SELECT MAX(id_salarie) FROM salarie), 1));
SELECT setval(pg_get_serial_sequence('employe', 'id'), COALESCE((SELECT MAX(id) FROM employe), 1));
SELECT setval(pg_get_serial_sequence('contrat', 'id_contrat'), COALESCE((SELECT MAX(id_contrat) FROM contrat), 1));
SELECT setval(pg_get_serial_sequence('affectation', 'id_affectation'), COALESCE((SELECT MAX(id_affectation) FROM affectation), 1));
SELECT setval(pg_get_serial_sequence('document', 'id_document'), COALESCE((SELECT MAX(id_document) FROM document), 1));
SELECT setval(pg_get_serial_sequence('onboarding', 'id_onboarding'), COALESCE((SELECT MAX(id_onboarding) FROM onboarding), 1));
SELECT setval(pg_get_serial_sequence('onboarding_tache', 'id_tache'), COALESCE((SELECT MAX(id_tache) FROM onboarding_tache), 1));
SELECT setval(pg_get_serial_sequence('utilisateur', 'id_utilisateur'), COALESCE((SELECT MAX(id_utilisateur) FROM utilisateur), 1));

COMMIT;

-- ============================================================================
-- VÉRIFICATION : requêtes de contrôle
-- ============================================================================
-- SELECT s.matricule, s.nom, s.prenom, s.email, s.date_embauche, ss.designation AS statut_salarie
-- FROM salarie s JOIN status_salarie ss ON ss.id = s.statut
-- WHERE s.matricule = 'MAT-INF-0001';
--
-- SELECT u.identifiant, u.role, u.actif, u.doit_changer_mot_de_passe
-- FROM utilisateur u JOIN employe e ON e.id = u.id_employe
-- JOIN salarie s ON s.id_salarie = e.id_salarie
-- WHERE s.matricule = 'MAT-INF-0001';
--
-- SELECT d.nom_fichier, td.designation AS type_document, d.niveau_confidentialite
-- FROM document d JOIN type_document td ON td.id = d.type_document
-- JOIN salarie s ON s.id_salarie = d.id_salarie
-- WHERE s.matricule = 'MAT-INF-0001' AND d.archive = false;
-- ============================================================================


-- psql -U postgres -d gestion_rh -c "
-- UPDATE utilisateur
-- SET mot_de_passe_hash = '$2y$12$xlsAXH1floIbHu.Cb2McZutEs0ChL/aj5vPLMujpl2Hpq4VmP4pWW',
--     doit_changer_mot_de_passe = false
-- WHERE identifiant = 'tdurand';
