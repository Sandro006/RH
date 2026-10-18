-- ============================================================================
-- Fixtures Massives Piste D — Princio (Embauche) : 50 enregistrements par table
-- Idempotente
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. Insertion de 50 Candidats (IDs 1002 à 1051)
-- ----------------------------------------------------------------------------
INSERT INTO candidat (id_candidat, nom, prenom, email, telephone, adresse, date_creation)
VALUES 
(1002, 'Ndiaye', 'Fatou', 'f.ndiaye@test.com', '0723456789', 'Abidjan', NOW()),
(1003, 'Dubois', 'Marc', 'm.dubois@test.com', '0634567890', 'Paris', NOW()),
(1004, 'Koné', 'Awa', 'a.kone@test.com', '0745678901', 'Bamako', NOW()),
(1005, 'Diop', 'Moussa', 'm.diop@test.com', '0656789012', 'Dakar', NOW()),
(1006, 'Martin', 'Sophie', 's.martin@test.com', '0667890123', 'Lyon', NOW()),
(1007, 'Traoré', 'Ibrahim', 'i.traore@test.com', '0778901234', 'Ouagadougou', NOW()),
(1008, 'Bernard', 'Lucas', 'l.bernard@test.com', '0689012345', 'Marseille', NOW()),
(1009, 'Kouassi', 'Aya', 'a.kouassi@test.com', '0790123456', 'Abidjan', NOW()),
(1010, 'Petit', 'Chloé', 'c.petit@test.com', '0601234567', 'Bordeaux', NOW()),
(1011, 'Sow', 'Ousmane', 'o.sow@test.com', '0712345678', 'Conakry', NOW()),
(1012, 'Robert', 'Thomas', 't.robert@test.com', '0623456789', 'Toulouse', NOW()),
(1013, 'Diallo', 'Mariama', 'm.diallo2@test.com', '0734567890', 'Dakar', NOW()),
(1014, 'Richard', 'Julie', 'j.richard@test.com', '0645678901', 'Nantes', NOW()),
(1015, 'Camara', 'Sekou', 's.camara@test.com', '0756789012', 'Conakry', NOW()),
(1016, 'Durand', 'Nicolas', 'n.durand@test.com', '0667890134', 'Nice', NOW()),
(1017, 'Ba', 'Aminata', 'a.ba@test.com', '0778901245', 'Nouakchott', NOW()),
(1018, 'Moreau', 'Alexandre', 'a.moreau@test.com', '0689012356', 'Strasbourg', NOW()),
(1019, 'Sylla', 'Aboubakar', 'a.sylla@test.com', '0790123467', 'Bamako', NOW()),
(1020, 'Simon', 'Laura', 'l.simon@test.com', '0601234578', 'Montpellier', NOW()),
(1021, 'Fall', 'Cheikh', 'c.fall@test.com', '0712345689', 'Dakar', NOW()),
(1022, 'Laurent', 'David', 'd.laurent@test.com', '0623456790', 'Rennes', NOW()),
(1023, 'Cissé', 'Mariam', 'm.cisse@test.com', '0734567801', 'Bamako', NOW()),
(1024, 'Michel', 'Paul', 'p.michel@test.com', '0645678912', 'Lille', NOW()),
(1025, 'Touré', 'Mamadou', 'm.toure@test.com', '0756789023', 'Abidjan', NOW()),
(1026, 'Garnier', 'Emma', 'e.garnier@test.com', '0667890145', 'Grenoble', NOW()),
(1027, 'Keita', 'Salif', 's.keita@test.com', '0778901256', 'Bamako', NOW()),
(1028, 'Faure', 'Hugo', 'h.faure@test.com', '0689012367', 'Dijon', NOW()),
(1029, 'Sanogo', 'Adama', 'a.sanogo@test.com', '0790123478', 'Abidjan', NOW()),
(1030, 'Rousseau', 'Camille', 'c.rousseau@test.com', '0601234589', 'Angers', NOW()),
(1031, 'BARRY', 'Alpha', 'a.barry@test.com', '0712345690', 'Conakry', NOW()),
(1032, 'Blanc', 'Mathieu', 'm.blanc@test.com', '0623456701', 'Nîmes', NOW()),
(1033, 'Ouattara', 'Lassina', 'l.ouattara@test.com', '0734567812', 'Bouaké', NOW()),
(1034, 'Guerin', 'Sarah', 's.guerin@test.com', '0645678923', 'Aix-en-Provence', NOW()),
(1035, 'Bamba', 'Vamara', 'v.bamba@test.com', '0756789034', 'Abidjan', NOW()),
(1036, 'Muller', 'Kevin', 'k.muller@test.com', '0667890156', 'Metz', NOW()),
(1037, 'Coulibaly', 'Fanta', 'f.coulibaly@test.com', '0778901267', 'Bamako', NOW()),
(1038, 'Henry', 'Manon', 'm.henry@test.com', '0689012378', 'Rouen', NOW()),
(1039, 'Kone', 'Bakary', 'b.kone@test.com', '0790123489', 'Abidjan', NOW()),
(1040, 'Roussel', 'Clément', 'c.roussel@test.com', '0601234590', 'Toulon', NOW()),
(1041, 'Zongo', 'Issaka', 'i.zongo@test.com', '0712345601', 'Ouagadougou', NOW()),
(1042, 'Perez', 'Audrey', 'a.perez@test.com', '0623456712', 'Clermont-Ferrand', NOW()),
(1043, 'Traore', 'Fatoumata', 'f.traore@test.com', '0734567823', 'Bamako', NOW()),
(1044, 'Fontaine', 'Romain', 'r.fontaine@test.com', '0645678934', 'Reims', NOW()),
(1045, 'Sidibe', 'Mohamed', 'm.sidibe@test.com', '0756789045', 'Dakar', NOW()),
(1046, 'Chevalier', 'Anaïs', 'a.chevalier@test.com', '0667890167', 'Le Havre', NOW()),
(1047, 'Doumbia', 'Adissa', 'a.doumbia@test.com', '0778901278', 'Abidjan', NOW()),
(1048, 'Masson', 'Jérémy', 'j.masson@test.com', '0689012389', 'St. Etienne', NOW()),
(1049, 'Cisse', 'Abdoulaye', 'a.cisse@test.com', '0790123490', 'Dakar', NOW()),
(1050, 'Mercier', 'Elodie', 'e.mercier@test.com', '0601234501', 'Tours', NOW()),
(1051, 'Camara', 'Djibril', 'd.camara@test.com', '0712345612', 'Conakry', NOW())
ON CONFLICT (id_candidat) DO NOTHING;

-- ----------------------------------------------------------------------------
-- 2. Insertion de 50 Offres d'Emploi (IDs 1002 à 1051)
-- ----------------------------------------------------------------------------
INSERT INTO offre_emploi (id_offre, statut, id_demande, titre, description,
                          date_publication, date_cloture, canal_diffusion, date_creation)
VALUES 
(1002, 2, 2, 'Lead Dev Python', 'Offre Python', CURRENT_DATE, CURRENT_DATE + 30, 'LinkedIn', NOW()),
(1003, 2, 3, 'Chef de Projet Digital', 'Offre Management', CURRENT_DATE, CURRENT_DATE + 45, 'Site Interne', NOW()),
(1004, 1, 4, 'Data Analyst Junior', 'Offre Data', CURRENT_DATE, CURRENT_DATE + 15, 'JobBoard', NOW()),
(1005, 2, 5, 'DevOps Engineer', 'Offre Cloud', CURRENT_DATE, CURRENT_DATE + 30, 'Site', NOW()),
(1006, 2, 6, 'UX/UI Designer', 'Offre Design', CURRENT_DATE, CURRENT_DATE + 20, 'Behance', NOW()),
(1007, 1, 7, 'Scrum Master', 'Offre Agile', CURRENT_DATE, CURRENT_DATE + 25, 'LinkedIn', NOW()),
(1008, 2, 8, 'Architecte Logiciel', 'Offre Architecture', CURRENT_DATE, CURRENT_DATE + 60, 'Site', NOW()),
(1009, 2, 9, 'Administrateur Systèmes', 'Offre SysAdmin', CURRENT_DATE, CURRENT_DATE + 30, 'JobBoard', NOW()),
(1010, 1, 10, 'Community Manager', 'Offre Marketing', CURRENT_DATE, CURRENT_DATE + 15, 'Réseaux', NOW()),
(1011, 2, 11, 'Dev Fullstack JS', 'Offre JS', CURRENT_DATE, CURRENT_DATE + 30, 'LinkedIn', NOW()),
(1012, 2, 12, 'Product Owner', 'Offre PO', CURRENT_DATE, CURRENT_DATE + 30, 'Site', NOW()),
(1013, 2, 13, 'Testeur QA', 'Offre QA', CURRENT_DATE, CURRENT_DATE + 20, 'JobBoard', NOW()),
(1014, 1, 14, 'SecOps Specialist', 'Offre Sécurité', CURRENT_DATE, CURRENT_DATE + 40, 'LinkedIn', NOW()),
(1015, 2, 15, 'Data Engineer', 'Offre Data', CURRENT_DATE, CURRENT_DATE + 30, 'Site', NOW()),
(1016, 2, 16, 'Développeur Mobile', 'Offre Mobile', CURRENT_DATE, CURRENT_DATE + 25, 'JobBoard', NOW()),
(1017, 1, 17, 'Tech Lead Java', 'Offre Java', CURRENT_DATE, CURRENT_DATE + 30, 'LinkedIn', NOW()),
(1018, 2, 18, 'Consultant BI', 'Offre BI', CURRENT_DATE, CURRENT_DATE + 30, 'Site', NOW()),
(1019, 2, 19, 'Support IT N2', 'Offre Support', CURRENT_DATE, CURRENT_DATE + 15, 'JobBoard', NOW()),
(1020, 1, 20, 'Administrateur BDD', 'Offre DBA', CURRENT_DATE, CURRENT_DATE + 45, 'LinkedIn', NOW()),
(1021, 2, 21, 'Dev C++', 'Offre C++', CURRENT_DATE, CURRENT_DATE + 30, 'Site', NOW()),
(1022, 2, 22, 'Analyste Financier', 'Offre Finance', CURRENT_DATE, CURRENT_DATE + 30, 'JobBoard', NOW()),
(1023, 1, 23, 'Responsable RH', 'Offre RH', CURRENT_DATE, CURRENT_DATE + 30, 'LinkedIn', NOW()),
(1024, 2, 24, 'Rédacteur Web', 'Offre Content', CURRENT_DATE, CURRENT_DATE + 15, 'Site', NOW()),
(1025, 2, 25, 'Growth Hacker', 'Offre Marketing', CURRENT_DATE, CURRENT_DATE + 20, 'JobBoard', NOW()),
(1026, 1, 26, 'Dev Ruby', 'Offre Ruby', CURRENT_DATE, CURRENT_DATE + 30, 'LinkedIn', NOW()),
(1027, 2, 27, 'Ingénieur IA', 'Offre AI', CURRENT_DATE, CURRENT_DATE + 50, 'Site', NOW()),
(1028, 2, 28, 'Traffic Manager', 'Offre Marketing', CURRENT_DATE, CURRENT_DATE + 25, 'JobBoard', NOW()),
(1029, 1, 29, 'Architecte Cloud', 'Offre Cloud', CURRENT_DATE, CURRENT_DATE + 40, 'LinkedIn', NOW()),
(1030, 2, 30, 'Dev PHP / Symfony', 'Offre PHP', CURRENT_DATE, CURRENT_DATE + 30, 'Site', NOW()),
(1031, 2, 31, 'Business Analyst', 'Offre BA', CURRENT_DATE, CURRENT_DATE + 30, 'JobBoard', NOW()),
(1032, 1, 32, 'Account Manager', 'Offre Sales', CURRENT_DATE, CURRENT_DATE + 30, 'LinkedIn', NOW()),
(1033, 2, 33, 'Dev Go', 'Offre Go', CURRENT_DATE, CURRENT_DATE + 30, 'Site', NOW()),
(1034, 2, 34, 'Chargé de Recrutement', 'Offre RH', CURRENT_DATE, CURRENT_DATE + 15, 'JobBoard', NOW()),
(1035, 1, 35, 'Ingénieur Réseau', 'Offre Réseau', CURRENT_DATE, CURRENT_DATE + 30, 'LinkedIn', NOW()),
(1036, 2, 36, 'Dev Rust', 'Offre Rust', CURRENT_DATE, CURRENT_DATE + 30, 'Site', NOW()),
(1037, 2, 37, 'Chief Technical Officer', 'Offre Direction', CURRENT_DATE, CURRENT_DATE + 90, 'Cabinet', NOW()),
(1038, 1, 38, 'Agronome Digital', 'Offre Agrotech', CURRENT_DATE, CURRENT_DATE + 30, 'JobBoard', NOW()),
(1039, 2, 39, 'Dev .NET', 'Offre .NET', CURRENT_DATE, CURRENT_DATE + 30, 'LinkedIn', NOW()),
(1040, 2, 40, 'Auditeur Informatique', 'Offre Audit', CURRENT_DATE, CURRENT_DATE + 30, 'Site', NOW()),
(1041, 1, 41, 'Formateur IT', 'Offre Formation', CURRENT_DATE, CURRENT_DATE + 20, 'JobBoard', NOW()),
(1042, 2, 42, 'Consultant Cybersécurité', 'Offre Sec', CURRENT_DATE, CURRENT_DATE + 45, 'LinkedIn', NOW()),
(1043, 2, 43, 'Dev Swift', 'Offre iOS', CURRENT_DATE, CURRENT_DATE + 30, 'Site', NOW()),
(1044, 1, 44, 'Dev Kotlin', 'Offre Android', CURRENT_DATE, CURRENT_DATE + 30, 'JobBoard', NOW()),
(1045, 2, 45, 'Chef de Produit', 'Offre Produit', CURRENT_DATE, CURRENT_DATE + 30, 'LinkedIn', NOW()),
(1046, 2, 46, 'Analyste SOC', 'Offre Sec', CURRENT_DATE, CURRENT_DATE + 30, 'Site', NOW()),
(1047, 1, 47, 'Technical Writer', 'Offre Doc', CURRENT_DATE, CURRENT_DATE + 15, 'JobBoard', NOW()),
(1048, 2, 48, 'Data Architect', 'Offre Data', CURRENT_DATE, CURRENT_DATE + 60, 'LinkedIn', NOW()),
(1049, 2, 49, 'SysAdmin Linux', 'Offre Linux', CURRENT_DATE, CURRENT_DATE + 30, 'Site', NOW()),
(1050, 1, 50, 'Helpdesk Manager', 'Offre Support', CURRENT_DATE, CURRENT_DATE + 15, 'JobBoard', NOW()),
(1051, 2, 51, 'Directeur des Systèmes d’Information', 'Offre DSI', CURRENT_DATE, CURRENT_DATE + 90, 'Cabinet', NOW())
ON CONFLICT (id_offre) DO NOTHING;

-- ----------------------------------------------------------------------------
-- 3. Insertion de 50 Candidatures (IDs 1002 à 1051)
-- ----------------------------------------------------------------------------
INSERT INTO candidature (id_candidature, id_candidat, id_offre, date_candidature,
                         cv, lettre_motivation, origine_candidature, statut)
VALUES 
(1002, 1002, 1002, NOW(), '/cv/c1002.pdf', '/lm/c1002.pdf', 'INTERNE', 3),
(1003, 1003, 1003, NOW(), '/cv/c1003.pdf', '/lm/c1003.pdf', 'EXTERNE', 5),
(1004, 1004, 1004, NOW(), '/cv/c1004.pdf', '/lm/c1004.pdf', 'COOPTATION', 6),
(1005, 1005, 1005, NOW(), '/cv/c1005.pdf', '/lm/c1005.pdf', 'EXTERNE', 2),
(1006, 1006, 1006, NOW(), '/cv/c1006.pdf', '/lm/c1006.pdf', 'INTERNE', 4),
(1007, 1007, 1007, NOW(), '/cv/c1007.pdf', '/lm/c1007.pdf', 'COOPTATION', 1),
(1008, 1008, 1008, NOW(), '/cv/c1008.pdf', '/lm/c1008.pdf', 'EXTERNE', 3),
(1009, 1009, 1009, NOW(), '/cv/c1009.pdf', '/lm/c1009.pdf', 'EXTERNE', 5),
(1010, 1010, 1010, NOW(), '/cv/c1010.pdf', '/lm/c1010.pdf', 'INTERNE', 6),
(1011, 1011, 1011, NOW(), '/cv/c1011.pdf', '/lm/c1011.pdf', 'COOPTATION', 2),
(1012, 1012, 1012, NOW(), '/cv/c1012.pdf', '/lm/c1012.pdf', 'EXTERNE', 3),
(1013, 1013, 1013, NOW(), '/cv/c1013.pdf', '/lm/c1013.pdf', 'EXTERNE', 4),
(1014, 1014, 1014, NOW(), '/cv/c1014.pdf', '/lm/c1014.pdf', 'INTERNE', 1),
(1015, 1015, 1015, NOW(), '/cv/c1015.pdf', '/lm/c1015.pdf', 'COOPTATION', 5),
(1016, 1016, 1016, NOW(), '/cv/c1016.pdf', '/lm/c1016.pdf', 'EXTERNE', 6),
(1017, 1017, 1017, NOW(), '/cv/c1017.pdf', '/lm/c1017.pdf', 'EXTERNE', 2),
(1018, 1018, 1018, NOW(), '/cv/c1018.pdf', '/lm/c1018.pdf', 'INTERNE', 3),
(1019, 1019, 1019, NOW(), '/cv/c1019.pdf', '/lm/c1019.pdf', 'COOPTATION', 4),
(1020, 1020, 1020, NOW(), '/cv/c1020.pdf', '/lm/c1020.pdf', 'EXTERNE', 5),
(1021, 1021, 1021, NOW(), '/cv/c1021.pdf', '/lm/c1021.pdf', 'EXTERNE', 1),
(1022, 1022, 1022, NOW(), '/cv/c1022.pdf', '/lm/c1022.pdf', 'INTERNE', 6),
(1023, 1023, 1023, NOW(), '/cv/c1023.pdf', '/lm/c1023.pdf', 'COOPTATION', 2),
(1024, 1024, 1024, NOW(), '/cv/c1024.pdf', '/lm/c1024.pdf', 'EXTERNE', 3),
(1025, 1025, 1025, NOW(), '/cv/c1025.pdf', '/lm/c1025.pdf', 'EXTERNE', 4),
(1026, 1026, 1026, NOW(), '/cv/c1026.pdf', '/lm/c1026.pdf', 'INTERNE', 5),
(1027, 1027, 1027, NOW(), '/cv/c1027.pdf', '/lm/c1027.pdf', 'COOPTATION', 6),
(1028, 1028, 1028, NOW(), '/cv/c1028.pdf', '/lm/c1028.pdf', 'EXTERNE', 1),
(1029, 1029, 1029, NOW(), '/cv/c1029.pdf', '/lm/c1029.pdf', 'EXTERNE', 2),
(1030, 1030, 1030, NOW(), '/cv/c1030.pdf', '/lm/c1030.pdf', 'INTERNE', 3),
(1031, 1031, 1031, NOW(), '/cv/c1031.pdf', '/lm/c1031.pdf', 'COOPTATION', 4),
(1032, 1032, 1032, NOW(), '/cv/c1032.pdf', '/lm/c1032.pdf', 'EXTERNE', 5),
(1033, 1033, 1033, NOW(), '/cv/c1033.pdf', '/lm/c1033.pdf', 'EXTERNE', 6),
(1034, 1034, 1034, NOW(), '/cv/c1034.pdf', '/lm/c1034.pdf', 'INTERNE', 1),
(1035, 1035, 1035, NOW(), '/cv/c1035.pdf', '/lm/c1035.pdf', 'COOPTATION', 2),
(1036, 1036, 1036, NOW(), '/cv/c1036.pdf', '/lm/c1036.pdf', 'EXTERNE', 3),
(1037, 1037, 1037, NOW(), '/cv/c1037.pdf', '/lm/c1037.pdf', 'EXTERNE', 4),
(1038, 1038, 1038, NOW(), '/cv/c1038.pdf', '/lm/c1038.pdf', 'INTERNE', 5),
(1039, 1039, 1039, NOW(), '/cv/c1039.pdf', '/lm/c1039.pdf', 'COOPTATION', 6),
(1040, 1040, 1040, NOW(), '/cv/c1040.pdf', '/lm/c1040.pdf', 'EXTERNE', 1),
(1041, 1041, 1041, NOW(), '/cv/c1041.pdf', '/lm/c1041.pdf', 'EXTERNE', 2),
(1042, 1042, 1042, NOW(), '/cv/c1042.pdf', '/lm/c1042.pdf', 'INTERNE', 3),
(1043, 1043, 1043, NOW(), '/cv/c1043.pdf', '/lm/c1043.pdf', 'COOPTATION', 4),
(1044, 1044, 1044, NOW(), '/cv/c1044.pdf', '/lm/c1044.pdf', 'EXTERNE', 5),
(1045, 1045, 1045, NOW(), '/cv/c1045.pdf', '/lm/c1045.pdf', 'EXTERNE', 6),
(1046, 1046, 1046, NOW(), '/cv/c1046.pdf', '/lm/c1046.pdf', 'INTERNE', 1),
(1047, 1047, 1047, NOW(), '/cv/c1047.pdf', '/lm/c1047.pdf', 'COOPTATION', 2),
(1048, 1048, 1048, NOW(), '/cv/c1048.pdf', '/lm/c1048.pdf', 'EXTERNE', 3),
(1049, 1049, 1049, NOW(), '/cv/c1049.pdf', '/lm/c1049.pdf', 'EXTERNE', 4),
(1050, 1050, 1050, NOW(), '/cv/c1050.pdf', '/lm/c1050.pdf', 'INTERNE', 5),
(1051, 1051, 1051, NOW(), '/cv/c1051.pdf', '/lm/c1051.pdf', 'COOPTATION', 6)
ON CONFLICT (id_candidature) DO NOTHING;

-- ----------------------------------------------------------------------------
-- 4. Insertion de 50 Propositions d'Embauche (IDs 1002 à 1051)
-- ----------------------------------------------------------------------------
INSERT INTO proposition_embauche (id_proposition, statut, id_candidature, date_proposition,
                                 remuneration_proposee, date_embauche_proposee, date_reponse, reponse)
VALUES 
(1002, 3, 1002, CURRENT_DATE, 52000.00, CURRENT_DATE + 20, NULL, 'En attente'),
(1003, 5, 1003, CURRENT_DATE - 5, 48000.00, CURRENT_DATE + 10, CURRENT_DATE - 2, 'Refusée'),
(1004, 4, 1004, CURRENT_DATE, 45000.00, CURRENT_DATE + 30, CURRENT_DATE, 'Acceptée'),
(1005, 3, 1005, CURRENT_DATE, 55000.00, CURRENT_DATE + 15, NULL, 'En attente'),
(1006, 4, 1006, CURRENT_DATE, 42000.00, CURRENT_DATE + 25, CURRENT_DATE, 'Acceptée'),
(1007, 5, 1007, CURRENT_DATE - 10, 39000.00, CURRENT_DATE + 5, CURRENT_DATE - 8, 'Refusée'),
(1008, 3, 1008, CURRENT_DATE, 60000.00, CURRENT_DATE + 30, NULL, 'En attente'),
(1009, 4, 1009, CURRENT_DATE, 47000.00, CURRENT_DATE + 15, CURRENT_DATE, 'Acceptée'),
(1010, 3, 1010, CURRENT_DATE, 41000.00, CURRENT_DATE + 20, NULL, 'En attente'),
(1011, 4, 1011, CURRENT_DATE, 51000.00, CURRENT_DATE + 10, CURRENT_DATE, 'Acceptée'),
(1012, 5, 1012, CURRENT_DATE - 3, 46000.00, CURRENT_DATE + 12, CURRENT_DATE - 1, 'Refusée'),
(1013, 3, 1013, CURRENT_DATE, 43000.00, CURRENT_DATE + 30, NULL, 'En attente'),
(1014, 4, 1014, CURRENT_DATE, 53000.00, CURRENT_DATE + 15, CURRENT_DATE, 'Acceptée'),
(1015, 3, 1015, CURRENT_DATE, 49000.00, CURRENT_DATE + 25, NULL, 'En attente'),
(1016, 4, 1016, CURRENT_DATE, 44000.00, CURRENT_DATE + 10, CURRENT_DATE, 'Acceptée'),
(1017, 5, 1017, CURRENT_DATE - 7, 58000.00, CURRENT_DATE + 20, CURRENT_DATE - 4, 'Refusée'),
(1018, 3, 1018, CURRENT_DATE, 46000.00, CURRENT_DATE + 30, NULL, 'En attente'),
(1019, 4, 1019, CURRENT_DATE, 38000.00, CURRENT_DATE + 15, CURRENT_DATE, 'Acceptée'),
(1020, 3, 1020, CURRENT_DATE, 50000.00, CURRENT_DATE + 20, NULL, 'En attente'),
(1021, 4, 1021, CURRENT_DATE, 52000.00, CURRENT_DATE + 25, CURRENT_DATE, 'Acceptée'),
(1022, 5, 1022, CURRENT_DATE - 2, 43000.00, CURRENT_DATE + 10, CURRENT_DATE, 'Refusée'),
(1023, 3, 1023, CURRENT_DATE, 45000.00, CURRENT_DATE + 30, NULL, 'En attente'),
(1024, 4, 1024, CURRENT_DATE, 40000.00, CURRENT_DATE + 15, CURRENT_DATE, 'Acceptée'),
(1025, 3, 1025, CURRENT_DATE, 48000.00, CURRENT_DATE + 20, NULL, 'En attente'),
(1026, 4, 1026, CURRENT_DATE, 49000.00, CURRENT_DATE + 25, CURRENT_DATE, 'Acceptée'),
(1027, 3, 1027, CURRENT_DATE, 62000.00, CURRENT_DATE + 30, NULL, 'En attente'),
(1028, 5, 1028, CURRENT_DATE - 4, 41000.00, CURRENT_DATE + 12, CURRENT_DATE - 2, 'Refusée'),
(1029, 4, 1029, CURRENT_DATE, 65000.00, CURRENT_DATE + 30, CURRENT_DATE, 'Acceptée'),
(1030, 3, 1030, CURRENT_DATE, 46000.00, CURRENT_DATE + 15, NULL, 'En attente'),
(1031, 4, 1031, CURRENT_DATE, 47000.00, CURRENT_DATE + 20, CURRENT_DATE, 'Acceptée'),
(1032, 3, 1032, CURRENT_DATE, 44000.00, CURRENT_DATE + 25, NULL, 'En attente'),
(1033, 4, 1033, CURRENT_DATE, 53000.00, CURRENT_DATE + 15, CURRENT_DATE, 'Acceptée'),
(1034, 5, 1034, CURRENT_DATE - 6, 39000.00, CURRENT_DATE + 10, CURRENT_DATE - 3, 'Refusée'),
(1035, 3, 1035, CURRENT_DATE, 50000.00, CURRENT_DATE + 30, NULL, 'En attente'),
(1036, 4, 1036, CURRENT_DATE, 54000.00, CURRENT_DATE + 20, CURRENT_DATE, 'Acceptée'),
(1037, 3, 1037, CURRENT_DATE, 75000.00, CURRENT_DATE + 45, NULL, 'En attente'),
(1038, 4, 1038, CURRENT_DATE, 43000.00, CURRENT_DATE + 15, CURRENT_DATE, 'Acceptée'),
(1039, 3, 1039, CURRENT_DATE, 49000.00, CURRENT_DATE + 25, NULL, 'En attente'),
(1040, 5, 1040, CURRENT_DATE - 8, 45000.00, CURRENT_DATE + 10, CURRENT_DATE - 5, 'Refusée'),
(1041, 4, 1041, CURRENT_DATE, 41000.00, CURRENT_DATE + 20, CURRENT_DATE, 'Acceptée'),
(1042, 3, 1042, CURRENT_DATE, 57000.00, CURRENT_DATE + 30, NULL, 'En attente'),
(1043, 4, 1043, CURRENT_DATE, 48000.00, CURRENT_DATE + 15, CURRENT_DATE, 'Acceptée'),
(1044, 3, 1044, CURRENT_DATE, 47000.00, CURRENT_DATE + 20, NULL, 'En attente'),
(1045, 4, 1045, CURRENT_DATE, 52000.00, CURRENT_DATE + 25, CURRENT_DATE, 'Acceptée'),
(1046, 3, 1046, CURRENT_DATE, 49000.00, CURRENT_DATE + 30, NULL, 'En attente'),
(1047, 5, 1047, CURRENT_DATE - 1, 38000.00, CURRENT_DATE + 10, CURRENT_DATE, 'Refusée'),
(1048, 4, 1048, CURRENT_DATE, 68000.00, CURRENT_DATE + 35, CURRENT_DATE, 'Acceptée'),
(1049, 3, 1049, CURRENT_DATE, 45000.00, CURRENT_DATE + 20, NULL, 'En attente'),
(1050, 4, 1050, CURRENT_DATE, 42000.00, CURRENT_DATE + 15, CURRENT_DATE, 'Acceptée'),
(1051, 3, 1051, CURRENT_DATE, 80000.00, CURRENT_DATE + 60, NULL, 'En attente')
ON CONFLICT (id_proposition) DO NOTHING;

-- ----------------------------------------------------------------------------
-- 5. Insertion de 50 Utilisateurs (IDs 1002 à 1051) avec mot de passe en commentaire
-- ----------------------------------------------------------------------------
INSERT INTO utilisateur (id_utilisateur, id_employe, identifiant, mot_de_passe_hash, role, actif, date_creation)
VALUES 
(1002, NULL, 'user_1002', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1002
(1003, NULL, 'user_1003', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1003
(1004, NULL, 'user_1004', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1004
(1005, NULL, 'user_1005', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1005
(1006, NULL, 'user_1006', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1006
(1007, NULL, 'user_1007', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1007
(1008, NULL, 'user_1008', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1008
(1009, NULL, 'user_1009', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1009
(1010, NULL, 'user_1010', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1010
(1011, NULL, 'user_1011', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1011
(1012, NULL, 'user_1012', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1012
(1013, NULL, 'user_1013', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1013
(1014, NULL, 'user_1014', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1014
(1015, NULL, 'user_1015', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1015
(1016, NULL, 'user_1016', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1016
(1017, NULL, 'user_1017', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1017
(1018, NULL, 'user_1018', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1018
(1019, NULL, 'user_1019', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1019
(1020, NULL, 'user_1020', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1020
(1021, NULL, 'user_1021', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1021
(1022, NULL, 'user_1022', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1022
(1023, NULL, 'user_1023', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1023
(1024, NULL, 'user_1024', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1024
(1025, NULL, 'user_1025', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1025
(1026, NULL, 'user_1026', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1026
(1027, NULL, 'user_1027', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1027
(1028, NULL, 'user_1028', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1028
(1029, NULL, 'user_1029', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1029
(1030, NULL, 'user_1030', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1030
(1031, NULL, 'user_1031', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1031
(1032, NULL, 'user_1032', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1032
(1033, NULL, 'user_1033', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1033
(1034, NULL, 'user_1034', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1034
(1035, NULL, 'user_1035', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1035
(1036, NULL, 'user_1036', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1036
(1037, NULL, 'user_1037', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1037
(1038, NULL, 'user_1038', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1038
(1039, NULL, 'user_1039', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1039
(1040, NULL, 'user_1040', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1040
(1041, NULL, 'user_1041', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1041
(1042, NULL, 'user_1042', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1042
(1043, NULL, 'user_1043', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1043
(1044, NULL, 'user_1044', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1044
(1045, NULL, 'user_1045', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1045
(1046, NULL, 'user_1046', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1046
(1047, NULL, 'user_1047', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1047
(1048, NULL, 'user_1048', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW()), -- mot_de_passe : password1048
(1049, NULL, 'user_1049', '$2y$10$abcdefghijklmnopqrstuv', 'rh', TRUE, NOW()), -- mot_de_passe : password1049
(1050, NULL, 'user_1050', '$2y$10$abcdefghijklmnopqrstuv', 'admin', TRUE, NOW()), -- mot_de_passe : password1050
(1051, NULL, 'user_1051', '$2y$10$abcdefghijklmnopqrstuv', 'manager', TRUE, NOW())  -- mot_de_passe : password1051
ON CONFLICT (id_utilisateur) DO NOTHING;

-- ----------------------------------------------------------------------------
-- Mise à jour des séquences PostgreSQL
-- ----------------------------------------------------------------------------
SELECT setval(pg_get_serial_sequence('candidat', 'id_candidat'),
              GREATEST((SELECT MAX(id_candidat) FROM candidat), 1));
SELECT setval(pg_get_serial_sequence('offre_emploi', 'id_offre'),
              GREATEST((SELECT MAX(id_offre) FROM offre_emploi), 1));
SELECT setval(pg_get_serial_sequence('candidature', 'id_candidature'),
              GREATEST((SELECT MAX(id_candidature) FROM candidature), 1));
SELECT setval(pg_get_serial_sequence('proposition_embauche', 'id_proposition'),
              GREATEST((SELECT MAX(id_proposition) FROM proposition_embauche), 1));
SELECT setval(pg_get_serial_sequence('utilisateur', 'id_utilisateur'),
              GREATEST((SELECT MAX(id_utilisateur) FROM utilisateur), 1));

COMMIT;