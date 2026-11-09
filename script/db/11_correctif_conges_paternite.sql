-- ============================================================================
-- 11_correctif_conges_paternite.sql — Correction seeds Congés (PATERNITÉ)
--
-- Pourquoi : dans 08_migration_conges_bloc7.sql, PATERNITE avait
-- deduit_solde = true. Résultat : toute demande « Congé paternité » réservait
-- sur un solde PATERNITE en permanence à 0 (aucun droit n'est jamais acquis
-- pour ce type), d'où « Solde insuffisant pour 2026 (disponible : 0) » même
-- quand l'ANNUEL affiche 25 j libres à droite.
--
-- Le congé de naissance / paternité est payé sans débiter un solde de congés :
-- on le passe donc en deduit_solde = false (comme MALADIE / MATERNITÉ).
--
-- Utilisation :
--   psql -U postgres -d gestion_rh -f script/db/11_correctif_conges_paternite.sql
-- Idempotent : peut être relancé sans erreur (le SET est constant).
-- ============================================================================

BEGIN;

UPDATE type_conge SET deduit_solde = false WHERE code = 'PATERNITE';
UPDATE type_conge
SET deduit_solde = false,
    justificatif_requis = false
WHERE code = 'SANS_SOLDE';

COMMIT;
