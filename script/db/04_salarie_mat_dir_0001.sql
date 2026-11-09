-- ============================================================================
-- 04_salarie_mat_dir_0001.sql — Données complètes pour le salarié MAT-DIR-0001
--
-- Contenu :
--   1. Salarié MAT-DIR-0001 (Fanja Rakotondrabe - Directeur général)
--   2. Employé associé
--   3. Contrats (carrière)
--   4. Affectations
--   5. Documents
--   6. Historiques de statut
--   7. Onboarding (intégration)
--
-- Prérequis : 01_migrations.sql et 02_seeds.sql exécutés
-- Utilisation : psql -U postgres -d gestion_rh -f script/db/04_salarie_mat_dir_0001.sql
-- Idempotent : peut être relancé sans dupliquer les lignes
-- ============================================================================

BEGIN;

-- ============================================================================
-- 1. SALARIÉ MAT-DIR-0001
-- ============================================================================
-- Le salarié existe déjà dans 02_seeds.sql, mais on l'insère de manière idempotente
INSERT INTO salarie (id_salarie, id_candidat, matricule, nom, prenom, email, telephone, 
                   date_naissance, lieu_naissance, nationalite, situation_familiale, 
                   adresse, ville, pays, date_embauche, date_sortie, statut)
SELECT v.id_salarie, v.id_candidat, v.matricule, v.nom, v.prenom, v.email, v.telephone,
       v.date_naissance, v.lieu_naissance, v.nationalite, v.situation_familiale,
       v.adresse, v.ville, v.pays, v.date_embauche, v.date_sortie, ss.id
FROM (VALUES
    (100, NULL, 'MAT-DIR-0001', 'Rakotondrabe', 'Fanja', 'fanja.rakotondrabe@example.test',
     '0320000011', '1978-05-04', 'Antananarivo', 'Malgache', 'Marié(e)',
     '123 Avenue de l''Indépendance, Antananarivo 101', 'Antananarivo', 'Madagascar',
     '2018-01-15', NULL)
) AS v(id_salarie, id_candidat, matricule, nom, prenom, email, telephone,
       date_naissance, lieu_naissance, nationalite, situation_familiale,
       adresse, ville, pays, date_embauche, date_sortie)
JOIN status_salarie ss ON ss.designation = 'Actif'
WHERE NOT EXISTS (
    SELECT 1 FROM salarie s WHERE s.matricule = 'MAT-DIR-0001'
);

-- ============================================================================
-- 2. EMPLOYÉ ASSOCIÉ
-- ============================================================================
INSERT INTO employe (id, nom, date_de_naissance, poste_id, id_salarie)
SELECT v.id, v.nom, v.date_de_naissance, p.id_poste, s.id_salarie
FROM (VALUES
    (100, 'Directeur général - Fanja', '1978-05-04', 'Directeur général', 'MAT-DIR-0001')
) AS v(id, nom, date_de_naissance, poste, matricule)
JOIN poste p ON p.intitule = v.poste
JOIN salarie s ON s.matricule = v.matricule
WHERE NOT EXISTS (
    SELECT 1 FROM employe e WHERE e.id_salarie = s.id_salarie
);

-- ============================================================================
-- 3. CONTRATS (CARRIÈRE)
-- ============================================================================

-- Premier contrat CDD (2018-2019)
INSERT INTO contrat (id_contrat, id_salarie, id_poste, type_contrat_id, date_debut, date_fin,
                    salaire_base, periode_essai_debut, periode_essai_fin, date_signature, statut)
SELECT v.id_contrat, s.id_salarie, p.id_poste, tc.id, v.date_debut, v.date_fin,
       v.salaire_base, v.periode_essai_debut, v.periode_essai_fin, v.date_signature, sc.id
FROM (VALUES
    (1001, '2018-01-15', '2019-01-14', 75000.00, '2018-01-15', '2018-04-15', '2018-01-10')
) AS v(id_contrat, date_debut, date_fin, salaire_base, periode_essai_debut, 
       periode_essai_fin, date_signature)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN poste p ON p.intitule = 'Directeur général'
JOIN type_contrat tc ON tc.designation = 'CDD'
JOIN status_contrat sc ON sc.designation = 'Terminé'
WHERE NOT EXISTS (
    SELECT 1 FROM contrat c WHERE c.id_contrat = v.id_contrat
);

-- Deuxième contrat CDI (2019 - présent)
INSERT INTO contrat (id_contrat, id_salarie, id_poste, type_contrat_id, date_debut, date_fin,
                    salaire_base, periode_essai_debut, periode_essai_fin, date_signature, statut)
SELECT v.id_contrat, s.id_salarie, p.id_poste, tc.id, v.date_debut, v.date_fin,
       v.salaire_base, v.periode_essai_debut, v.periode_essai_fin, v.date_signature, sc.id
FROM (VALUES
    (1002, '2019-01-15', NULL, 85000.00, '2019-01-15', '2019-04-15', '2019-01-10')
) AS v(id_contrat, date_debut, date_fin, salaire_base, periode_essai_debut, 
       periode_essai_fin, date_signature)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN poste p ON p.intitule = 'Directeur général'
JOIN type_contrat tc ON tc.designation = 'CDI'
JOIN status_contrat sc ON sc.designation = 'En cours'
WHERE NOT EXISTS (
    SELECT 1 FROM contrat c WHERE c.id_contrat = v.id_contrat
);

-- ============================================================================
-- 4. AFFECTATIONS
-- ============================================================================

-- Première affectation (2018-2019) - Service Direction générale
INSERT INTO affectation (id_affectation, id_salarie, id_poste, date_debut, date_fin, motif)
SELECT v.id_affectation, s.id_salarie, p.id_poste, v.date_debut, v.date_fin, v.motif
FROM (VALUES
    (1001, '2018-01-15', '2019-01-14', 'Première affectation en tant que Directeur général')
) AS v(id_affectation, date_debut, date_fin, motif)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN poste p ON p.intitule = 'Directeur général'
WHERE NOT EXISTS (
    SELECT 1 FROM affectation a WHERE a.id_affectation = v.id_affectation
);

-- Deuxième affectation (2019 - présent) - Service Direction générale (renouvellement)
INSERT INTO affectation (id_affectation, id_salarie, id_poste, date_debut, date_fin, motif)
SELECT v.id_affectation, s.id_salarie, p.id_poste, v.date_debut, v.date_fin, v.motif
FROM (VALUES
    (1002, '2019-01-15', NULL, 'Renouvellement après passage en CDI')
) AS v(id_affectation, date_debut, date_fin, motif)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN poste p ON p.intitule = 'Directeur général'
WHERE NOT EXISTS (
    SELECT 1 FROM affectation a WHERE a.id_affectation = v.id_affectation
);

-- ============================================================================
-- 5. DOCUMENTS
-- ============================================================================

-- CV
INSERT INTO document (id_document, id_salarie, type_document, nom_fichier, chemin_fichier,
                      date_depot, date_emission, date_expiration, niveau_confidentialite,
                      mime_type, taille_octets)
SELECT v.id_document, s.id_salarie, td.id, v.nom_fichier, v.chemin_fichier,
       v.date_depot, v.date_emission, v.date_expiration, v.niveau_confidentialite,
       v.mime_type, v.taille_octets
FROM (VALUES
    (1001, 'CV_Rakotondrabe_Fanja.pdf', '/documents/MAT-DIR-0001/cv/CV_Rakotondrabe_Fanja.pdf',
     '2018-01-10', '2018-01-01', NULL, 'interne', 'application/pdf', 245760)
) AS v(id_document, nom_fichier, chemin_fichier, date_depot, date_emission, 
       date_expiration, niveau_confidentialite, mime_type, taille_octets)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN type_document td ON td.designation = 'CV'
WHERE NOT EXISTS (
    SELECT 1 FROM document d WHERE d.id_document = v.id_document
);

-- Contrat de travail (CDD)
INSERT INTO document (id_document, id_salarie, type_document, nom_fichier, chemin_fichier,
                      date_depot, date_emission, date_expiration, niveau_confidentialite,
                      mime_type, taille_octets)
SELECT v.id_document, s.id_salarie, td.id, v.nom_fichier, v.chemin_fichier,
       v.date_depot, v.date_emission, v.date_expiration, v.niveau_confidentialite,
       v.mime_type, v.taille_octets
FROM (VALUES
    (1002, 'Contrat_CDD_2018-2019_Rakotondrabe.pdf', '/documents/MAT-DIR-0001/contrats/Contrat_CDD_2018-2019_Rakotondrabe.pdf',
     '2018-01-15', '2018-01-15', '2019-01-14', 'rh', 'application/pdf', 524288)
) AS v(id_document, nom_fichier, chemin_fichier, date_depot, date_emission, 
       date_expiration, niveau_confidentialite, mime_type, taille_octets)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN type_document td ON td.designation = 'Contrat de travail'
WHERE NOT EXISTS (
    SELECT 1 FROM document d WHERE d.id_document = v.id_document
);

-- Contrat de travail (CDI)
INSERT INTO document (id_document, id_salarie, type_document, nom_fichier, chemin_fichier,
                      date_depot, date_emission, date_expiration, niveau_confidentialite,
                      mime_type, taille_octets)
SELECT v.id_document, s.id_salarie, td.id, v.nom_fichier, v.chemin_fichier,
       v.date_depot, v.date_emission, v.date_expiration, v.niveau_confidentialite,
       v.mime_type, v.taille_octets
FROM (VALUES
    (1003, 'Contrat_CDI_2019_Rakotondrabe.pdf', '/documents/MAT-DIR-0001/contrats/Contrat_CDI_2019_Rakotondrabe.pdf',
     '2019-01-15', '2019-01-15', NULL, 'rh', 'application/pdf', 589824)
) AS v(id_document, nom_fichier, chemin_fichier, date_depot, date_emission, 
       date_expiration, niveau_confidentialite, mime_type, taille_octets)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN type_document td ON td.designation = 'Contrat de travail'
WHERE NOT EXISTS (
    SELECT 1 FROM document d WHERE d.id_document = v.id_document
);

-- Pièce d'identité
INSERT INTO document (id_document, id_salarie, type_document, nom_fichier, chemin_fichier,
                      date_depot, date_emission, date_expiration, niveau_confidentialite,
                      mime_type, taille_octets)
SELECT v.id_document, s.id_salarie, td.id, v.nom_fichier, v.chemin_fichier,
       v.date_depot, v.date_emission, v.date_expiration, v.niveau_confidentialite,
       v.mime_type, v.taille_octets
FROM (VALUES
    (1004, 'CIN_Rakotondrabe_Fanja.pdf', '/documents/MAT-DIR-0001/identite/CIN_Rakotondrabe_Fanja.pdf',
     '2018-01-10', '2015-03-20', '2025-03-20', 'rh', 'application/pdf', 393216)
) AS v(id_document, nom_fichier, chemin_fichier, date_depot, date_emission, 
       date_expiration, niveau_confidentialite, mime_type, taille_octets)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN type_document td ON td.designation = 'Pièce d''identité'
WHERE NOT EXISTS (
    SELECT 1 FROM document d WHERE d.id_document = v.id_document
);

-- Diplôme
INSERT INTO document (id_document, id_salarie, type_document, nom_fichier, chemin_fichier,
                      date_depot, date_emission, date_expiration, niveau_confidentialite,
                      mime_type, taille_octets)
SELECT v.id_document, s.id_salarie, td.id, v.nom_fichier, v.chemin_fichier,
       v.date_depot, v.date_emission, v.date_expiration, v.niveau_confidentialite,
       v.mime_type, v.taille_octets
FROM (VALUES
    (1005, 'Diplome_Master_Gestion_Rakotondrabe.pdf', '/documents/MAT-DIR-0001/diplomes/Diplome_Master_Gestion_Rakotondrabe.pdf',
     '2018-01-10', '2001-07-15', NULL, 'interne', 'application/pdf', 458752)
) AS v(id_document, nom_fichier, chemin_fichier, date_depot, date_emission, 
       date_expiration, niveau_confidentialite, mime_type, taille_octets)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN type_document td ON td.designation = 'Diplôme'
WHERE NOT EXISTS (
    SELECT 1 FROM document d WHERE d.id_document = v.id_document
);

-- Certificat médical
INSERT INTO document (id_document, id_salarie, type_document, nom_fichier, chemin_fichier,
                      date_depot, date_emission, date_expiration, niveau_confidentialite,
                      mime_type, taille_octets)
SELECT v.id_document, s.id_salarie, td.id, v.nom_fichier, v.chemin_fichier,
       v.date_depot, v.date_emission, v.date_expiration, v.niveau_confidentialite,
       v.mime_type, v.taille_octets
FROM (VALUES
    (1006, 'Certificat_Medical_2018_Rakotondrabe.pdf', '/documents/MAT-DIR-0001/sante/Certificat_Medical_2018_Rakotondrabe.pdf',
     '2018-01-12', '2018-01-10',  DATE '2019-01-10', 'rh', 'application/pdf', 131072)
) AS v(id_document, nom_fichier, chemin_fichier, date_depot, date_emission, 
       date_expiration, niveau_confidentialite, mime_type, taille_octets)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN type_document td ON td.designation = 'Certificat médical'
WHERE NOT EXISTS (
    SELECT 1 FROM document d WHERE d.id_document = v.id_document
);

-- RIB
INSERT INTO document (id_document, id_salarie, type_document, nom_fichier, chemin_fichier,
                      date_depot, date_emission, date_expiration, niveau_confidentialite,
                      mime_type, taille_octets)
SELECT v.id_document, s.id_salarie, td.id, v.nom_fichier, v.chemin_fichier,
       v.date_depot, v.date_emission, v.date_expiration, v.niveau_confidentialite,
       v.mime_type, v.taille_octets
FROM (VALUES
    (1007, 'RIB_Rakotondrabe_Fanja.pdf', '/documents/MAT-DIR-0001/bancaire/RIB_Rakotondrabe_Fanja.pdf',
     '2018-01-10', '2018-01-05', NULL, 'restreint', 'application/pdf', 98304)
) AS v(id_document, nom_fichier, chemin_fichier, date_depot, date_emission, 
       date_expiration, niveau_confidentialite, mime_type, taille_octets)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN type_document td ON td.designation = 'RIB'
WHERE NOT EXISTS (
    SELECT 1 FROM document d WHERE d.id_document = v.id_document
);

-- ============================================================================
-- 6. HISTORIQUES DE STATUT
-- ============================================================================

-- Historique du salarié (de En attente d'embauche à Actif)
INSERT INTO salarie_status_historique (id_historique, id_salarie, statut_ancien, statut_nouveau,
                                        date_changement, id_utilisateur, commentaire)
SELECT v.id_historique, s.id_salarie, ss_ancien.id, ss_nouveau.id, v.date_changement,
       u.id_utilisateur, v.commentaire
FROM (VALUES
    (1001, '2018-01-15', 'Passage en statut Actif après embauche')
) AS v(id_historique, date_changement, commentaire)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN status_salarie ss_ancien ON ss_ancien.designation = 'En attente d''embauche'
JOIN status_salarie ss_nouveau ON ss_nouveau.designation = 'Actif'
JOIN utilisateur u ON u.identifiant = 'admin'
WHERE NOT EXISTS (
    SELECT 1 FROM salarie_status_historique h WHERE h.id_historique = v.id_historique
);

-- Historique du contrat CDD (de En préparation à Terminé)
INSERT INTO contrat_status_historique (id_historique, id_contrat, statut_ancien, statut_nouveau,
                                        date_changement, id_utilisateur, commentaire)
SELECT v.id_historique, c.id_contrat, sc_ancien.id, sc_nouveau.id, v.date_changement,
       u.id_utilisateur, v.commentaire
FROM (VALUES
    (1001, 1001, '2018-01-10', 'Création du contrat'),
    (1002, 1001, '2018-01-15', 'Contrat signé'),
    (1003, 1001, '2019-01-14', 'Fin du contrat CDD')
) AS v(id_historique, id_contrat, date_changement, commentaire)
JOIN contrat c ON c.id_contrat = v.id_contrat
JOIN status_contrat sc_ancien ON CASE v.commentaire
    WHEN 'Création du contrat' THEN sc_ancien.designation = 'En préparation'
    WHEN 'Contrat signé' THEN sc_ancien.designation = 'En attente de signature'
    WHEN 'Fin du contrat CDD' THEN sc_ancien.designation = 'En cours'
END
JOIN status_contrat sc_nouveau ON CASE v.commentaire
    WHEN 'Création du contrat' THEN sc_nouveau.designation = 'En préparation'
    WHEN 'Contrat signé' THEN sc_nouveau.designation = 'Signé'
    WHEN 'Fin du contrat CDD' THEN sc_nouveau.designation = 'Terminé'
END
JOIN utilisateur u ON u.identifiant = 'admin'
WHERE NOT EXISTS (
    SELECT 1 FROM contrat_status_historique h WHERE h.id_historique = v.id_historique
);

-- Historique du contrat CDI (de En préparation à En cours)
INSERT INTO contrat_status_historique (id_historique, id_contrat, statut_ancien, statut_nouveau,
                                        date_changement, id_utilisateur, commentaire)
SELECT v.id_historique, c.id_contrat, sc_ancien.id, sc_nouveau.id, v.date_changement,
       u.id_utilisateur, v.commentaire
FROM (VALUES
    (1004, 1002, '2019-01-10', 'Création du contrat CDI'),
    (1005, 1002, '2019-01-15', 'Contrat CDI signé et actif')
) AS v(id_historique, id_contrat, date_changement, commentaire)
JOIN contrat c ON c.id_contrat = v.id_contrat
JOIN status_contrat sc_ancien ON CASE v.commentaire
    WHEN 'Création du contrat CDI' THEN sc_ancien.designation = 'En préparation'
    WHEN 'Contrat CDI signé et actif' THEN sc_ancien.designation = 'En attente de signature'
END
JOIN status_contrat sc_nouveau ON CASE v.commentaire
    WHEN 'Création du contrat CDI' THEN sc_nouveau.designation = 'En préparation'
    WHEN 'Contrat CDI signé et actif' THEN sc_nouveau.designation = 'En cours'
END
JOIN utilisateur u ON u.identifiant = 'admin'
WHERE NOT EXISTS (
    SELECT 1 FROM contrat_status_historique h WHERE h.id_historique = v.id_historique
);

-- ============================================================================
-- 7. ONBOARDING (INTÉGRATION)
-- ============================================================================

-- Dossier d'intégration initial (2018)
INSERT INTO onboarding (id_onboarding, statut, id_salarie, date_debut, date_fin)
SELECT v.id_onboarding, so.id, s.id_salarie, v.date_debut, v.date_fin
FROM (VALUES
    (1001, '2018-01-15', '2018-02-15')
) AS v(id_onboarding, date_debut, date_fin)
JOIN salarie s ON s.matricule = 'MAT-DIR-0001'
JOIN status_onboarding so ON so.designation = 'Terminé'
WHERE NOT EXISTS (
    SELECT 1 FROM onboarding o WHERE o.id_onboarding = v.id_onboarding
);

-- Tâches d'intégration
INSERT INTO onboarding_tache (id_tache, statut, id_onboarding, intitule, description, responsable,
                              date_echeance, date_realisation)
SELECT v.id_tache, sot.id, o.id_onboarding, v.intitule, v.description, v.responsable,
       v.date_echeance, v.date_realisation
FROM (VALUES
    (1001, 1001, 'Remise du kit de bienvenue', 'Documentation et équipement', 'RH', '2018-01-20', '2018-01-18'),
    (1002, 1001, 'Présentation de l''équipe', 'Réunion avec les départements', 'Directeur général', '2018-01-25', '2018-01-22'),
    (1003, 1001, 'Formation aux outils internes', 'Système d''information et processus', 'IT', '2018-02-28', '2018-02-15'),
    (1004, 1001, 'Premier comité de direction', 'Participation aux décisions stratégiques', 'Directeur général', '2018-02-10', '2018-02-05')
) AS v(id_tache, id_onboarding, intitule, description, responsable, date_echeance, date_realisation)
JOIN onboarding o ON o.id_onboarding = v.id_onboarding
JOIN status_onboarding_tache sot ON sot.designation = 'Terminée'
WHERE NOT EXISTS (
    SELECT 1 FROM onboarding_tache t WHERE t.id_tache = v.id_tache
);

-- ============================================================================
-- 8. MISE À NIVEAU DES SÉQUENCES
-- ============================================================================
SELECT setval(pg_get_serial_sequence('salarie', 'id_salarie'),
              GREATEST((SELECT MAX(id_salarie) FROM salarie), 1));
SELECT setval(pg_get_serial_sequence('employe', 'id'),
              GREATEST((SELECT MAX(id) FROM employe), 1));
SELECT setval(pg_get_serial_sequence('contrat', 'id_contrat'),
              GREATEST((SELECT MAX(id_contrat) FROM contrat), 1));
SELECT setval(pg_get_serial_sequence('affectation', 'id_affectation'),
              GREATEST((SELECT MAX(id_affectation) FROM affectation), 1));
SELECT setval(pg_get_serial_sequence('document', 'id_document'),
              GREATEST((SELECT MAX(id_document) FROM document), 1));
SELECT setval(pg_get_serial_sequence('onboarding', 'id_onboarding'),
              GREATEST((SELECT MAX(id_onboarding) FROM onboarding), 1));
SELECT setval(pg_get_serial_sequence('onboarding_tache', 'id_tache'),
              GREATEST((SELECT MAX(id_tache) FROM onboarding_tache), 1));

COMMIT;

-- ============================================================================
-- VERTICALICATION
-- ============================================================================
SELECT '=== SALARI ===' AS section;
SELECT * FROM salarie WHERE matricule = 'MAT-DIR-0001';

SELECT '=== EMPLOY ===' AS section;
SELECT e.*, p.intitule AS poste_intitule 
FROM employe e
JOIN poste p ON e.poste_id = p.id_poste
JOIN salarie s ON e.id_salarie = s.id_salarie
WHERE s.matricule = 'MAT-DIR-0001';

SELECT '=== CONTRATS ===' AS section;
SELECT c.*, tc.designation AS type_contrat, sc.designation AS statut_contrat, p.intitule AS poste_intitule
FROM contrat c
JOIN type_contrat tc ON c.type_contrat_id = tc.id
JOIN status_contrat sc ON c.statut = sc.id
JOIN poste p ON c.id_poste = p.id_poste
JOIN salarie s ON c.id_salarie = s.id_salarie
WHERE s.matricule = 'MAT-DIR-0001'
ORDER BY c.date_debut;

SELECT '=== AFFECTATIONS ===' AS section;
SELECT a.*, p.intitule AS poste_intitule
FROM affectation a
JOIN poste p ON a.id_poste = p.id_poste
JOIN salarie s ON a.id_salarie = s.id_salarie
WHERE s.matricule = 'MAT-DIR-0001'
ORDER BY a.date_debut;

SELECT '=== DOCUMENTS ===' AS section;
SELECT d.*, td.designation AS type_document
FROM document d
JOIN type_document td ON d.type_document = td.id
JOIN salarie s ON d.id_salarie = s.id_salarie
WHERE s.matricule = 'MAT-DIR-0001'
ORDER BY d.date_depot;

SELECT '=== HISTORIQUE STATUT SALARI ===' AS section;
SELECT h.*, ss_ancien.designation AS statut_ancien, ss_nouveau.designation AS statut_nouveau,
       u.identifiant AS utilisateur
FROM salarie_status_historique h
JOIN status_salarie ss_ancien ON h.statut_ancien = ss_ancien.id
JOIN status_salarie ss_nouveau ON h.statut_nouveau = ss_nouveau.id
LEFT JOIN utilisateur u ON h.id_utilisateur = u.id_utilisateur
JOIN salarie s ON h.id_salarie = s.id_salarie
WHERE s.matricule = 'MAT-DIR-0001'
ORDER BY h.date_changement;

SELECT '=== HISTORIQUE STATUT CONTRATS ===' AS section;
SELECT h.*, c.id_contrat, sc_ancien.designation AS statut_ancien, sc_nouveau.designation AS statut_nouveau,
       u.identifiant AS utilisateur
FROM contrat_status_historique h
JOIN contrat c ON h.id_contrat = c.id_contrat
JOIN status_contrat sc_ancien ON h.statut_ancien = sc_ancien.id
JOIN status_contrat sc_nouveau ON h.statut_nouveau = sc_nouveau.id
LEFT JOIN utilisateur u ON h.id_utilisateur = u.id_utilisateur
JOIN salarie s ON c.id_salarie = s.id_salarie
WHERE s.matricule = 'MAT-DIR-0001'
ORDER BY h.date_changement;

SELECT '=== ONBOARDING ===' AS section;
SELECT o.*, so.designation AS statut_onboarding
FROM onboarding o
JOIN status_onboarding so ON o.statut = so.id
JOIN salarie s ON o.id_salarie = s.id_salarie
WHERE s.matricule = 'MAT-DIR-0001';

SELECT '=== TACHES ONBOARDING ===' AS section;
SELECT t.*, sot.designation AS statut_tache
FROM onboarding_tache t
JOIN status_onboarding_tache sot ON t.statut = sot.id
JOIN onboarding o ON t.id_onboarding = o.id_onboarding
JOIN salarie s ON o.id_salarie = s.id_salarie
WHERE s.matricule = 'MAT-DIR-0001'
ORDER BY t.date_echeance;
