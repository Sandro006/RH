-- ============================================================================
-- 04_salarie_mat_dir_0001.sql — Données complètes pour le salarié MAT-DIR-0001
--
-- Contenu :
--   1. Identité complète du salarié MAT-DIR-0001 (Fanja Rakotondrabe)
--   2. Contrats (carrière : CDI actuel + CDI précédent)
--   3. Affectations correspondantes
--   4. Historique des statuts
--   5. Documents administratifs (5 pièces PDF réelles, téléchargeables)
--
-- Prérequis : 01_migrations.sql et 02_seeds.sql exécutés
-- Les fichiers PDF doivent être présents dans writable/uploads/documents/
--   (voir les UUID ci-dessous ; générés pour la démonstration).
-- Utilisation : psql -U postgres -d gestion_rh -f script/db/04_salarie_mat_dir_0001.sql
-- Idempotent : peut être relancé sans dupliquer les lignes
-- ============================================================================

BEGIN;

-- ============================================================================
-- 1. IDENTITÉ COMPLÈTE
-- ============================================================================
UPDATE salarie
SET date_naissance      = DATE '1978-04-12',
    lieu_naissance      = 'Antananarivo',
    nationalite         = 'Malagasy',
    situation_familiale = 'Mariée',
    adresse             = 'Lot II M 45 Bis, Ankadivato',
    ville               = 'Antananarivo',
    pays                = 'Madagascar',
    date_sortie         = NULL,
    statut              = (SELECT id FROM status_salarie WHERE designation = 'Actif')
WHERE matricule = 'MAT-DIR-0001';

-- ============================================================================
-- 2. AFFECTATIONS
-- ============================================================================
-- Affectation 1 : Responsable Administration du Personnel (2018-01-15 -> 2021-12-31)
INSERT INTO affectation (id_salarie, id_poste, date_debut, date_fin, motif)
SELECT s.id_salarie, 3, DATE '2018-01-15', DATE '2021-12-31', 'Prise de fonction'
FROM salarie s
WHERE s.matricule = 'MAT-DIR-0001'
  AND NOT EXISTS (
        SELECT 1 FROM affectation a
        WHERE a.id_salarie = s.id_salarie
          AND a.id_poste = 3
          AND a.date_debut = DATE '2018-01-15'
  );

-- Affectation 2 : Directeur général (2022-01-01 -> en cours)
INSERT INTO affectation (id_salarie, id_poste, date_debut, date_fin, motif)
SELECT s.id_salarie, 16, DATE '2022-01-01', NULL, 'Nomination au poste de Directeur général'
FROM salarie s
WHERE s.matricule = 'MAT-DIR-0001'
  AND NOT EXISTS (
        SELECT 1 FROM affectation a
        WHERE a.id_salarie = s.id_salarie
          AND a.id_poste = 16
          AND a.date_debut = DATE '2022-01-01'
  );

-- ============================================================================
-- 3. CONTRATS
-- ============================================================================
-- Contrat actuel : CDI Directeur général (2022-01-01 -> en cours), 95 000
-- On met à jour le contrat pré-existant s'il pointe sur le mauvais poste/statut.
UPDATE contrat
SET id_poste            = 16,
    type_contrat_id     = (SELECT id FROM type_contrat WHERE designation = 'CDI'),
    date_debut          = DATE '2022-01-01',
    date_fin            = NULL,
    salaire_base        = 95000.00,
    periode_essai_debut = NULL,
    periode_essai_fin   = NULL,
    date_signature      = DATE '2021-12-20',
    statut              = (SELECT id FROM status_contrat WHERE designation = 'En cours')
WHERE id_contrat = (
    SELECT MIN(c.id_contrat)
    FROM contrat c
    JOIN salarie s ON s.id_salarie = c.id_salarie
    WHERE s.matricule = 'MAT-DIR-0001'
);

-- Contrat précédent : CDI Responsable Administration du Personnel (2018-01-15 -> 2021-12-31), 65 000
INSERT INTO contrat (id_salarie, id_poste, type_contrat_id, date_debut, date_fin,
                    salaire_base, periode_essai_debut, periode_essai_fin, date_signature, statut)
SELECT s.id_salarie, 3,
       (SELECT id FROM type_contrat WHERE designation = 'CDI'),
       DATE '2018-01-15', DATE '2021-12-31', 65000.00,
       DATE '2018-01-15', DATE '2018-07-14', DATE '2018-01-10',
       (SELECT id FROM status_contrat WHERE designation = 'Terminé')
FROM salarie s
WHERE s.matricule = 'MAT-DIR-0001'
  AND NOT EXISTS (
        SELECT 1 FROM contrat c
        WHERE c.id_salarie = s.id_salarie
          AND c.id_poste = 3
          AND c.date_debut = DATE '2018-01-15'
  );

-- ============================================================================
-- 4. HISTORIQUE DES STATUTS
-- ============================================================================
INSERT INTO salarie_status_historique (id_salarie, statut_ancien, statut_nouveau, date_changement, id_utilisateur, commentaire)
SELECT s.id_salarie,
       (SELECT id FROM status_salarie WHERE designation = 'En attente d''embauche'),
       (SELECT id FROM status_salarie WHERE designation = 'En période d''essai'),
       TIMESTAMPTZ '2018-01-15 09:00:00+03', NULL, 'Entrée dans l''entreprise'
FROM salarie s
WHERE s.matricule = 'MAT-DIR-0001'
  AND NOT EXISTS (
        SELECT 1 FROM salarie_status_historique h
        WHERE h.id_salarie = s.id_salarie
          AND h.commentaire = 'Entrée dans l''entreprise'
  );

INSERT INTO salarie_status_historique (id_salarie, statut_ancien, statut_nouveau, date_changement, id_utilisateur, commentaire)
SELECT s.id_salarie,
       (SELECT id FROM status_salarie WHERE designation = 'En période d''essai'),
       (SELECT id FROM status_salarie WHERE designation = 'Actif'),
       TIMESTAMPTZ '2018-07-15 09:00:00+03', NULL, 'Validation de la période d''essai'
FROM salarie s
WHERE s.matricule = 'MAT-DIR-0001'
  AND NOT EXISTS (
        SELECT 1 FROM salarie_status_historique h
        WHERE h.id_salarie = s.id_salarie
          AND h.commentaire = 'Validation de la période d''essai'
  );

-- ============================================================================
-- 5. DOCUMENTS ADMINISTRATIFS (fichiers PDF réels dans writable/uploads/documents/)
-- ============================================================================
-- 5.1 Contrat de travail — CDI Directeur général (rh, sans expiration)
INSERT INTO document (id_salarie, type_document, nom_fichier, chemin_fichier, date_depot,
                      date_emission, date_expiration, niveau_confidentialite, id_utilisateur_depot,
                      mime_type, taille_octets, checksum_sha256, archive, remplace_par)
SELECT s.id_salarie, (SELECT id FROM type_document WHERE designation = 'Contrat de travail'),
       'Contrat CDI - Fanja Rakotondrabe.pdf',
       'documents/11111111-1111-4111-8111-111111111111.pdf',
       TIMESTAMPTZ '2022-01-02 10:00:00+03', DATE '2022-01-01', NULL, 'rh',
       (SELECT id_utilisateur FROM utilisateur WHERE identifiant = 'rh'),
       'application/pdf', 817,
       'b7e21253e12d5052e6304fa2600a8c180961516ef9611284a40701a46aa482b1',
       false, NULL
FROM salarie s
WHERE s.matricule = 'MAT-DIR-0001'
  AND NOT EXISTS (SELECT 1 FROM document d WHERE d.chemin_fichier = 'documents/11111111-1111-4111-8111-111111111111.pdf');

-- 5.2 Pièce d'identité — CIN (rh, valide jusqu'en 2032)
INSERT INTO document (id_salarie, type_document, nom_fichier, chemin_fichier, date_depot,
                      date_emission, date_expiration, niveau_confidentialite, id_utilisateur_depot,
                      mime_type, taille_octets, checksum_sha256, archive, remplace_par)
SELECT s.id_salarie, (SELECT id FROM type_document WHERE designation = 'Pièce d''identité'),
       'CIN - Fanja Rakotondrabe.pdf',
       'documents/22222222-2222-4222-8222-222222222222.pdf',
       TIMESTAMPTZ '2022-02-02 10:00:00+03', DATE '2022-02-01', DATE '2032-02-01', 'rh',
       (SELECT id_utilisateur FROM utilisateur WHERE identifiant = 'rh'),
       'application/pdf', 800,
       '0c84f1db54d51dbab92261244f6f31b83578714d28ae76185f24537d2c606b41',
       false, NULL
FROM salarie s
WHERE s.matricule = 'MAT-DIR-0001'
  AND NOT EXISTS (SELECT 1 FROM document d WHERE d.chemin_fichier = 'documents/22222222-2222-4222-8222-222222222222.pdf');

-- 5.3 Certificat médical (rh, expiré)
INSERT INTO document (id_salarie, type_document, nom_fichier, chemin_fichier, date_depot,
                      date_emission, date_expiration, niveau_confidentialite, id_utilisateur_depot,
                      mime_type, taille_octets, checksum_sha256, archive, remplace_par)
SELECT s.id_salarie, (SELECT id FROM type_document WHERE designation = 'Certificat médical'),
       'Certificat medical 2025.pdf',
       'documents/33333333-3333-4333-8333-333333333333.pdf',
       TIMESTAMPTZ '2025-10-16 10:00:00+03', DATE '2025-10-15', DATE '2026-10-15', 'rh',
       (SELECT id_utilisateur FROM utilisateur WHERE identifiant = 'rh'),
       'application/pdf', 771,
       'ef1d4c423aeba1edad80b0441f9f4b98e7bc3770fae16afbfe362e551155646f',
       false, NULL
FROM salarie s
WHERE s.matricule = 'MAT-DIR-0001'
  AND NOT EXISTS (SELECT 1 FROM document d WHERE d.chemin_fichier = 'documents/33333333-3333-4333-8333-333333333333.pdf');

-- 5.4 RIB (restreint, sans expiration)
INSERT INTO document (id_salarie, type_document, nom_fichier, chemin_fichier, date_depot,
                      date_emission, date_expiration, niveau_confidentialite, id_utilisateur_depot,
                      mime_type, taille_octets, checksum_sha256, archive, remplace_par)
SELECT s.id_salarie, (SELECT id FROM type_document WHERE designation = 'RIB'),
       'RIB - Fanja Rakotondrabe.pdf',
       'documents/44444444-4444-4444-8444-444444444444.pdf',
       TIMESTAMPTZ '2022-01-06 10:00:00+03', DATE '2022-01-05', NULL, 'restreint',
       (SELECT id_utilisateur FROM utilisateur WHERE identifiant = 'rh'),
       'application/pdf', 790,
       '182f442c117bd35b8d55b8167892df350cee27d9137f2708fe06a6336069f2a1',
       false, NULL
FROM salarie s
WHERE s.matricule = 'MAT-DIR-0001'
  AND NOT EXISTS (SELECT 1 FROM document d WHERE d.chemin_fichier = 'documents/44444444-4444-4444-8444-444444444444.pdf');

-- 5.5 Attestation de travail (interne, expire bientôt)
INSERT INTO document (id_salarie, type_document, nom_fichier, chemin_fichier, date_depot,
                      date_emission, date_expiration, niveau_confidentialite, id_utilisateur_depot,
                      mime_type, taille_octets, checksum_sha256, archive, remplace_par)
SELECT s.id_salarie, (SELECT id FROM type_document WHERE designation = 'Attestation de travail'),
       'Attestation de travail 2026.pdf',
       'documents/55555555-5555-4555-8555-555555555555.pdf',
       TIMESTAMPTZ '2026-01-02 10:00:00+03', DATE '2026-01-01', DATE '2026-12-01', 'interne',
       (SELECT id_utilisateur FROM utilisateur WHERE identifiant = 'rh'),
       'application/pdf', 795,
       '46c3d9cc7ed7ec853d408dbf43af7e74a26b8ebbd5977a946b1bc64ced4175d8',
       false, NULL
FROM salarie s
WHERE s.matricule = 'MAT-DIR-0001'
  AND NOT EXISTS (SELECT 1 FROM document d WHERE d.chemin_fichier = 'documents/55555555-5555-4555-8555-555555555555.pdf');

COMMIT;
