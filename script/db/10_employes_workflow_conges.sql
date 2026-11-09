-- ============================================================================
-- 10_employes_workflow_conges.sql — Équipe de test multi-départements
--
-- Contenu (idempotent, peut être relancé sans doublon) :
--   • 5 nouveaux salariés (matricules MAT-WF-0001..0005), couvrant RH,
--     Informatique et Finance (+ 1 commercial)
--   • Employés + comptes utilisateurs (login : 'wf.*' / 'salarie123')
--   • Contrats CDI « En cours » pour chacun (+ 1 historique CDD→CDI)
--   • Historiques : salarie_status_historique, contrat_status_historique
--   • Affectations (dont 1 transfert de service pour un salarié)
--   • Soldes ANNUEL 2026 + 1 demande de test par salarié (validation/planning)
--
-- Prérequis (dans cet ordre) : 01_migrations.sql, 02_seeds.sql, 08_migration_conges_bloc7.sql
-- (08 fournit le schéma congés ; ce script peut s'exécuter sans 09_)
-- Utilisation : psql -U postgres -d gestion_rh -f script/db/10_employes_workflow_conges.sql
--
-- Comptes de test (mot de passe : salarie123 pour tous) :
--   wf.durand     (salarié,  rôle salarie)   — Développeur Senior (Informatique)
--   wf.rakoto     (salarié,  rôle salarie)   — Comptable Senior (Finance)
--   wf.randria    (salarié,  rôle salarie)   — Assistant RH (RH)
--   wf.rakotoarisoa (manager, rôle manager)  — Responsable Infrastructure (Informatique)
--   wf.jr         (rh,       rôle rh)        — Responsable Admin du Personnel (RH)
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. SALARIÉS
-- ----------------------------------------------------------------------------

INSERT INTO salarie (matricule, nom, prenom, email, telephone, date_embauche, statut)
SELECT v.matricule, v.nom, v.prenom, v.email, v.telephone, v.date_embauche, ss.id
FROM (VALUES
    ('MAT-WF-0001', 'Durand',            'Thomas',  'wf.durand@example.test',            NULL,        DATE '2024-09-01'),
    ('MAT-WF-0003', 'Rakoto',            'Soa',     'wf.rakoto@example.test',            '0320000020', DATE '2022-03-01'),
    ('MAT-WF-0004', 'Randria',           'Lala',    'wf.randria@example.test',           '0320000021', DATE '2023-06-01'),
    ('MAT-WF-0005', 'Rakotoarisoa',      'Hasina',  'wf.rakotoarisoa@example.test',      '0320000022', DATE '2020-08-10'),
    ('MAT-WF-0006', 'Ranaivo',           'Jean',    'wf.jr@example.test',                NULL,        DATE '2021-11-05')
) AS v(matricule, nom, prenom, email, telephone, date_embauche)
JOIN status_salarie ss ON ss.designation = 'Actif'
WHERE NOT EXISTS (
    SELECT 1 FROM salarie s WHERE s.matricule = v.matricule
);

-- ----------------------------------------------------------------------------
-- 2. EMPLOYÉS + COMPTES
-- ----------------------------------------------------------------------------

INSERT INTO employe (nom, date_de_naissance, poste_id, id_salarie)
SELECT v.nom, v.date_de_naissance, p.id_poste, s.id_salarie
FROM (VALUES
    ('Développeur - Thomas Durand',          DATE '1995-04-12', 'Développeur Senior',              'MAT-WF-0001'),
    ('Comptable - Soa Rakoto',               DATE '1998-11-30', 'Comptable Senior',                'MAT-WF-0003'),
    ('Assistant RH - Lala Randria',          DATE '2000-02-08', 'Assistant RH',                    'MAT-WF-0004'),
    ('Resp. Infrastructure - Hasina',        DATE '1991-07-25', 'Administrateur Systèmes et Réseaux', 'MAT-WF-0005'),
    ('Resp. Admin RH - Jean Ranaivo',        DATE '1988-12-15', 'Responsable Administration du Personnel', 'MAT-WF-0006')
) AS v(nom, date_de_naissance, poste, matricule)
JOIN poste p ON p.intitule = v.poste
JOIN salarie s ON s.matricule = v.matricule
WHERE NOT EXISTS (
    SELECT 1 FROM employe e WHERE e.id_salarie = s.id_salarie
);

INSERT INTO utilisateur (id_employe, identifiant, mot_de_passe_hash, role, actif)
SELECT e.id, v.identifiant, '$2y$10$ObzZ9nBNCg.ZU.3Rag7sh.P5hPrFl7VSRWPp7hy72kOSynTes0A3u', v.role, true
FROM (VALUES
    ('wf.durand',          'salarie',   'Développeur - Thomas Durand'),
    ('wf.rakoto',          'salarie',   'Comptable - Soa Rakoto'),
    ('wf.randria',         'salarie',   'Assistant RH - Lala Randria'),
    ('wf.rakotoarisoa',    'manager',   'Resp. Infrastructure - Hasina'),
    ('wf.jr',              'rh',        'Resp. Admin RH - Jean Ranaivo')
) AS v(identifiant, role, nom_employe)
JOIN employe e ON e.nom = v.nom_employe
WHERE NOT EXISTS (
    SELECT 1 FROM utilisateur u WHERE u.identifiant = v.identifiant
);

UPDATE utilisateur
SET doit_changer_mot_de_passe = false
WHERE identifiant LIKE 'wf.%';

-- ----------------------------------------------------------------------------
-- 3. CONTRATS (actuel En cours + examen pour un salarié : CDD terminé puis CDI)
-- ----------------------------------------------------------------------------

INSERT INTO contrat (id_salarie, id_poste, type_contrat_id, date_debut, date_fin,
                     salaire_base, date_signature, statut)
SELECT s.id_salarie, p.id_poste, tc.id, v.date_debut, NULL,
       v.salaire_base, v.date_debut, sc.id
FROM (VALUES
    ('MAT-WF-0001', 'Développeur Senior',                DATE '2024-09-01', 40000.00),
    ('MAT-WF-0003', 'Comptable Senior',                  DATE '2022-03-01', 37000.00),
    ('MAT-WF-0004', 'Assistant RH',                      DATE '2023-06-01', 30000.00),
    ('MAT-WF-0005', 'Administrateur Systèmes et Réseaux', DATE '2020-08-10', 52000.00),
    ('MAT-WF-0006', 'Responsable Administration du Personnel', DATE '2021-11-05', 55000.00)
) AS v(matricule, intitule_poste, date_debut, salaire_base)
JOIN salarie s ON s.matricule = v.matricule
JOIN poste p ON p.intitule = v.intitule_poste
JOIN type_contrat tc ON tc.designation = 'CDI'
JOIN status_contrat sc ON sc.designation = 'En cours'
WHERE NOT EXISTS (
    SELECT 1 FROM contrat c
    WHERE c.id_salarie = s.id_salarie AND c.date_fin IS NULL
);

-- Historique : CDD terminé de Soa (2022-03 → 2022-12), puis CDI
INSERT INTO contrat (id_salarie, id_poste, type_contrat_id, date_debut, date_fin,
                     salaire_base, date_signature, statut)
SELECT s.id_salarie, p.id_poste, tc.id, DATE '2022-03-01', DATE '2022-12-31',
       32000.00, DATE '2022-03-01', sc.id
FROM salarie s
JOIN poste p ON p.intitule = 'Comptable Junior'
JOIN type_contrat tc ON tc.designation = 'CDD'
JOIN status_contrat sc ON sc.designation = 'Terminé'
WHERE s.matricule = 'MAT-WF-0003'
  AND NOT EXISTS (
      SELECT 1 FROM contrat c
      WHERE c.id_salarie = s.id_salarie AND c.date_fin = DATE '2022-12-31'
  );

-- ----------------------------------------------------------------------------
-- 4. HISTORIQUES DE STATUTS (salarié : En attente -> Actif ; contrat : Signé -> En cours)
-- ----------------------------------------------------------------------------

INSERT INTO salarie_status_historique (id_salarie, statut_ancien, statut_nouveau, date_changement)
SELECT s.id_salarie, anci.id, nouv.id, v.date_changement
FROM (VALUES
    ('MAT-WF-0001', DATE '2024-09-01'),
    ('MAT-WF-0003', DATE '2022-03-01'),
    ('MAT-WF-0004', DATE '2023-06-01'),
    ('MAT-WF-0005', DATE '2020-08-10'),
    ('MAT-WF-0006', DATE '2021-11-05')
) AS v(matricule, date_changement)
JOIN salarie s ON s.matricule = v.matricule
JOIN status_salarie anci ON anci.designation = 'En attente d''embauche'
JOIN status_salarie nouv ON nouv.designation = 'Actif'
WHERE NOT EXISTS (
    SELECT 1 FROM salarie_status_historique h
    WHERE h.id_salarie = s.id_salarie AND h.statut_nouveau = nouv.id
      AND h.date_changement = v.date_changement
);

INSERT INTO contrat_status_historique (id_contrat, statut_ancien, statut_nouveau, date_changement)
SELECT c.id_contrat, sig.id, cours.id, c.date_debut
FROM contrat c
JOIN salarie s ON s.id_salarie = c.id_salarie AND s.matricule LIKE 'MAT-WF-%' AND c.date_fin IS NULL
JOIN status_contrat sig ON sig.designation = 'Signé'
JOIN status_contrat cours ON cours.designation = 'En cours'
WHERE NOT EXISTS (
    SELECT 1 FROM contrat_status_historique h
    WHERE h.id_contrat = c.id_contrat AND h.statut_nouveau = cours.id
);

-- ----------------------------------------------------------------------------
-- 5. AFFECTATIONS (actuelle par salarié + une précédente pour Soa : transfert Comptable Junior -> Senior)
-- ----------------------------------------------------------------------------

INSERT INTO affectation (id_salarie, id_poste, date_debut, date_fin, motif)
SELECT s.id_salarie, p.id_poste, v.date_debut, NULL, v.motif
FROM (VALUES
    ('MAT-WF-0001', 'Développeur Senior',                DATE '2024-09-01', 'Affectation initiale'),
    ('MAT-WF-0003', 'Comptable Senior',                  DATE '2023-01-01', 'Promotion Comptable Junior -> Senior'),
    ('MAT-WF-0004', 'Assistant RH',                      DATE '2023-06-01', 'Affectation initiale'),
    ('MAT-WF-0005', 'Administrateur Systèmes et Réseaux', DATE '2020-08-10', 'Affectation initiale'),
    ('MAT-WF-0006', 'Responsable Administration du Personnel', DATE '2021-11-05', 'Affectation initiale')
) AS v(matricule, intitule_poste, date_debut, motif)
JOIN salarie s ON s.matricule = v.matricule
JOIN poste p ON p.intitule = v.intitule_poste
WHERE NOT EXISTS (
    SELECT 1 FROM affectation a
    WHERE a.id_salarie = s.id_salarie AND a.id_poste = p.id_poste AND a.date_fin IS NULL
);

INSERT INTO affectation (id_salarie, id_poste, date_debut, date_fin, motif)
SELECT s.id_salarie, p.id_poste, DATE '2022-03-01', DATE '2022-12-31', 'Première affectation (Comptable Junior)'
FROM salarie s
JOIN poste p ON p.intitule = 'Comptable Junior'
WHERE s.matricule = 'MAT-WF-0003'
  AND NOT EXISTS (
      SELECT 1 FROM affectation a
      WHERE a.id_salarie = s.id_salarie AND a.date_fin = DATE '2022-12-31'
  );

-- ----------------------------------------------------------------------------
-- 6. CONGÉS : soldes ANNUEL 2026 + 1 demande de test par salarié
-- ----------------------------------------------------------------------------

INSERT INTO solde_conge (id_salarie, id_type_conge, annee, droit_acquis, report, pris, en_attente)
SELECT s.id_salarie, tc.id_type_conge, 2026, 25.00, 0, 0, 0
FROM salarie s
JOIN type_conge tc ON tc.code = 'ANNUEL'
WHERE s.matricule LIKE 'MAT-WF-%'
ON CONFLICT (id_salarie, id_type_conge, annee) DO NOTHING;

INSERT INTO demande_conge (id_salarie, id_type_conge, id_statut, date_debut, date_fin,
                           demi_journee_debut, demi_journee_fin, nb_jours, motif, date_demande)
SELECT s.id_salarie, tc.id_type_conge, sdc.id_statut, v.date_debut, v.date_fin,
       false, false, v.nb_jours, 'Fixture workflow (10)', TIMESTAMP '2026-11-20 10:00:00'
FROM (VALUES
    ('MAT-WF-0001', DATE '2026-12-14', DATE '2026-12-18', 5.00, 'EN_ATTENTE_MANAGER'),
    ('MAT-WF-0003', DATE '2026-11-09', DATE '2026-11-13', 5.00, 'VALIDEE'),
    ('MAT-WF-0004', DATE '2026-11-25', DATE '2026-11-26', 2.00, 'EN_ATTENTE_RH'),
    ('MAT-WF-0005', DATE '2026-12-28', DATE '2026-12-31', 4.00, 'EN_ATTENTE_MANAGER'),
    ('MAT-WF-0006', DATE '2026-11-19', DATE '2026-11-20', 2.00, 'VALIDEE')
) AS v(matricule, date_debut, date_fin, nb_jours, statut_code)
JOIN salarie s ON s.matricule = v.matricule
JOIN type_conge tc ON tc.code = 'ANNUEL'
JOIN statut_demande_conge sdc ON sdc.code = v.statut_code
WHERE NOT EXISTS (
    SELECT 1 FROM demande_conge dc
    WHERE dc.id_salarie = s.id_salarie AND dc.date_debut = v.date_debut AND dc.date_fin = v.date_fin
);

-- Absences générées pour les validées
INSERT INTO absence (id_salarie, id_type_absence, id_demande_conge, date_debut, date_fin,
                     nb_jours, justifiee, commentaire, id_saisi_par, date_saisie)
SELECT s.id_salarie, ta.id_type_absence, dc.id_demande_conge, dc.date_debut, dc.date_fin,
       dc.nb_jours, true, 'Fixture (10) : générée depuis demande validée', NULL, TIMESTAMP '2026-11-20 12:00:00'
FROM demande_conge dc
JOIN salarie s ON s.id_salarie = dc.id_salarie AND s.matricule LIKE 'MAT-WF-%'
JOIN type_absence ta ON ta.code = 'CONGE'
JOIN statut_demande_conge sdc ON sdc.id_statut = dc.id_statut AND sdc.code = 'VALIDEE'
WHERE NOT EXISTS (
    SELECT 1 FROM absence a
    WHERE a.id_salarie = s.id_salarie
      AND a.date_debut = dc.date_debut AND a.date_fin = dc.date_fin
);

-- Correctif de données (bases où l'accent a été inséré) : rôle = 'salarie',
-- sans accent, pour correspondre à Roles::SALARIE côté PHP (comparaison stricte).
UPDATE utilisateur SET role = 'salarie' WHERE role = 'salarié';

COMMIT;
