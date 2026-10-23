-- ============================================================================
-- Fixtures : grille salariale + contrôleur de gestion (département Finance)
--
-- Prérequis : schéma PostgreSQL créé, fixtures_recrutement.sql appliqué au moins
-- pour les référentiels (postes, rangs, services, départements, status_salarie).
--
-- La fixture est idempotente et peut être rejouée sans dupliquer les lignes.
--
-- Compte de validation budgétaire : identifiant « controle », mot de passe
-- « controle123 » (compte existant, rattaché à un employé du département Finance).
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. Poste « Contrôleur de gestion » rattaché au service Comptabilité (Finance)
-- ----------------------------------------------------------------------------

INSERT INTO poste (id_service, intitule, description, rang_id)
SELECT s.id_service, 'Contrôleur de gestion', 'Analyse les budgets et valide les demandes de recrutement', r.id
FROM service s, rang r
WHERE s.nom = 'Comptabilité'
  AND r.designation = 'Senior'
  AND NOT EXISTS (SELECT 1 FROM poste p WHERE p.intitule = 'Contrôleur de gestion');

-- ----------------------------------------------------------------------------
-- 2. Grille salariale active (2025-01-01, sans date de fin) pour chaque poste
-- ----------------------------------------------------------------------------

INSERT INTO grille_salariale (id_poste, rang, salaire_min, salaire_max, date_debut)
SELECT p.id_poste, p.rang_id, v.salaire_min, v.salaire_max, DATE '2025-01-01'
FROM (VALUES
    ('Responsable Recrutement',                 42000.00, 58000.00),
    ('Chargé de Recrutement',                   30000.00, 42000.00),
    ('Responsable Administration du Personnel', 40000.00, 55000.00),
    ('Assistant RH',                            26000.00, 34000.00),
    ('Développeur Senior',                      45000.00, 62000.00),
    ('Développeur Junior',                      30000.00, 40000.00),
    ('Administrateur Systèmes et Réseaux',      38000.00, 52000.00),
    ('Comptable Senior',                        36000.00, 48000.00),
    ('Comptable Junior',                        27000.00, 35000.00),
    ('Responsable Commercial',                  43000.00, 60000.00),
    ('Commercial Terrain',                      28000.00, 40000.00),
    ('Responsable Communication',               40000.00, 55000.00),
    ('Chargé de Communication',                 28000.00, 38000.00),
    ('AUTH - Administrateur',                   45000.00, 65000.00),
    ('Contrôleur de gestion',                   38000.00, 52000.00)
) AS v(intitule, salaire_min, salaire_max)
JOIN poste p ON p.intitule = v.intitule
WHERE NOT EXISTS (
    SELECT 1
    FROM grille_salariale g
    WHERE g.id_poste = p.id_poste
      AND g.date_debut = DATE '2025-01-01'
);

-- ----------------------------------------------------------------------------
-- 3. Salarié + employé du département Finance (contrôle de gestion)
-- ----------------------------------------------------------------------------

INSERT INTO salarie (matricule, nom, prenom, email, telephone, date_embauche, statut)
SELECT 'MAT-FIN-0001', 'Rakotomalala', 'Andry', 'andry.rakotomalala@example.test', '0320000010',
       DATE '2021-03-01', ss.id
FROM status_salarie ss
WHERE ss.designation = 'Actif'
  AND NOT EXISTS (SELECT 1 FROM salarie s WHERE s.matricule = 'MAT-FIN-0001');

INSERT INTO employe (nom, date_de_naissance, poste_id, id_salarie)
SELECT 'Contrôleur de gestion - Andry', DATE '1989-09-12', p.id_poste, s.id_salarie
FROM poste p, salarie s
WHERE p.intitule = 'Contrôleur de gestion'
  AND s.matricule = 'MAT-FIN-0001'
  AND NOT EXISTS (SELECT 1 FROM employe e WHERE e.id_salarie = s.id_salarie);

-- ----------------------------------------------------------------------------
-- 4. Rattacher le compte « controle » à cet employé (validation budgétaire)
-- ----------------------------------------------------------------------------

UPDATE utilisateur
SET id_employe = (
        SELECT e.id
        FROM employe e
        JOIN salarie s ON s.id_salarie = e.id_salarie
        WHERE s.matricule = 'MAT-FIN-0001'
    ),
    actif = true,
    doit_changer_mot_de_passe = false
WHERE identifiant = 'controle'
  AND EXISTS (SELECT 1 FROM salarie WHERE matricule = 'MAT-FIN-0001');

-- ----------------------------------------------------------------------------
-- 5. Séquence cohérente après insertion
-- ----------------------------------------------------------------------------

SELECT setval(pg_get_serial_sequence('poste', 'id_poste'), COALESCE((SELECT MAX(id_poste) FROM poste), 1));
SELECT setval(pg_get_serial_sequence('grille_salariale', 'id_grille'), COALESCE((SELECT MAX(id_grille) FROM grille_salariale), 1));
SELECT setval(pg_get_serial_sequence('salarie', 'id_salarie'), COALESCE((SELECT MAX(id_salarie) FROM salarie), 1));
SELECT setval(pg_get_serial_sequence('employe', 'id'), COALESCE((SELECT MAX(id) FROM employe), 1));

COMMIT;
