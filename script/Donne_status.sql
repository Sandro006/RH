-- 1. Statuts des demandes de recrutement
INSERT INTO status (designation) VALUES
('En cours'),
('En contrôle RH'),
('En validation budgétaire'),
('En validation hiérarchique'),
('En consultation interne'),
('Validée'),
('Refusée'),
('À corriger'),
('Annulée');

-- 2. Statuts des candidatures
INSERT INTO status_candidature (designation) VALUES
('Reçue'),
('En présélection'),
('Présélectionnée'),
('Entretien prévu'),
('En évaluation'),
('Retenue'),
('Refusée'),
('Retirée');

-- 3. Statuts des offres d'emploi
INSERT INTO status_offre_emploi (designation) VALUES
('Brouillon'),
('Publiée'),
('Suspendue'),
('Clôturée'),
('Annulée');

-- 4. Statuts des entretiens
INSERT INTO status_entretien (designation) VALUES
('Planifié'),
('Confirmé'),
('Réalisé'),
('Reporté'),
('Annulé');

-- 5. Statuts des propositions d'embauche
INSERT INTO status_proposition_embauche (designation) VALUES
('À envoyer'),
('Envoyée'),
('Acceptée'),
('Refusée'),
('Expirée');

-- 6. Statuts des salariés
INSERT INTO status_salarie (designation) VALUES
('En attente d''embauche'),
('Actif'),
('En période d''essai'),
('Suspendu'),
('Parti');

-- 7. Statuts des contrats
INSERT INTO status_contrat (designation) VALUES
('En préparation'),
('En attente de signature'),
('Signé'),
('En cours'),
('Terminé'),
('Résilié');

-- 8. Statuts des dossiers d'intégration
INSERT INTO status_onboarding (designation) VALUES
('À démarrer'),
('En cours'),
('En attente'),
('Terminé'),
('Annulé');

-- 9. Statuts des tâches d'intégration
INSERT INTO status_onboarding_tache (designation) VALUES
('À faire'),
('En cours'),
('Bloquée'),
('Terminée'),
('Annulée');

INSERT INTO type_contrat (designation) VALUES
('CDI'),
('CDD'),
('Stage'),
('Interim'),
('Consultant'),
('Essai'),
('Alternance');

INSERT INTO type_entrentien (designation) VALUES
('Entretien d''embauche'),
('Entretien de fin de période d''essai'),
('Entretien d''intégration');

INSERT INTO type_document (designation) VALUES
('CV'),
('Contrat de travail'),
('Pièce d''identité'),
('Diplôme'),
('Attestation de travail'),
('Certificat médical'),
('RIB'),
('Attestation de formation'),
('Autre');

INSERT INTO rang (designation) VALUES
('Stagiaire'),
('Junior'),
('Intermédiaire'),
('Senior'),
('Chef d''équipe'),
('Manager'),
('Responsable'),
('Directeur'),
('Vice-président'),
('Président');

INSERT INTO action (action) VALUES
('INSERT'),
('UPDATE'),
('DELETE'),
('LOGIN'),
('LOGOUT'),
('CONSULTATION'),
('VALIDATION'),
('REFUS'),
('EXPORT'),
('UPLOAD'),
('DOWNLOAD'),
('CREATE');


INSERT INTO departement
    (id_departement, nom, description, date_creation)
VALUES
    (1, 'Ressources humaines', 'Gestion du personnel et du recrutement', CURRENT_DATE),
    (2, 'Informatique', 'Développement et maintenance des systèmes informatiques', CURRENT_DATE),
    (3, 'Finance', 'Gestion financière et comptabilité', CURRENT_DATE),
    (4, 'Commercial', 'Vente et relation client', CURRENT_DATE),
    (5, 'Marketing', 'Communication et stratégie marketing', CURRENT_DATE);


INSERT INTO service
    (id_service, id_departement, nom, description, date_creation)
VALUES
    (1, 1, 'Recrutement', 'Gestion des candidatures et des embauches', CURRENT_DATE),
    (2, 1, 'Administration du personnel', 'Gestion administrative des salariés', CURRENT_DATE),
    (3, 2, 'Développement', 'Développement des applications', CURRENT_DATE),
    (4, 2, 'Infrastructure', 'Gestion des serveurs et du réseau', CURRENT_DATE),
    (5, 3, 'Comptabilité', 'Gestion des opérations comptables', CURRENT_DATE),
    (6, 4, 'Ventes', 'Gestion des ventes et des clients', CURRENT_DATE),
    (7, 5, 'Communication', 'Gestion de la communication de l entreprise', CURRENT_DATE);

-- Donneés des postes surtout pour les Resources Humaines manager
INSERT INTO poste
    (id_poste, id_service, intitule, description, date_creation, rang_id)
VALUES
    (1, 1, 'Responsable Recrutement', 'Supervise les processus de recrutement', CURRENT_DATE, 7),
    (2, 1, 'Chargé de Recrutement', 'Gère les candidatures et les entretiens', CURRENT_DATE, 5),
    (3, 2, 'Responsable Administration du Personnel', 'Supervise la gestion administrative des salariés', CURRENT_DATE, 7),
    (4, 2, 'Assistant RH', 'Assiste dans la gestion administrative des salariés', CURRENT_DATE, 4),
    (5, 3, 'Développeur Senior', 'Développe et maintient les applications informatiques', CURRENT_DATE, 4),
    (6, 3, 'Développeur Junior', 'Assiste dans le développement des applications informatiques', CURRENT_DATE, 2),
    (7, 4, 'Administrateur Systèmes et Réseaux', 'Gère les serveurs et le réseau informatique', CURRENT_DATE, 5),
    (8, 5, 'Comptable Senior', 'Gère les opérations comptables complexes', CURRENT_DATE, 4),
    (9, 5, 'Comptable Junior', 'Assiste dans la gestion des opérations comptables', CURRENT_DATE, 2),
    (10, 6, 'Responsable Commercial', 'Supervise les activités commerciales et les ventes', CURRENT_DATE, 7),
    (11, 6, 'Commercial Terrain', 'Gère les relations avec les clients et effectue des ventes sur le terrain', CURRENT_DATE, 5),
    (12, 7, 'Responsable Communication', 'Supervise la communication interne et externe de l entreprise', CURRENT_DATE, 7),
    (13, 7, 'Chargé de Communication', 'Assiste dans la gestion de la communication de l entreprise', CURRENT_DATE, 5);

-- Remise à niveau des séquences (les identifiants ont été fournis explicitement ci-dessus).
SELECT setval(pg_get_serial_sequence('departement', 'id_departement'), (SELECT MAX(id_departement) FROM departement));
SELECT setval(pg_get_serial_sequence('service', 'id_service'), (SELECT MAX(id_service) FROM service));
SELECT setval(pg_get_serial_sequence('poste', 'id_poste'), (SELECT MAX(id_poste) FROM poste));
