-- ============================================================================
-- 13_responsables_services_conges.sql — Responsables par service (Bloc 7)
--
-- Pourquoi : trouveurValideur() déduit le manager direct comme « même service
-- + rang supérieur ». Sans responsables dans Développement/Comptabilité/Ventes,
-- les demandes EN_ATTENTE_MANAGER des salariés de ces services escaladaient
-- toutes vers le RH. Ce script ajoute un poste de responsable par service.
--
-- Contenu (idempotent) :
--   • 3 postes de responsable (Développement, Comptabilité, Ventes)
--   • 3 nouveaux salariés managers (MAT-WF-0010/0011/0012) + employés +
--     comptes (wf.randonarivo, wf.fenona, wf.mamy / salarie123 ou manager123)
--   • Contrats CDI « En cours », affectations, soldes ANNUEL 2026, demandes
--   • 1 delegation (Responsable Développement -> wf.rakotoarisoa)
--
-- Prérequis (après 10_employes_workflow_conges.sql) :
--   01 → 02 → 08 → [09] → 10 → 13
-- Utilisation : psql -U postgres -d gestion_rh -f script/db/13_responsables_services_conges.sql
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. POSTES DE RESPONSABLE (un par service, rang 7 = Responsable)
-- ----------------------------------------------------------------------------

INSERT INTO poste (id_poste, id_service, intitule, description, rang_id)
SELECT 100, 3, 'Responsable Développement', 'Supervise l''équipe Développement (fixture Bloc 7)', 7
WHERE NOT EXISTS (SELECT 1 FROM poste p WHERE p.intitule = 'Responsable Développement');

INSERT INTO poste (id_poste, id_service, intitule, description, rang_id)
SELECT 101, 5, 'Responsable Comptabilité', 'Supervise la Comptabilité (fixture Bloc 7)', 7
WHERE NOT EXISTS (SELECT 1 FROM poste p WHERE p.intitule = 'Responsable Comptabilité');

INSERT INTO poste (id_poste, id_service, intitule, description, rang_id)
SELECT 102, 6, 'Responsable Commercial', 'Supervise les Ventes (fixture Bloc 7)', 7
WHERE NOT EXISTS (SELECT 1 FROM poste p WHERE p.intitule = 'Responsable Commercial');

-- ----------------------------------------------------------------------------
-- 2. SALARIÉS MANAGERS
-- ----------------------------------------------------------------------------

INSERT INTO salarie (matricule, nom, prenom, email, telephone, date_embauche, statut)
SELECT v.matricule, v.nom, v.prenom, v.email, v.telephone, v.date_embauche, ss.id
FROM (VALUES
    ('MAT-WF-0010', 'Randonarivo', 'Harena', 'wf.randonarivo@example.test', '0320000030', DATE '2019-05-06'),
    ('MAT-WF-0011', 'Ratsimbazafy', 'Fenona', 'wf.fenona@example.test', '0320000031', DATE '2017-09-18'),
    ('MAT-WF-0012', 'Rasolonirina', 'Mamy', 'wf.mamy@example.test', '0320000032', DATE '2020-02-03')
) AS v(matricule, nom, prenom, email, telephone, date_embauche)
JOIN status_salarie ss ON ss.designation = 'Actif'
WHERE NOT EXISTS (
    SELECT 1 FROM salarie s WHERE s.matricule = v.matricule
);

-- ----------------------------------------------------------------------------
-- 3. EMPLOYÉS + COMPTES
-- ----------------------------------------------------------------------------

INSERT INTO employe (nom, date_de_naissance, poste_id, id_salarie)
SELECT v.nom, v.date_de_naissance, p.id_poste, s.id_salarie
FROM (VALUES
    ('Resp. Développement — Harena Randonarivo', DATE '1987-06-22', 'Responsable Développement', 'MAT-WF-0010'),
    ('Resp. Comptabilité — Fenona Ratsimbazafy', DATE '1984-03-14', 'Responsable Comptabilité', 'MAT-WF-0011'),
    ('Resp. Commercial — Mamy Rasolonirina', DATE '1990-09-07', 'Responsable Commercial', 'MAT-WF-0012')
) AS v(nom, date_de_naissance, poste, matricule)
JOIN poste p ON p.intitule = v.poste
JOIN salarie s ON s.matricule = v.matricule
WHERE NOT EXISTS (
    SELECT 1 FROM employe e WHERE e.id_salarie = s.id_salarie
);

INSERT INTO utilisateur (id_employe, identifiant, mot_de_passe_hash, role, actif)
SELECT e.id, v.identifiant, '$2y$10$VZG2/Tz.Dw/oqqfYO9yI.u29wx7ziSRpcP8zgG6JOh9TW/o76HswW', 'manager', true
FROM (VALUES
    ('wf.randonarivo', 'Resp. Développement — Harena Randonarivo'),
    ('wf.fenona', 'Resp. Comptabilité — Fenona Ratsimbazafy'),
    ('wf.mamy', 'Resp. Commercial — Mamy Rasolonirina')
) AS v(identifiant, nom_employe)
JOIN employe e ON e.nom = v.nom_employe
WHERE NOT EXISTS (
    SELECT 1 FROM utilisateur u WHERE u.identifiant = v.identifiant
);

UPDATE utilisateur
SET doit_changer_mot_de_passe = false
WHERE identifiant IN ('wf.randonarivo', 'wf.fenona', 'wf.mamy');

-- ----------------------------------------------------------------------------
-- 4. CONTRATS CDI « En cours » + AFFECTATIONS
-- ----------------------------------------------------------------------------

INSERT INTO contrat (id_salarie, id_poste, type_contrat_id, date_debut, date_fin,
                     salaire_base, date_signature, statut)
SELECT s.id_salarie, p.id_poste, tc.id, v.date_debut, NULL,
       v.salaire_base, v.date_debut, sc.id
FROM (VALUES
    ('MAT-WF-0010', 'Responsable Développement', DATE '2019-05-06', 62000.00),
    ('MAT-WF-0011', 'Responsable Comptabilité', DATE '2017-09-18', 55000.00),
    ('MAT-WF-0012', 'Responsable Commercial', DATE '2020-02-03', 58000.00)
) AS v(matricule, intitule_poste, date_debut, salaire_base)
JOIN salarie s ON s.matricule = v.matricule
JOIN poste p ON p.intitule = v.intitule_poste
JOIN type_contrat tc ON tc.designation = 'CDI'
JOIN status_contrat sc ON sc.designation = 'En cours'
WHERE NOT EXISTS (
    SELECT 1 FROM contrat c
    WHERE c.id_salarie = s.id_salarie AND c.date_fin IS NULL
);

INSERT INTO affectation (id_salarie, id_poste, date_debut, date_fin, motif)
SELECT s.id_salarie, p.id_poste, v.date_debut, NULL, 'Affectation initiale (fixture Bloc 7)'
FROM (VALUES
    ('MAT-WF-0010', 'Responsable Développement', DATE '2019-05-06'),
    ('MAT-WF-0011', 'Responsable Comptabilité', DATE '2017-09-18'),
    ('MAT-WF-0012', 'Responsable Commercial', DATE '2020-02-03')
) AS v(matricule, intitule_poste, date_debut)
JOIN salarie s ON s.matricule = v.matricule
JOIN poste p ON p.intitule = v.intitule_poste
WHERE NOT EXISTS (
    SELECT 1 FROM affectation a
    WHERE a.id_salarie = s.id_salarie AND a.id_poste = p.id_poste AND a.date_fin IS NULL
);

-- ----------------------------------------------------------------------------
-- 5. DÉLÉGATION (fixture : Harena déléguée à Hasina en décembre 2026)
-- ----------------------------------------------------------------------------

INSERT INTO delegation_validation (id_manager, id_delegue, date_debut, date_fin, motif)
SELECT man.id_salarie, del.id_salarie, DATE '2026-12-01', DATE '2026-12-31',
       'Fixture Bloc 7 : manager en congé'
FROM salarie man
JOIN salarie del ON del.matricule = 'MAT-WF-0005'
WHERE man.matricule = 'MAT-WF-0010'
  AND NOT EXISTS (
      SELECT 1 FROM delegation_validation dv
      WHERE dv.id_manager = man.id_salarie AND dv.id_delegue = del.id_salarie
        AND dv.date_debut = DATE '2026-12-01'
  );

-- ----------------------------------------------------------------------------
-- 6. CONGÉS : soldes ANNUEL 2026 + 1 demande de test par manager
-- ----------------------------------------------------------------------------

INSERT INTO solde_conge (id_salarie, id_type_conge, annee, droit_acquis, report, pris, en_attente)
SELECT s.id_salarie, tc.id_type_conge, 2026, 25.00, 0, 0, 0
FROM salarie s
JOIN type_conge tc ON tc.code = 'ANNUEL'
WHERE s.matricule IN ('MAT-WF-0010', 'MAT-WF-0011', 'MAT-WF-0012')
ON CONFLICT (id_salarie, id_type_conge, annee) DO NOTHING;

INSERT INTO demande_conge (id_salarie, id_type_conge, id_statut, date_debut, date_fin,
                           demi_journee_debut, demi_journee_fin, nb_jours, motif, date_demande)
SELECT s.id_salarie, tc.id_type_conge, sdc.id_statut, v.date_debut, v.date_fin,
       false, false, v.nb_jours, 'Fixture workflow (13)', TIMESTAMP '2026-11-20 10:00:00'
FROM (VALUES
    ('MAT-WF-0010', DATE '2026-12-14', DATE '2026-12-18', 5.00, 'EN_ATTENTE_MANAGER'),
    ('MAT-WF-0011', DATE '2026-11-09', DATE '2026-11-13', 5.00, 'VALIDEE'),
    ('MAT-WF-0012', DATE '2026-12-07', DATE '2026-12-11', 5.00, 'EN_ATTENTE_MANAGER')
) AS v(matricule, date_debut, date_fin, nb_jours, statut_code)
JOIN salarie s ON s.matricule = v.matricule
JOIN type_conge tc ON tc.code = 'ANNUEL'
JOIN statut_demande_conge sdc ON sdc.code = v.statut_code
WHERE NOT EXISTS (
    SELECT 1 FROM demande_conge dc
    WHERE dc.id_salarie = s.id_salarie AND dc.date_debut = v.date_debut AND dc.date_fin = v.date_fin
);

COMMIT;
