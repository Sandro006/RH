-- ============================================================================
-- 15_test_paie_retenues.sql — Test de la retenue automatique sur bulletin
--
-- Objectif : vérifier que BulletinService::calculer() applique seul la
-- retenue congés (jours sans solde + jours injustifiés), sans saisie manuelle
-- du montant, à partir des absences/congés du mois.
--
-- Prérequis (dans cet ordre) : 01_migrations.sql, 02_seeds.sql,
--   08_migration_conges_bloc7.sql, 09_seeds_conges_bloc7.sql,
--   14_migration_paie.sql
--   (09 fournit les contrats DSI « En cours » avec salaire_base et les
--    absences déjà existantes reprises ci-dessous ; 14 fournit les tables
--    paie et le taux CNPS démo : employé 1 %, patronal 13 %.)
-- Utilisation : psql -U postgres -d gestion_rh -f script/db/15_test_paie_retenues.sql
--   (base Neon : remplacer par la chaîne de connexion Neon, ex.
--    psql "<connection_string>" -f script/db/15_test_paie_retenues.sql)
--
-- Après exécution, générer les bulletins (c'est CE geste qui déclenche le
-- calcul automatique — aucun cron paie n'existe dans le code) :
--   • Écran Finance > Bulletins de paie > « Calculer », mois 2026-11,
--     pour chaque salarié ci-dessous (rôles rh/admin) ;
--   • ou API : POST /api/paie/bulletins/calculer  { "id_salarie": <id>, "mois": "2026-11" }
--   • puis re-vérifier avec la requête de contrôle (section 5).
-- Attention : un bulletin déjà VALIDÉ bloque tout recalcul (STATUT_INVALIDE).
--   La section 0 supprime les bulletins non validés du périmètre de test
--   pour rendre le script relançable ; un bulletin validé doit être supprimé
--   à la main (après contrôle) avant de relancer le test.
--
-- Données de test (mois 2026-11, 20 jours ouvrés : 21 lun–ven moins le
-- 11/11 Armistice, férié — cf. CalendrierService::estJourOuvre) :
--
--   Matricule      | Cas testé (novembre 2026)                          | Référence
--   ---------------|--------------------------------------------------------|----------
--   MAT-FIN-0001   | ANNUEL validé 16–20/11 (payé, 09), ABSENCE_         | 09 + §2c/§4
--   (Andry, 43000) | INJUSTIFIEE 26–27/11 (§2c), PRIME +10000 et          |
--                  | RETTENUE manuelle −2000 (éléments, §4)             |
--   MAT-AUTH-0002  | SANS_SOLDE validé 23–24/11 (retenue, 09),           | 09 + §4
--   (RH, 45000)    | RETTENUE manuelle −1500 (§4)                       |
--   MAT-AUTH-0003  | MALADIE non justifiée 30/11 (début d'une absence     | 09
--   (Manager,      | 30/11–04/12 saisie par RH, justifiee=false : la vue |
--    60000)        | compte 1 jour injustifié en novembre, 4 en décembre) |
--   MAT-AUTH-0001  | MALADIE justifiée 05–06/11 (payée, §2a) +          | §1/§2a/§2b
--   (Admin, 55000) | SANS_SOLDE validé 12–13/11 (§1, absence générée §2b) |
--
-- Résultats attendus après génération des bulletins (taux journalier =
-- salaire_base / 20 jours ouvrés ; cotisations employé 1 %, patronal 13 %) :
--
--   Matricule      | payés | sans solde | injust. | retenue_congés | éléments | brut  | cotis. | net   | patronal
--   ---------------|-------|------------|---------|----------------|----------|-------|--------|-------|----------
--   MAT-FIN-0001   |     5 |          0 |       2 |          4300.00 |    8000.00 | 46700 |   467.00 | 46233 |  6071.00
--   MAT-AUTH-0002  |     0 |          2 |       0 |          4500.00 |   −1500.00 | 39000 |   390.00 | 38610 |  5070.00
--   MAT-AUTH-0003  |     0 |          0 |       1 |          3000.00 |       0.00 | 57000 |   570.00 | 56430 |  7410.00
--   MAT-AUTH-0001  |     2 |          2 |       0 |          5500.00 |       0.00 | 49500 |   495.00 | 49005 |  6435.00
--
--   Détail : Andry 43000/20 = 2150/j × 2 injust. = 4300 ;
--   RH 45000/20 = 2250/j × 2 sans solde = 4500 ;
--   Manager 60000/20 = 3000/j × 1 injust. = 3000 ;
--   Admin 55000/20 = 2750/j × 2 sans solde = 5500.
--   Le congé payé (Andry 16–20/11) et la maladie justifiée (Admin 05–06/11)
--   prouvent l'absence de retenue sur les jours payés.
--
-- Idempotent : relançable sans doublon (gardes NOT EXISTS + nettoyage des
-- seules lignes « Test paie » en section 0). Ne touche pas aux données 09.
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 0. NETTOYAGE des lignes de ce test (relançabilité), jamais des données 09
-- ----------------------------------------------------------------------------

-- Bulletins non validés du périmètre (un bulletin validé bloque le recalcul :
-- à supprimer à la main si besoin, après contrôle).
DELETE FROM bulletin_paie
WHERE mois = DATE '2026-11-01'
  AND date_validation IS NULL
  AND id_salarie IN (
      SELECT id_salarie FROM salarie
      WHERE matricule IN ('MAT-FIN-0001', 'MAT-AUTH-0001', 'MAT-AUTH-0002', 'MAT-AUTH-0003')
  );

DELETE FROM element_paie
WHERE mois = DATE '2026-11-01' AND libelle LIKE 'Test paie%';

-- Absences de test d'abord (FK absence -> demande_conge en ON DELETE RESTRICT).
DELETE FROM absence WHERE commentaire LIKE 'Test paie%';
DELETE FROM demande_conge WHERE motif LIKE 'Test paie%';

-- ----------------------------------------------------------------------------
-- 1. DEMANDE SANS_SOLDE VALIDEE (Admin, 12–13/11) — comme un congé créé
--    via l'API puis validé manager puis RH (l'absence est générée en §2).
-- ----------------------------------------------------------------------------

INSERT INTO demande_conge (id_salarie, id_type_conge, id_statut, date_debut, date_fin,
                           demi_journee_debut, demi_journee_fin, nb_jours, motif, date_demande,
                           id_manager_valideur, date_validation_manager, commentaire_manager,
                           id_rh_valideur, date_validation_rh, commentaire_rh,
                           motif_refus, date_annulation)
SELECT s.id_salarie, tc.id_type_conge, sdc.id_statut, DATE '2026-11-12', DATE '2026-11-13',
       false, false, 2.00, 'Test paie : congé sans solde (retenue attendue)',
       TIMESTAMP '2026-11-05 10:00:00',
       man.id_salarie, TIMESTAMP '2026-11-06 09:00:00', 'OK',
       rh.id_salarie, TIMESTAMP '2026-11-06 14:00:00', NULL,
       NULL::text, NULL::timestamp
FROM salarie s
JOIN type_conge tc ON tc.code = 'SANS_SOLDE'
JOIN statut_demande_conge sdc ON sdc.code = 'VALIDEE'
LEFT JOIN salarie man ON man.matricule = 'MAT-AUTH-0003'
LEFT JOIN salarie rh ON rh.matricule = 'MAT-AUTH-0002'
WHERE s.matricule = 'MAT-AUTH-0001'
  AND NOT EXISTS (
      SELECT 1 FROM demande_conge dc
      WHERE dc.id_salarie = s.id_salarie
        AND dc.id_type_conge = tc.id_type_conge
        AND dc.date_debut = DATE '2026-11-12'
        AND dc.date_fin = DATE '2026-11-13'
  );

-- ----------------------------------------------------------------------------
-- 2. ABSENCES : maladie justifiée (payée), sans solde générée, injustifiée
-- ----------------------------------------------------------------------------

-- 2a. MALADIE JUSTIFIEE de l'Admin, 05–06/11 (saisie RH, justificatif déposé)
--     => jours_payes = 2, aucune retenue.
INSERT INTO absence (id_salarie, id_type_absence, id_demande_conge, date_debut, date_fin,
                     nb_jours, justifiee, commentaire, id_saisi_par, date_saisie)
SELECT s.id_salarie, ta.id_type_absence, NULL, DATE '2026-11-05', DATE '2026-11-06',
       2.00, true, 'Test paie : maladie justifiée (payée, sans retenue)',
       u.id_utilisateur, TIMESTAMP '2026-11-07 09:00:00'
FROM salarie s
JOIN type_absence ta ON ta.code = 'MALADIE'
JOIN utilisateur u ON u.identifiant = 'rh'
WHERE s.matricule = 'MAT-AUTH-0001'
  AND NOT EXISTS (
      SELECT 1 FROM absence a
      WHERE a.id_salarie = s.id_salarie AND a.date_debut = DATE '2026-11-05' AND a.date_fin = DATE '2026-11-06'
  );

-- 2b. Absence CONGE justifiée générée depuis la demande SANS_SOLDE validée
--     de l'Admin (12–13/11) => jours_sans_solde = 2 (vue : tc.code = 'SANS_SOLDE').
INSERT INTO absence (id_salarie, id_type_absence, id_demande_conge, date_debut, date_fin,
                     nb_jours, justifiee, commentaire, id_saisi_par, date_saisie)
SELECT s.id_salarie, ta.id_type_absence, dc.id_demande_conge, dc.date_debut, dc.date_fin,
       dc.nb_jours, true, 'Test paie : générée depuis demande sans solde validée',
       NULL, TIMESTAMP '2026-11-06 14:01:00'
FROM salarie s
JOIN type_absence ta ON ta.code = 'CONGE'
JOIN demande_conge dc ON dc.id_salarie = s.id_salarie
    AND dc.date_debut = DATE '2026-11-12' AND dc.date_fin = DATE '2026-11-13'
JOIN type_conge tc ON tc.id_type_conge = dc.id_type_conge AND tc.code = 'SANS_SOLDE'
WHERE s.matricule = 'MAT-AUTH-0001'
  AND NOT EXISTS (
      SELECT 1 FROM absence a
      WHERE a.id_salarie = s.id_salarie AND a.date_debut = DATE '2026-11-12' AND a.date_fin = DATE '2026-11-13'
  );

-- 2c. ABSENCE INJUSTIFIEE d'Andry, 26–27/11 (saisie RH)
--     => jours_injustifies = 2, retenue = 2150 × 2 = 4300.
INSERT INTO absence (id_salarie, id_type_absence, id_demande_conge, date_debut, date_fin,
                     nb_jours, justifiee, commentaire, id_saisi_par, date_saisie)
SELECT s.id_salarie, ta.id_type_absence, NULL, DATE '2026-11-26', DATE '2026-11-27',
       2.00, false, 'Test paie : absence injustifiée (retenue attendue)',
       u.id_utilisateur, TIMESTAMP '2026-11-28 09:00:00'
FROM salarie s
JOIN type_absence ta ON ta.code = 'ABSENCE_INJUSTIFIEE'
JOIN utilisateur u ON u.identifiant = 'rh'
WHERE s.matricule = 'MAT-FIN-0001'
  AND NOT EXISTS (
      SELECT 1 FROM absence a
      WHERE a.id_salarie = s.id_salarie AND a.date_debut = DATE '2026-11-26' AND a.date_fin = DATE '2026-11-27'
  );

-- ----------------------------------------------------------------------------
-- 3. (Rappel des absences 09 déjà en base, sans nouvel INSERT)
--    • MAT-FIN-0001 : ANNUEL validé 16–20/11 (CONGE, justifiée) => payés 5.
--    • MAT-AUTH-0002 : SANS_SOLDE validé 23–24/11 (CONGE, justifiée) => sans solde 2.
--    • MAT-AUTH-0003 : MALADIE 30/11–04/12 non justifiée => 1 injust. en nov.
-- ----------------------------------------------------------------------------

-- ----------------------------------------------------------------------------
-- 4. ELEMENTS VARIABLES de paie (retenues MANUELLES, distinctes de la
--    retenue congés automatique — cf. ElementPaieService, type RETTENUE
--    stocké en négatif). Mois 2026-11.
-- ----------------------------------------------------------------------------

INSERT INTO element_paie (id_salarie, mois, type_element, libelle, montant, commentaire, id_saisi_par)
SELECT s.id_salarie, DATE '2026-11-01', v.type_element, v.libelle, v.montant, v.commentaire,
       u.id_utilisateur
FROM (VALUES
    ('MAT-FIN-0001',  'PRIME',    'Test paie - prime rendement',   10000.00, 'Test paie : prime (gain)'),
    ('MAT-FIN-0001',  'RETTENUE', 'Test paie - retenue manuelle',  -2000.00, 'Test paie : retenue manuelle (distincte de la retenue conges)'),
    ('MAT-AUTH-0002', 'RETTENUE', 'Test paie - retenue manuelle',  -1500.00, 'Test paie : retenue manuelle (distincte de la retenue conges)')
) AS v(matricule, type_element, libelle, montant, commentaire)
JOIN salarie s ON s.matricule = v.matricule
JOIN utilisateur u ON u.identifiant = 'rh'
WHERE NOT EXISTS (
    SELECT 1 FROM element_paie e
    WHERE e.id_salarie = s.id_salarie AND e.mois = DATE '2026-11-01' AND e.libelle = v.libelle
);

COMMIT;

-- ============================================================================
-- 5. CONTROLES (à lancer après génération des bulletins)
-- ============================================================================

-- 5a. Variables de paie lues par BulletinService (avant génération).
SELECT matricule, id_salarie, jours_payes, jours_sans_solde, jours_injustifies
FROM v_variables_paie_conges
WHERE mois = DATE '2026-11-01'
  AND matricule IN ('MAT-FIN-0001', 'MAT-AUTH-0001', 'MAT-AUTH-0002', 'MAT-AUTH-0003')
ORDER BY matricule;
-- Attendu :
--   MAT-AUTH-0001 : payés 2, sans solde 2, injustifiés 0
--   MAT-AUTH-0002 : payés 0, sans solde 2, injustifiés 0
--   MAT-AUTH-0003 : payés 0, sans solde 0, injustifiés 1
--   MAT-FIN-0001   : payés 5, sans solde 0, injustifiés 2

-- 5b. Bulletins générés (après « Calculer » pour chacun, mois 2026-11).
SELECT s.matricule, b.salaire_base, b.jours_ouvres_mois,
       b.jours_paye, b.jours_sans_solde, b.jours_injustifies,
       b.retenue_conges, b.elements_paie, b.brut,
       b.cotisations_employe, b.net, b.cotisations_patronal,
       b.date_validation
FROM bulletin_paie b
JOIN salarie s ON s.id_salarie = b.id_salarie
WHERE b.mois = DATE '2026-11-01'
  AND s.matricule IN ('MAT-FIN-0001', 'MAT-AUTH-0001', 'MAT-AUTH-0002', 'MAT-AUTH-0003')
ORDER BY s.matricule;
-- Attendu (voir tableau en tête) :
--   MAT-AUTH-0001 : retenue 5500, brut 49500, net 49005
--   MAT-AUTH-0002 : retenue 4500, éléments −1500, brut 39000, net 38610
--   MAT-AUTH-0003 : retenue 3000, brut 57000, net 56430
--   MAT-FIN-0001   : retenue 4300, éléments +8000, brut 46700, net 46233

-- ============================================================================
-- 6. NETTOYAGE (optionnel, à décommenter pour retirer toutes les données
--    de test de ce script — ne supprime PAS les données 09).
-- ============================================================================
-- BEGIN;
-- DELETE FROM bulletin_paie
-- WHERE mois = DATE '2026-11-01'
--   AND id_salarie IN (SELECT id_salarie FROM salarie
--                      WHERE matricule IN ('MAT-FIN-0001', 'MAT-AUTH-0001',
--                                          'MAT-AUTH-0002', 'MAT-AUTH-0003'));
-- DELETE FROM element_paie WHERE libelle LIKE 'Test paie%' AND mois = DATE '2026-11-01';
-- DELETE FROM absence WHERE commentaire LIKE 'Test paie%';
-- DELETE FROM demande_conge WHERE motif LIKE 'Test paie%';
-- COMMIT;
