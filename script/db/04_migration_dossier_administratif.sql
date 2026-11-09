-- ============================================================================
-- 04_migration_dossier_administratif.sql
--
-- Migration idempotente pour le Bloc 5 — Dossier administratif du salarié.
--
-- À exécuter sur une base existante après 01_migrations.sql :
--   psql -U postgres -d gestion_rh -f script/db/04_migration_dossier_administratif.sql
--
-- Pour une base vierge, les colonnes sont déjà intégrées dans 01_migrations.sql.
-- Le script peut être relancé sans erreur.
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. Identité et coordonnées du salarié
-- ----------------------------------------------------------------------------
ALTER TABLE salarie ADD COLUMN IF NOT EXISTS date_naissance date;
ALTER TABLE salarie ADD COLUMN IF NOT EXISTS lieu_naissance varchar(150);
ALTER TABLE salarie ADD COLUMN IF NOT EXISTS nationalite varchar(100);
ALTER TABLE salarie ADD COLUMN IF NOT EXISTS situation_familiale varchar(50);
ALTER TABLE salarie ADD COLUMN IF NOT EXISTS adresse text;
ALTER TABLE salarie ADD COLUMN IF NOT EXISTS ville varchar(100);
ALTER TABLE salarie ADD COLUMN IF NOT EXISTS pays varchar(100);
ALTER TABLE salarie ADD COLUMN IF NOT EXISTS date_sortie date;

-- ----------------------------------------------------------------------------
-- 2. Paramétrage des types de documents
-- ----------------------------------------------------------------------------
ALTER TABLE type_document ADD COLUMN IF NOT EXISTS obligatoire boolean NOT NULL DEFAULT false;
ALTER TABLE type_document ADD COLUMN IF NOT EXISTS duree_validite_mois integer;
ALTER TABLE type_document ADD COLUMN IF NOT EXISTS niveau_confidentialite varchar(20) NOT NULL DEFAULT 'interne';
ALTER TABLE type_document ADD COLUMN IF NOT EXISTS actif boolean NOT NULL DEFAULT true;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'type_document_confidentialite_check'
          AND conrelid = 'type_document'::regclass
    ) THEN
        ALTER TABLE type_document
            ADD CONSTRAINT type_document_confidentialite_check
            CHECK (niveau_confidentialite IN ('public', 'interne', 'rh', 'restreint'));
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'type_document_validite_check'
          AND conrelid = 'type_document'::regclass
    ) THEN
        ALTER TABLE type_document
            ADD CONSTRAINT type_document_validite_check
            CHECK (duree_validite_mois IS NULL OR duree_validite_mois > 0);
    END IF;
END $$;

-- ----------------------------------------------------------------------------
-- 3. Pièces du dossier documentaire
-- ----------------------------------------------------------------------------
ALTER TABLE document ADD COLUMN IF NOT EXISTS date_emission date;
ALTER TABLE document ADD COLUMN IF NOT EXISTS date_expiration date;
ALTER TABLE document ADD COLUMN IF NOT EXISTS niveau_confidentialite varchar(20) NOT NULL DEFAULT 'interne';
ALTER TABLE document ADD COLUMN IF NOT EXISTS id_utilisateur_depot bigint;
ALTER TABLE document ADD COLUMN IF NOT EXISTS mime_type varchar(100);
ALTER TABLE document ADD COLUMN IF NOT EXISTS taille_octets bigint;
ALTER TABLE document ADD COLUMN IF NOT EXISTS checksum_sha256 varchar(64);
ALTER TABLE document ADD COLUMN IF NOT EXISTS remplace_par bigint;
ALTER TABLE document ADD COLUMN IF NOT EXISTS archive boolean NOT NULL DEFAULT false;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'document_confidentialite_check'
          AND conrelid = 'document'::regclass
    ) THEN
        ALTER TABLE document
            ADD CONSTRAINT document_confidentialite_check
            CHECK (niveau_confidentialite IN ('public', 'interne', 'rh', 'restreint'));
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'document_fk3'
          AND conrelid = 'document'::regclass
    ) THEN
        ALTER TABLE document
            ADD CONSTRAINT document_fk3
            FOREIGN KEY (id_utilisateur_depot) REFERENCES utilisateur(id_utilisateur)
            ON DELETE SET NULL;
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'document_fk4'
          AND conrelid = 'document'::regclass
    ) THEN
        ALTER TABLE document
            ADD CONSTRAINT document_fk4
            FOREIGN KEY (remplace_par) REFERENCES document(id_document)
            ON DELETE SET NULL;
    END IF;
END $$;

-- ----------------------------------------------------------------------------
-- 4. Index utiles au dossier et aux alertes de validité
-- ----------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_document_salarie ON document (id_salarie);
CREATE INDEX IF NOT EXISTS idx_document_type ON document (type_document);
CREATE INDEX IF NOT EXISTS idx_document_expiration ON document (date_expiration);
CREATE INDEX IF NOT EXISTS idx_document_archive ON document (archive);
CREATE INDEX IF NOT EXISTS idx_document_utilisateur ON document (id_utilisateur_depot);
CREATE INDEX IF NOT EXISTS idx_document_salarie_archive ON document (id_salarie, archive);

COMMIT;
