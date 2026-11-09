-- ============================================================================
-- 12_correctif_conges_sans_solde.sql — Correction seeds Congés (SANS_SOLDE)
--
-- Pourquoi : sur certaines bases, SANS_SOLDE restait paramétré
-- deduit_solde = true / justificatif_requis = true, ce qui faisait échouer
-- la demande « Congé sans solde » avec :
--   « Solde insuffisant pour 2026 (disponible : 0, demandé : 5) »
-- et affichait « Un justificatif est requis » à tort.
--
-- Le congé sans solde est par définition : non payé (est_paye = false)
-- et non débité (deduit_solde = false) ; aucun justificatif requis.
-- (08_migration_conges_bloc7.sql actuel contient déjà ces valeurs ;
--  ce script aligne les bases créées avec un ancien brouillon des seeds.)
--
-- Utilisation :
--   psql -U postgres -d gestion_rh -f script/db/12_correctif_conges_sans_solde.sql
-- Idempotent : peut être relancé sans erreur (le SET est constant).
-- ============================================================================

BEGIN;

UPDATE type_conge
SET deduit_solde = false,
    justificatif_requis = false
WHERE code = 'SANS_SOLDE';

COMMIT;
