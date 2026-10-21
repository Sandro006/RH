-- ============================================================================
-- Migration : historique initial des entretiens
-- Base cible : gestion_rh
-- Exécution   : psql -U postgres -d gestion_rh -f migration_entretien.sql
-- ============================================================================

BEGIN;

-- Le premier historique représente : aucun statut -> Planifié.
ALTER TABLE entretien_status_historique
    ALTER COLUMN statut_ancien DROP NOT NULL;

COMMIT;
