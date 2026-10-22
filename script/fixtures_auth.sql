BEGIN;

INSERT INTO departement (nom, description)
SELECT 'AUTH - Direction', 'Fixture Piste A'
WHERE NOT EXISTS (SELECT 1 FROM departement WHERE nom = 'AUTH - Direction');

INSERT INTO service (id_departement, nom, description)
SELECT d.id_departement, 'AUTH - Service', 'Fixture Piste A'
FROM departement d
WHERE d.nom = 'AUTH - Direction'
  AND NOT EXISTS (SELECT 1 FROM service WHERE nom = 'AUTH - Service');

INSERT INTO rang (designation)
SELECT 'AUTH - Manager'
WHERE NOT EXISTS (SELECT 1 FROM rang WHERE designation = 'AUTH - Manager');

INSERT INTO poste (id_service, intitule, description, rang_id)
SELECT s.id_service, 'AUTH - Administrateur', 'Fixture Piste A', r.id
FROM service s, rang r
WHERE s.nom = 'AUTH - Service'
  AND r.designation = 'AUTH - Manager'
  AND NOT EXISTS (SELECT 1 FROM poste WHERE intitule = 'AUTH - Administrateur');

INSERT INTO status_salarie (designation)
SELECT 'Actif'
WHERE NOT EXISTS (SELECT 1 FROM status_salarie WHERE designation = 'Actif');

INSERT INTO salarie (matricule, nom, prenom, email, date_embauche, statut)
SELECT 'MAT-AUTH-0001', 'Admin', 'Auth', 'admin.auth@example.test', CURRENT_DATE, ss.id
FROM status_salarie ss
WHERE ss.designation = 'Actif'
  AND NOT EXISTS (SELECT 1 FROM salarie WHERE matricule = 'MAT-AUTH-0001');

INSERT INTO salarie (matricule, nom, prenom, email, date_embauche, statut)
SELECT 'MAT-AUTH-0002', 'Rh', 'Auth', 'rh.auth@example.test', CURRENT_DATE, ss.id
FROM status_salarie ss
WHERE ss.designation = 'Actif'
  AND NOT EXISTS (SELECT 1 FROM salarie WHERE matricule = 'MAT-AUTH-0002');

INSERT INTO salarie (matricule, nom, prenom, email, date_embauche, statut)
SELECT 'MAT-AUTH-0003', 'Manager', 'Auth', 'manager.auth@example.test', CURRENT_DATE, ss.id
FROM status_salarie ss
WHERE ss.designation = 'Actif'
  AND NOT EXISTS (SELECT 1 FROM salarie WHERE matricule = 'MAT-AUTH-0003');

INSERT INTO employe (nom, date_de_naissance, poste_id, id_salarie)
SELECT 'Administrateur Auth', DATE '1980-01-01', p.id_poste, s.id_salarie
FROM poste p, salarie s
WHERE p.intitule = 'AUTH - Administrateur'
  AND s.matricule = 'MAT-AUTH-0001'
  AND NOT EXISTS (SELECT 1 FROM employe e WHERE e.id_salarie = s.id_salarie);

INSERT INTO employe (nom, date_de_naissance, poste_id, id_salarie)
SELECT 'RH Auth', DATE '1985-01-01', p.id_poste, s.id_salarie
FROM poste p, salarie s
WHERE p.intitule = 'AUTH - Administrateur'
  AND s.matricule = 'MAT-AUTH-0002'
  AND NOT EXISTS (SELECT 1 FROM employe e WHERE e.id_salarie = s.id_salarie);

INSERT INTO employe (nom, date_de_naissance, poste_id, id_salarie)
SELECT 'Manager Auth', DATE '1986-01-01', p.id_poste, s.id_salarie
FROM poste p, salarie s
WHERE p.intitule = 'AUTH - Administrateur'
  AND s.matricule = 'MAT-AUTH-0003'
  AND NOT EXISTS (SELECT 1 FROM employe e WHERE e.id_salarie = s.id_salarie);

-- password: admin123
INSERT INTO utilisateur (id_employe, identifiant, mot_de_passe_hash, role, actif)
SELECT e.id, 'admin', '$2y$10$es0U0VGtKgq2E.1dbvUN8uDTZbvahlPbRsnp4XZE21aXnP3NaxQAy', 'admin', true
FROM employe e
WHERE e.nom = 'Administrateur Auth'
  AND NOT EXISTS (SELECT 1 FROM utilisateur WHERE identifiant = 'admin');

-- password: rh123
INSERT INTO utilisateur (id_employe, identifiant, mot_de_passe_hash, role, actif)
SELECT e.id, 'rh', '$2y$10$eQfviYz.MyfpAOsFIPZXS.r2gTXE0P.cDcXyqEdIK18yL4LX6k45.', 'rh', true
FROM employe e
WHERE e.nom = 'RH Auth'
  AND NOT EXISTS (SELECT 1 FROM utilisateur WHERE identifiant = 'rh');

-- password: manager123
INSERT INTO utilisateur (id_employe, identifiant, mot_de_passe_hash, role, actif)
SELECT e.id, 'manager', '$2y$10$VZG2/Tz.Dw/oqqfYO9yI.u29wx7ziSRpcP8zgG6JOh9TW/o76HswW', 'manager', true
FROM employe e
WHERE e.nom = 'Manager Auth'
  AND NOT EXISTS (SELECT 1 FROM utilisateur WHERE identifiant = 'manager');

-- password: recruteur123
INSERT INTO utilisateur (id_employe, identifiant, mot_de_passe_hash, role, actif)
SELECT NULL, 'recruteur', '$2y$10$GXLr5yg5TtVJuSzDIfVsVO3rImUu640Ykl35kr63tmZNPpD7qWuHG', 'recruteur', true
WHERE NOT EXISTS (SELECT 1 FROM utilisateur WHERE identifiant = 'recruteur');

-- password: direction123
INSERT INTO utilisateur (id_employe, identifiant, mot_de_passe_hash, role, actif)
SELECT NULL, 'direction', '$2y$10$TpbiaWaJ7xni1PapMjF9YOq33mh78p.dIFTp0qD6Ijbh4LtwnoOkW', 'direction', true
WHERE NOT EXISTS (SELECT 1 FROM utilisateur WHERE identifiant = 'direction');

-- password: controle123
INSERT INTO utilisateur (id_employe, identifiant, mot_de_passe_hash, role, actif)
SELECT NULL, 'controle', '$2y$10$UBZOskUsVTfgm25nSgO9VeQjdg0D4/yzuIjitteXiz6dn8fwQlg6a', 'controle_gestion', true
WHERE NOT EXISTS (SELECT 1 FROM utilisateur WHERE identifiant = 'controle');

-- password: salarie123
INSERT INTO utilisateur (id_employe, identifiant, mot_de_passe_hash, role, actif)
SELECT NULL, 'salarie', '$2y$10$ObzZ9nBNCg.ZU.3Rag7sh.P5hPrFl7VSRWPp7hy72kOSynTes0A3u', 'salarie', true
WHERE NOT EXISTS (SELECT 1 FROM utilisateur WHERE identifiant = 'salarie');

INSERT INTO action (action) VALUES
    ('LOGIN'),
    ('LOGOUT'),
    ('REFUS'),
    ('CREATE'),
    ('UPDATE'),
    ('CONSULTATION')
ON CONFLICT (action) DO NOTHING;

COMMIT;
