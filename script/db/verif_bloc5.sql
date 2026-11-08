-- ============================================================================
-- verif_bloc5.sql — Vérifications manuelles du Bloc 5
--
-- Utilisation :
--   psql -U postgres -d gestion_rh -f script/db/verif_bloc5.sql
--
-- Ce script ne modifie aucune donnée.
-- ============================================================================

\echo '=== 1. Colonnes ajoutées à salarie ==='
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'salarie'
  AND column_name IN (
      'date_naissance', 'lieu_naissance', 'nationalite', 'situation_familiale',
      'adresse', 'ville', 'pays', 'date_sortie'
  )
ORDER BY ordinal_position;

\echo '=== 2. Colonnes ajoutées à document ==='
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'document'
  AND column_name IN (
      'date_emission', 'date_expiration', 'niveau_confidentialite',
      'id_utilisateur_depot', 'mime_type', 'taille_octets', 'checksum_sha256',
      'remplace_par', 'archive'
  )
ORDER BY ordinal_position;

\echo '=== 3. Colonnes ajoutées à type_document ==='
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'type_document'
  AND column_name IN (
      'obligatoire', 'duree_validite_mois', 'niveau_confidentialite', 'actif'
  )
ORDER BY ordinal_position;

\echo '=== 4. Contraintes et clés étrangères ==='
SELECT conname, contype, pg_get_constraintdef(oid) AS definition
FROM pg_constraint
WHERE conrelid IN ('document'::regclass, 'type_document'::regclass)
  AND conname IN (
      'document_fk3', 'document_fk4', 'document_confidentialite_check',
      'type_document_confidentialite_check', 'type_document_validite_check'
  )
ORDER BY conname;

\echo '=== 5. Métadonnées des types de document ==='
SELECT id, designation, obligatoire, duree_validite_mois, niveau_confidentialite, actif
FROM type_document
ORDER BY id;

\echo '=== 6. Documents avec statut de validité calculé ==='
SELECT
    d.id_document,
    s.matricule,
    td.designation AS type_document,
    d.date_expiration,
    d.niveau_confidentialite,
    d.archive,
    CASE
        WHEN d.archive THEN 'archive'
        WHEN d.date_expiration IS NULL THEN 'valide'
        WHEN d.date_expiration < CURRENT_DATE THEN 'expire'
        WHEN d.date_expiration <= CURRENT_DATE + 30 THEN 'expire_bientot'
        ELSE 'valide'
    END AS statut_validite
FROM document d
JOIN salarie s ON s.id_salarie = d.id_salarie
JOIN type_document td ON td.id = d.type_document
ORDER BY d.date_expiration NULLS LAST, d.id_document;

\echo '=== 7. Pièces obligatoires manquantes par salarié ==='
SELECT
    s.id_salarie,
    s.matricule,
    td.designation AS piece_obligatoire
FROM salarie s
CROSS JOIN type_document td
WHERE td.obligatoire = true
  AND td.actif = true
  AND NOT EXISTS (
      SELECT 1
      FROM document d
      WHERE d.id_salarie = s.id_salarie
        AND d.type_document = td.id
        AND d.archive = false
  )
ORDER BY s.matricule, td.designation;
