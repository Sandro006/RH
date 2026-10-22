-- ============================================================================
-- Fixtures du module Recrutement
--
-- Prérequis : le schéma PostgreSQL doit avoir été créé au préalable.
-- La fixture est idempotente et peut être exécutée après Donne_status.sql,
-- fixtures_auth.sql ou fixtures_embauche.sql.
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. Référentiels utilisés par le recrutement
-- ----------------------------------------------------------------------------

INSERT INTO rang (designation)
SELECT v.designation
FROM (VALUES
    ('Junior'),
    ('Intermédiaire'),
    ('Senior'),
    ('Chef d''équipe'),
    ('Manager'),
    ('Responsable')
) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM rang r WHERE r.designation = v.designation
);

INSERT INTO type_contrat (designation)
SELECT v.designation
FROM (VALUES ('CDI'), ('CDD'), ('Stage'), ('Alternance')) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM type_contrat t WHERE t.designation = v.designation
);

-- Statuts des demandes de recrutement.
INSERT INTO status (designation)
SELECT v.designation
FROM (VALUES
    ('En cours'),
    ('En contrôle RH'),
    ('En validation budgétaire'),
    ('En validation hiérarchique'),
    ('En consultation interne'),
    ('Validée'),
    ('Refusée'),
    ('À corriger'),
    ('Annulée')
) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status s WHERE s.designation = v.designation
);

-- Statuts de candidature.
INSERT INTO status_candidature (designation)
SELECT v.designation
FROM (VALUES
    ('Reçue'),
    ('En présélection'),
    ('Présélectionnée'),
    ('Entretien prévu'),
    ('En évaluation'),
    ('Retenue'),
    ('Refusée'),
    ('Retirée')
) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_candidature s WHERE s.designation = v.designation
);

-- Autres statuts référencés par les écrans et les modules liés.
INSERT INTO status_offre_emploi (designation)
SELECT v.designation
FROM (VALUES ('Brouillon'), ('Publiée'), ('Suspendue'), ('Clôturée'), ('Annulée')) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_offre_emploi s WHERE s.designation = v.designation
);

INSERT INTO status_entretien (designation)
SELECT v.designation
FROM (VALUES ('Planifié'), ('Confirmé'), ('Réalisé'), ('Reporté'), ('Annulé')) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_entretien s WHERE s.designation = v.designation
);

INSERT INTO status_salarie (designation)
SELECT v.designation
FROM (VALUES ('En attente d''embauche'), ('Actif'), ('En période d''essai'), ('Suspendu'), ('Parti')) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_salarie s WHERE s.designation = v.designation
);

INSERT INTO status_contrat (designation)
SELECT v.designation
FROM (VALUES ('En préparation'), ('En attente de signature'), ('Signé'), ('En cours'), ('Terminé'), ('Résilié')) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_contrat s WHERE s.designation = v.designation
);

INSERT INTO status_proposition_embauche (designation)
SELECT v.designation
FROM (VALUES ('À envoyer'), ('Envoyée'), ('En négociation'), ('Acceptée'), ('Refusée'), ('Expirée')) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_proposition_embauche s WHERE s.designation = v.designation
);

INSERT INTO status_revendication (designation)
SELECT v.designation
FROM (VALUES ('Soumise'), ('En discussion'), ('Acceptée'), ('Refusée'), ('Retirée')) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_revendication s WHERE s.designation = v.designation
);

INSERT INTO status_onboarding (designation)
SELECT v.designation
FROM (VALUES ('À démarrer'), ('En cours'), ('En attente'), ('Terminé'), ('Annulé')) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_onboarding s WHERE s.designation = v.designation
);

INSERT INTO status_onboarding_tache (designation)
SELECT v.designation
FROM (VALUES ('À faire'), ('En cours'), ('Bloquée'), ('Terminée'), ('Annulée')) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_onboarding_tache s WHERE s.designation = v.designation
);

-- ----------------------------------------------------------------------------
-- 2. Départements et services
-- ----------------------------------------------------------------------------

INSERT INTO departement (nom, description)
SELECT v.nom, v.description
FROM (VALUES
    ('Ressources humaines', 'Gestion du personnel et du recrutement'),
    ('Informatique', 'Développement et maintenance des systèmes informatiques'),
    ('Finance', 'Gestion financière et comptable'),
    ('Opérations', 'Pilotage des opérations et de la qualité')
) AS v(nom, description)
WHERE NOT EXISTS (
    SELECT 1 FROM departement d WHERE d.nom = v.nom
);

INSERT INTO service (id_departement, nom, description)
SELECT d.id_departement, v.nom, v.description
FROM (VALUES
    ('Ressources humaines', 'Recrutement', 'Gestion des candidatures et des embauches'),
    ('Ressources humaines', 'Administration du personnel', 'Gestion administrative des salariés'),
    ('Informatique', 'Développement', 'Développement des applications métiers'),
    ('Informatique', 'Infrastructure', 'Gestion des serveurs et du réseau'),
    ('Finance', 'Comptabilité', 'Gestion des opérations comptables'),
    ('Opérations', 'Qualité', 'Suivi de la qualité et de l’amélioration continue')
) AS v(departement, nom, description)
JOIN departement d ON d.nom = v.departement
WHERE NOT EXISTS (
    SELECT 1 FROM service s WHERE s.nom = v.nom
);

-- ----------------------------------------------------------------------------
-- 3. Postes et grilles salariales
-- ----------------------------------------------------------------------------

INSERT INTO poste (id_service, intitule, description, rang_id)
SELECT s.id_service, v.intitule, v.description, r.id
FROM (VALUES
    ('Recrutement', 'Responsable recrutement', 'Supervise le processus de recrutement', 'Responsable'),
    ('Recrutement', 'Chargé de recrutement', 'Gère les candidatures et les entretiens', 'Senior'),
    ('Développement', 'Développeur backend', 'Conçoit et maintient les services applicatifs', 'Intermédiaire'),
    ('Développement', 'Développeur frontend', 'Conçoit les interfaces web et leur intégration', 'Junior'),
    ('Infrastructure', 'Administrateur systèmes et réseaux', 'Administre les serveurs et le réseau', 'Senior'),
    ('Comptabilité', 'Contrôleur de gestion', 'Analyse les résultats et les budgets', 'Senior'),
    ('Qualité', 'Responsable qualité', 'Pilote la démarche qualité', 'Responsable')
) AS v(service, intitule, description, rang)
JOIN service s ON s.nom = v.service
JOIN rang r ON r.designation = v.rang
WHERE NOT EXISTS (
    SELECT 1 FROM poste p WHERE p.intitule = v.intitule
);

INSERT INTO grille_salariale (id_poste, rang, salaire_min, salaire_max, date_debut)
SELECT p.id_poste, r.id, v.salaire_min, v.salaire_max, DATE '2025-01-01'
FROM (VALUES
    ('Responsable recrutement', 'Responsable', 42000.00, 58000.00),
    ('Chargé de recrutement', 'Senior', 32000.00, 45000.00),
    ('Développeur backend', 'Intermédiaire', 36000.00, 50000.00),
    ('Développeur frontend', 'Junior', 28000.00, 38000.00),
    ('Administrateur systèmes et réseaux', 'Senior', 35000.00, 48000.00),
    ('Contrôleur de gestion', 'Senior', 38000.00, 52000.00),
    ('Responsable qualité', 'Responsable', 40000.00, 56000.00)
) AS v(intitule, rang, salaire_min, salaire_max)
JOIN poste p ON p.intitule = v.intitule
JOIN rang r ON r.designation = v.rang
WHERE NOT EXISTS (
    SELECT 1
    FROM grille_salariale g
    WHERE g.id_poste = p.id_poste
      AND g.rang = r.id
      AND g.date_debut = DATE '2025-01-01'
);

-- ----------------------------------------------------------------------------
-- 4. Salariés et employés de référence
-- ----------------------------------------------------------------------------

INSERT INTO salarie (matricule, nom, prenom, email, telephone, date_embauche, statut)
SELECT v.matricule, v.nom, v.prenom, v.email, v.telephone, v.date_embauche, ss.id
FROM (VALUES
    ('MAT-RH-0001', 'Rakoto', 'Miora', 'miora.rakoto@example.test', '0320000001', DATE '2022-04-04', 'Actif'),
    ('MAT-RH-0002', 'Ramanantsoa', 'Hery', 'hery.ramanantsoa@example.test', '0320000002', DATE '2021-09-13', 'Actif'),
    ('MAT-IT-0001', 'Andriamihaja', 'Nina', 'nina.andriamihaja@example.test', '0320000003', DATE '2023-01-16', 'Actif'),
    ('MAT-FIN-0001', 'Rasoanaivo', 'Tiana', 'tiana.rasoanaivo@example.test', '0320000004', DATE '2020-06-01', 'Actif'),
    ('MAT-OPS-0001', 'Randrianarisoa', 'Lova', 'lova.randrianarisoa@example.test', '0320000005', DATE '2024-02-12', 'En période d''essai')
) AS v(matricule, nom, prenom, email, telephone, date_embauche, statut)
JOIN status_salarie ss ON ss.designation = v.statut
WHERE NOT EXISTS (
    SELECT 1 FROM salarie s WHERE s.matricule = v.matricule
);

INSERT INTO employe (nom, date_de_naissance, poste_id, id_salarie)
SELECT v.nom_employe, v.date_de_naissance, p.id_poste, s.id_salarie
FROM (VALUES
    ('Responsable RH - Miora', DATE '1988-03-15', 'Responsable recrutement', 'MAT-RH-0001'),
    ('Recruteur - Hery', DATE '1986-11-22', 'Chargé de recrutement', 'MAT-RH-0002'),
    ('Développeuse - Nina', DATE '1993-07-08', 'Développeur backend', 'MAT-IT-0001'),
    ('Contrôleur - Tiana', DATE '1985-01-30', 'Contrôleur de gestion', 'MAT-FIN-0001'),
    ('Qualité - Lova', DATE '1997-05-19', 'Responsable qualité', 'MAT-OPS-0001')
) AS v(nom_employe, date_de_naissance, poste, matricule)
JOIN poste p ON p.intitule = v.poste
JOIN salarie s ON s.matricule = v.matricule
WHERE NOT EXISTS (
    SELECT 1 FROM employe e WHERE e.id_salarie = s.id_salarie
);

-- Les séquences restent cohérentes même si la base contenait déjà des lignes.
SELECT setval(pg_get_serial_sequence('departement', 'id_departement'), COALESCE((SELECT MAX(id_departement) FROM departement), 1));
SELECT setval(pg_get_serial_sequence('service', 'id_service'), COALESCE((SELECT MAX(id_service) FROM service), 1));
SELECT setval(pg_get_serial_sequence('poste', 'id_poste'), COALESCE((SELECT MAX(id_poste) FROM poste), 1));
SELECT setval(pg_get_serial_sequence('grille_salariale', 'id_grille'), COALESCE((SELECT MAX(id_grille) FROM grille_salariale), 1));
SELECT setval(pg_get_serial_sequence('salarie', 'id_salarie'), COALESCE((SELECT MAX(id_salarie) FROM salarie), 1));
SELECT setval(pg_get_serial_sequence('employe', 'id'), COALESCE((SELECT MAX(id) FROM employe), 1));

COMMIT;
