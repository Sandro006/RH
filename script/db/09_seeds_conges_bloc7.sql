-- ============================================================================
-- 09_seeds_conges_bloc7.sql — Jeux de données Bloc 7 (fixtures de test)
--
-- Contenu (idempotent, peut être relancé sans doublon) :
--   • Contrats CDI « En cours » pour les 5 salariés de démo (02_seeds)
--   • Soldes ANNUEL 2026 pour ces salariés
--   • Demandes dans chaque statut : EN_ATTENTE_MANAGER / _RH / VALIDEE /
--     REFUSEE / ANNULEE (+ demi-journée, sans solde, maladie)
--   • Absences générées (validées) et saisies manuellement (MALADIE)
--   • Mouvements RESERVATION / DEBIT / RECREDIT réels (cohérents soldes)
--   • Justificatifs (via demande et via absence), délégation d’un manager
--
-- Prérequis (dans cet ordre) : 01_migrations.sql, 02_seeds.sql, 08_migration_conges_bloc7.sql
-- Utilisation : psql -U postgres -d gestion_rh -f script/db/09_seeds_conges_bloc7.sql
--
-- Comptes utilisables pour tester les écrans (voir 02_seeds) :
--   manager / manager123 (rôle manager) | rh / rh123 (rôle rh)
--   salarie / salarie123 (rôle salarie, sans employé : les écrans « mes… » en ont besoin)
--
-- Cas du §6 couverts par ces données : solde suffisant, type sans déduction,
-- demi-journée, type sans solde (retenue paie), malade + justificatif, refus
-- avec motif, annulation avec recrédit, délégation de validation, etc.
-- Le solde insuffisant / le chevauchement / la rupture de délai se testent
-- en créant une demande via l’API (POST /api/conges/demandes).
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. CONTRATS ACTIFS (CDI, En cours) pour les salariés de démo
-- ----------------------------------------------------------------------------

INSERT INTO contrat (id_salarie, id_poste, type_contrat_id, date_debut, date_fin,
                     salaire_base, periode_essai_debut, periode_essai_fin, date_signature, statut)
SELECT s.id_salarie, p.id_poste, tc.id, v.date_debut, NULL,
       v.salaire_base, NULL, NULL, v.date_debut, sc.id
FROM (VALUES
    ('MAT-AUTH-0001', DATE '1980-01-01', 55000.00),
    ('MAT-AUTH-0002', DATE '1985-01-01', 45000.00),
    ('MAT-AUTH-0003', DATE '1986-01-01', 60000.00),
    ('MAT-FIN-0001',  DATE '2021-03-01', 43000.00),
    ('MAT-DIR-0001',  DATE '2018-01-15', 90000.00)
) AS v(matricule, date_debut, salaire_base)
JOIN salarie s ON s.matricule = v.matricule
JOIN employe e ON e.id_salarie = s.id_salarie
JOIN poste p ON p.id_poste = e.poste_id
JOIN type_contrat tc ON tc.designation = 'CDI'
JOIN status_contrat sc ON sc.designation = 'En cours'
WHERE NOT EXISTS (
    SELECT 1 FROM contrat c
    WHERE c.id_salarie = s.id_salarie AND c.date_fin IS NULL
);

-- ----------------------------------------------------------------------------
-- 2. SOLDES ANNUEL 2026 (cohérents avec les demandes ci-dessous)
-- ----------------------------------------------------------------------------

INSERT INTO solde_conge (id_salarie, id_type_conge, annee, droit_acquis, report, pris, en_attente)
SELECT s.id_salarie, tc.id_type_conge, 2026, v.droit_acquis, v.report, v.pris, v.en_attente
FROM (VALUES
    ('MAT-AUTH-0001', 25.00,  0.00,  0.00, 0.00),   -- Admin : dispo 25
    ('MAT-AUTH-0002', 25.00,  2.00,  0.00, 3.00),   -- RH : réservé 3 (sa demande EN_ATTENTE_RH)
    ('MAT-AUTH-0003', 25.00,  0.00,  0.00, 0.00),   -- Manager : demande refusée
    ('MAT-FIN-0001',  25.00,  5.00,  5.00, 4.50),   -- Andry : pris 5 (validée) + réservé 4,5 (en attente)
    ('MAT-DIR-0001',  25.00,  0.00,  0.00, 0.00)    -- Direction : dispo 25
) AS v(matricule, droit_acquis, report, pris, en_attente)
JOIN salarie s ON s.matricule = v.matricule
JOIN type_conge tc ON tc.code = 'ANNUEL'
ON CONFLICT (id_salarie, id_type_conge, annee) DO NOTHING;

-- ----------------------------------------------------------------------------
-- 3. DEMANDES (cycle de vie complet : en attente, validée, refusée, annulée)
-- ----------------------------------------------------------------------------

INSERT INTO demande_conge (id_salarie, id_type_conge, id_statut, date_debut, date_fin,
                           demi_journee_debut, demi_journee_fin, nb_jours, motif, date_demande,
                           id_manager_valideur, date_validation_manager, commentaire_manager,
                           id_rh_valideur, date_validation_rh, commentaire_rh,
                           motif_refus, date_annulation)
SELECT s.id_salarie, tc.id_type_conge, sdc.id_statut, v.date_debut, v.date_fin,
       v.demi_debut, v.demi_fin, v.nb_jours, v.motif, v.date_demande,
       man.id_salarie, v.date_validation_manager, v.commentaire_manager,
       rh.id_salarie, v.date_validation_rh, v.commentaire_rh,
       v.motif_refus, v.date_annulation
FROM (VALUES
    -- Andry : ANNUEL validée
    ('MAT-FIN-0001', 'ANNUEL', 'VALIDEE',
     DATE '2026-11-16', DATE '2026-11-20', false, false, 5.00,
     'Congé annuel payé (fixture)', TIMESTAMP '2026-11-10 10:00:00',
     'MAT-AUTH-0003', TIMESTAMP '2026-11-11 09:00:00', 'OK, période calme',
     'MAT-AUTH-0002', TIMESTAMP '2026-11-12 14:30:00', NULL,
     NULL::text, NULL::timestamp),
    -- Andry : ANNUEL en attente manager (demi-journée sur le début)
    ('MAT-FIN-0001', 'ANNUEL', 'EN_ATTENTE_MANAGER',
     DATE '2026-12-07', DATE '2026-12-11', true, false, 4.50,
     'Congé annuel (fixture)', TIMESTAMP '2026-11-25 08:15:00',
     NULL::text, NULL::timestamp, NULL::text,
     NULL::text, NULL::timestamp, NULL::text,
     NULL::text, NULL::timestamp),
    -- Andry : ANNUEL annulée
    ('MAT-FIN-0001', 'ANNUEL', 'ANNULEE',
     DATE '2026-10-19', DATE '2026-10-21', false, false, 3.00,
     'Annulé par le salarié (fixture)', TIMESTAMP '2026-10-10 11:20:00',
     NULL::text, NULL::timestamp, NULL::text,
     NULL::text, NULL::timestamp, NULL::text,
     NULL::text, TIMESTAMP '2026-10-13 16:40:00'),
    -- Manager : MALADIE en attente manager
    ('MAT-AUTH-0003', 'MALADIE', 'EN_ATTENTE_MANAGER',
     DATE '2026-11-30', DATE '2026-12-04', false, false, 5.00,
     'Arrêt maladie (fixture)', TIMESTAMP '2026-11-29 07:45:00',
     NULL::text, NULL::timestamp, NULL::text,
     NULL::text, NULL::timestamp, NULL::text,
     NULL::text, NULL::timestamp),
    -- Manager : ANNUEL refusée
    ('MAT-AUTH-0003', 'ANNUEL', 'REFUSEE',
     DATE '2026-10-26', DATE '2026-10-30', false, false, 5.00,
     'Congé annuel (fixture)', TIMESTAMP '2026-10-20 09:30:00',
     NULL::text, NULL::timestamp, NULL::text,
     NULL::text, NULL::timestamp, NULL::text,
     'Période de clôture mensuelle', NULL::timestamp),
    -- RH : ANNUEL en attente RH (manager a déjà validé)
    ('MAT-AUTH-0002', 'ANNUEL', 'EN_ATTENTE_RH',
     DATE '2026-12-21', DATE '2026-12-23', false, false, 3.00,
     'Congé annuel (fixture)', TIMESTAMP '2026-11-26 15:10:00',
     'MAT-AUTH-0003', TIMESTAMP '2026-11-27 10:00:00', 'OK',
     NULL::text, NULL::timestamp, NULL::text,
     NULL::text, NULL::timestamp),
    -- RH : SANS_SOLDE validée (pas de débit, retenue paie)
    ('MAT-AUTH-0002', 'SANS_SOLDE', 'VALIDEE',
     DATE '2026-11-23', DATE '2026-11-24', false, false, 2.00,
     'Congé sans solde (fixture)', TIMESTAMP '2026-11-18 12:00:00',
     'MAT-AUTH-0003', TIMESTAMP '2026-11-19 08:00:00', 'OK',
     'MAT-AUTH-0002', TIMESTAMP '2026-11-19 15:00:00', NULL,
     NULL::text, NULL::timestamp)
) AS v(matricule, type_code, statut_code, date_debut, date_fin, demi_debut, demi_fin, nb_jours,
       motif, date_demande, matricule_manager, date_validation_manager, commentaire_manager,
       matricule_rh, date_validation_rh, commentaire_rh, motif_refus, date_annulation)
JOIN salarie s ON s.matricule = v.matricule
JOIN type_conge tc ON tc.code = v.type_code
JOIN statut_demande_conge sdc ON sdc.code = v.statut_code
LEFT JOIN salarie man ON man.matricule = v.matricule_manager
LEFT JOIN salarie rh ON rh.matricule = v.matricule_rh
WHERE NOT EXISTS (
    SELECT 1 FROM demande_conge dc
    WHERE dc.id_salarie = s.id_salarie
      AND dc.id_type_conge = tc.id_type_conge
      AND dc.date_debut = v.date_debut
      AND dc.date_fin = v.date_fin
);

-- ----------------------------------------------------------------------------
-- 4. ABSENCES (générées pour les validées, saisies pour la maladie)
-- ----------------------------------------------------------------------------

INSERT INTO absence (id_salarie, id_type_absence, id_demande_conge, date_debut, date_fin,
                     nb_jours, justifiee, commentaire, id_saisi_par, date_saisie)
SELECT s.id_salarie, ta.id_type_absence, dc.id_demande_conge, v.date_debut, v.date_fin,
       v.nb_jours, true, 'Fixture : générée depuis demande validée', NULL, TIMESTAMP '2026-11-12 14:31:00'
FROM (VALUES
    ('MAT-FIN-0001', 'CONGE', DATE '2026-11-16', DATE '2026-11-20', 5.00),
    ('MAT-AUTH-0002', 'CONGE', DATE '2026-11-23', DATE '2026-11-24', 2.00)
) AS v(matricule, type_absence_code, date_debut, date_fin, nb_jours)
JOIN salarie s ON s.matricule = v.matricule
JOIN type_absence ta ON ta.code = v.type_absence_code
JOIN demande_conge dc ON dc.id_salarie = s.id_salarie
    AND dc.date_debut = v.date_debut AND dc.date_fin = v.date_fin
WHERE NOT EXISTS (
    SELECT 1 FROM absence a
    WHERE a.id_salarie = s.id_salarie AND a.date_debut = v.date_debut AND a.date_fin = v.date_fin
);

-- Saisie manuelle RH : MALADIE du manager (non justifiée — justificatif en attente)
INSERT INTO absence (id_salarie, id_type_absence, id_demande_conge, date_debut, date_fin,
                     nb_jours, justifiee, commentaire, id_saisi_par, date_saisie)
SELECT s.id_salarie, ta.id_type_absence, NULL, DATE '2026-11-30', DATE '2026-12-04',
       5.00, false, 'Fixture : saisie RH, justificatif en attente',
       u.id_utilisateur, TIMESTAMP '2026-11-29 18:00:00'
FROM salarie s
JOIN type_absence ta ON ta.code = 'MALADIE'
JOIN utilisateur u ON u.identifiant = 'rh'
WHERE s.matricule = 'MAT-AUTH-0003'
  AND NOT EXISTS (
      SELECT 1 FROM absence a
      WHERE a.id_salarie = s.id_salarie AND a.date_debut = DATE '2026-11-30' AND a.date_fin = DATE '2026-12-04'
  );

-- ----------------------------------------------------------------------------
-- 5. JUSTIFICATIFS (un via demande, un via absence manuelle)
-- ----------------------------------------------------------------------------

-- Demandede EN_ATTENTE_RH du RH (justificatif validé)
INSERT INTO justificatif_absence (id_absence, id_demande_conge, nom_fichier, chemin, date_depot, valide, id_validateur)
SELECT NULL, dc.id_demande_conge, 'avis_rh.pdf', '/documents/conges/fixture/avis_rh.pdf',
       TIMESTAMP '2026-11-27 16:00:00', true, NULL
FROM demande_conge dc
JOIN salarie s ON s.id_salarie = dc.id_salarie AND s.matricule = 'MAT-AUTH-0002'
JOIN type_conge tc ON tc.id_type_conge = dc.id_type_conge AND tc.code = 'ANNUEL'
JOIN statut_demande_conge sdc ON sdc.id_statut = dc.id_statut AND sdc.code = 'EN_ATTENTE_RH'
WHERE dc.date_debut = DATE '2026-12-21'
  AND NOT EXISTS (
      SELECT 1 FROM justificatif_absence j WHERE j.id_demande_conge = dc.id_demande_conge
  );

-- Absence MALADIE saisie manuellement (justificatif en attente de validation)
INSERT INTO justificatif_absence (id_absence, id_demande_conge, nom_fichier, chemin, date_depot, valide, id_validateur)
SELECT a.id_absence, NULL, 'arret_maladie.pdf', '/documents/conges/fixture/arret_maladie.pdf',
       TIMESTAMP '2026-12-01 09:00:00', NULL, NULL
FROM absence a
JOIN salarie s ON s.id_salarie = a.id_salarie AND s.matricule = 'MAT-AUTH-0003'
JOIN type_absence ta ON ta.id_type_absence = a.id_type_absence AND ta.code = 'MALADIE'
WHERE a.date_debut = DATE '2026-11-30'
  AND NOT EXISTS (
      SELECT 1 FROM justificatif_absence j WHERE j.id_absence = a.id_absence
  );

-- ----------------------------------------------------------------------------
-- 6. MOUVEMENTS DE SOLDE (journal cohérent avec les soldes ci-dessus)
-- ----------------------------------------------------------------------------

WITH demande AS (
    SELECT s.matricule, tc.code AS type_code,
           dc.id_demande_conge, dc.date_debut, dc.date_fin
    FROM demande_conge dc
    JOIN salarie s ON s.id_salarie = dc.id_salarie
    JOIN type_conge tc ON tc.id_type_conge = dc.id_type_conge
),
mouvement AS (
    SELECT v.matricule, v.type_code, v.date_debut, v.type_mouvement,
           v.nb_jours, v.commentaire
    FROM (VALUES
        ('MAT-FIN-0001', 'ANNUEL', DATE '2026-11-16', 'RESERVATION', -5.00, 'Fixture RES Andry 16/11'),
        ('MAT-FIN-0001', 'ANNUEL', DATE '2026-11-16', 'DEBIT',       -5.00, 'Fixture DEBIT Andry 16/11'),
        ('MAT-FIN-0001', 'ANNUEL', DATE '2026-12-07', 'RESERVATION', -4.50, 'Fixture RES Andry 07/12'),
        ('MAT-FIN-0001', 'ANNUEL', DATE '2026-10-19', 'RESERVATION', -3.00, 'Fixture RES Andry 19/10'),
        ('MAT-FIN-0001', 'ANNUEL', DATE '2026-10-19', 'RECREDIT',     3.00, 'Fixture REC Andry 19/10'),
        ('MAT-AUTH-0002', 'ANNUEL', DATE '2026-12-21', 'RESERVATION',  -3.00, 'Fixture RES RH 21/12')
    ) AS v(matricule, type_code, date_debut, type_mouvement, nb_jours, commentaire)
)
INSERT INTO mouvement_solde (id_solde_conge, id_demande_conge, type_mouvement, nb_jours, date_mouvement, commentaire)
SELECT sc.id_solde_conge, d.id_demande_conge, m.type_mouvement,
       m.nb_jours, CURRENT_TIMESTAMP, m.commentaire
FROM mouvement m
JOIN salarie s ON s.matricule = m.matricule
JOIN type_conge tc ON tc.code = m.type_code
JOIN solde_conge sc ON sc.id_salarie = s.id_salarie AND sc.id_type_conge = tc.id_type_conge AND sc.annee = 2026
JOIN demande d ON d.matricule = m.matricule AND d.type_code = m.type_code AND d.date_debut = m.date_debut
WHERE NOT EXISTS (
    SELECT 1 FROM mouvement_solde ms
    WHERE ms.id_solde_conge = sc.id_solde_conge
      AND ms.id_demande_conge = d.id_demande_conge
      AND ms.type_mouvement = m.type_mouvement
      AND ms.commentaire = m.commentaire
);

-- ----------------------------------------------------------------------------
-- 7. DÉLÉGATION (le manager: MAT-AUTH-0003 délègue à la RH du 2026-11-09 au 2026-12-31)
-- ----------------------------------------------------------------------------

INSERT INTO delegation_validation (id_manager, id_delegue, date_debut, date_fin, motif)
SELECT man.id_salarie, rh.id_salarie, DATE '2026-11-09', DATE '2026-12-31',
       'Fixture : manager en déplacement'
FROM salarie man
JOIN salarie rh ON rh.matricule = 'MAT-AUTH-0002' AND man.matricule = 'MAT-AUTH-0003'
WHERE NOT EXISTS (
    SELECT 1 FROM delegation_validation dv
    WHERE dv.id_manager = man.id_salarie AND dv.id_delegue = rh.id_salarie
      AND dv.date_debut = DATE '2026-11-09'
);

COMMIT;
