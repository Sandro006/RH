-- Schéma adapté pour importation dans DrawDB.
-- Les blocs PostgreSQL DO $$, transactions, index et commentaires ont été retirés.
-- Les clés étrangères ont été intégrées directement aux définitions des tables.
-- Les tables sont réordonnées pour que chaque REFERENCES pointe vers une table
-- déjà créée : le script est donc aussi exécutable directement dans PostgreSQL.

CREATE TABLE departement (
    id_departement bigserial NOT NULL,
	nom varchar(150) NOT NULL UNIQUE,
	description text,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_departement)
);

CREATE TABLE service (
    id_service bigserial NOT NULL,
	id_departement bigint NOT NULL,
	nom varchar(150) NOT NULL UNIQUE,
	description text,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_service),
    CONSTRAINT service_fk1 FOREIGN KEY (id_departement) REFERENCES departement (id_departement)
);

CREATE TABLE candidat (
    id_candidat bigserial NOT NULL,
	nom varchar(150) NOT NULL,
	prenom varchar(150) NOT NULL,
	email varchar(200) UNIQUE,
	telephone varchar(30),
	adresse text,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_candidat)
);

CREATE TABLE type_contrat (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL UNIQUE,
	PRIMARY KEY (id)
);

CREATE TABLE status (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL UNIQUE,
	PRIMARY KEY (id)
);

CREATE TABLE rang (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE poste (
    id_poste bigserial NOT NULL,
	id_service bigint NOT NULL,
	intitule varchar(200) NOT NULL UNIQUE,
	description text,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	rang_id bigint NOT NULL,
	PRIMARY KEY (id_poste),
    CONSTRAINT poste_fk1 FOREIGN KEY (id_service) REFERENCES service (id_service),
    CONSTRAINT poste_fk5 FOREIGN KEY (rang_id) REFERENCES rang (id)
);

CREATE TABLE grille_salariale (
    id_grille bigserial NOT NULL,
	id_poste bigint NOT NULL,
	rang bigint NOT NULL,
	salaire_min numeric(12,2) NOT NULL,
	salaire_max numeric(12,2) NOT NULL,
	date_debut date NOT NULL,
	date_fin date,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_grille),
    CONSTRAINT grille_salariale_fk1 FOREIGN KEY (id_poste) REFERENCES poste (id_poste),
    CONSTRAINT grille_salariale_fk2 FOREIGN KEY (rang) REFERENCES rang (id)
);

CREATE TABLE status_candidature (
    id bigserial NOT NULL,
	designation text NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE status_offre_emploi (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE status_entretien (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE status_proposition_embauche (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE nature_revendication (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE status_revendication (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE status_salarie (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE salarie (
    id_salarie bigserial NOT NULL,
	id_candidat bigint,
	matricule varchar(50) NOT NULL UNIQUE,
	nom varchar(150) NOT NULL,
	prenom varchar(150) NOT NULL,
	email varchar(200),
	telephone varchar(30),
	date_embauche date,
	statut bigint,
	PRIMARY KEY (id_salarie),
    CONSTRAINT salarie_fk1 FOREIGN KEY (id_candidat) REFERENCES candidat (id_candidat),
    CONSTRAINT salarie_fk8 FOREIGN KEY (statut) REFERENCES status_salarie (id)
);

CREATE TABLE affectation (
    id_affectation bigserial NOT NULL,
	id_salarie bigint NOT NULL,
	id_poste bigint NOT NULL,
	date_debut date NOT NULL,
	date_fin date,
	motif text,
	PRIMARY KEY (id_affectation),
    CONSTRAINT affectation_fk1 FOREIGN KEY (id_salarie) REFERENCES salarie (id_salarie),
    CONSTRAINT affectation_fk2 FOREIGN KEY (id_poste) REFERENCES poste (id_poste)
);

CREATE TABLE employe (
    id bigserial NOT NULL,
	nom varchar(255) NOT NULL,
	date_de_naissance date NOT NULL,
	poste_id bigint NOT NULL,
	id_salarie bigint NOT NULL,
	PRIMARY KEY (id),
    CONSTRAINT employe_fk3 FOREIGN KEY (poste_id) REFERENCES poste (id_poste),
    CONSTRAINT employe_fk4 FOREIGN KEY (id_salarie) REFERENCES salarie (id_salarie)
);

CREATE TABLE demande_recrutement (
    id_demande bigserial NOT NULL,
	id_poste bigint NOT NULL,
	id_demandeur bigint NOT NULL,
	motif text NOT NULL,
	id_profil integer,
	nom_personne_remplacee varchar(200),
	type_contrat bigint NOT NULL,
	duree_contrat varchar(80),
	missions text,
	date_souhaitee date,
	remuneration_min numeric(12,2),
	remuneration_max numeric(12,2),
	statut bigint NOT NULL,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_demande),
    CONSTRAINT demande_recrutement_fk1 FOREIGN KEY (id_poste) REFERENCES poste (id_poste),
    CONSTRAINT demande_recrutement_fk2 FOREIGN KEY (id_demandeur) REFERENCES employe (id),
    CONSTRAINT demande_recrutement_fk6 FOREIGN KEY (type_contrat) REFERENCES type_contrat (id),
    CONSTRAINT demande_recrutement_fk12 FOREIGN KEY (statut) REFERENCES status (id)
);

CREATE TABLE validation (
    id_validation bigserial NOT NULL,
	id_demande bigint NOT NULL,
	id_validateur bigint NOT NULL,
	statut_id bigint NOT NULL,
	date_validation timestamp with time zone NOT NULL DEFAULT now(),
	commentaire text,
	PRIMARY KEY (id_validation),
    CONSTRAINT validation_fk1 FOREIGN KEY (id_demande) REFERENCES demande_recrutement (id_demande),
    CONSTRAINT validation_fk2 FOREIGN KEY (id_validateur) REFERENCES employe (id),
    CONSTRAINT validation_fk3 FOREIGN KEY (statut_id) REFERENCES status (id)
);

CREATE TABLE offre_emploi (
    id_offre bigserial NOT NULL,
	statut bigint,
	id_demande bigint NOT NULL,
	titre varchar(200) NOT NULL UNIQUE,
	description text,
	date_publication date,
	date_cloture date,
	canal_diffusion varchar(120),
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_offre),
    CONSTRAINT offre_emploi_fk0 FOREIGN KEY (statut) REFERENCES status_offre_emploi (id),
    CONSTRAINT offre_emploi_fk2 FOREIGN KEY (id_demande) REFERENCES demande_recrutement (id_demande)
);

CREATE TABLE candidature (
    id_candidature bigserial NOT NULL,
	id_candidat bigint NOT NULL,
	id_offre bigint NOT NULL,
	date_candidature timestamp with time zone NOT NULL DEFAULT now(),
	cv varchar(255),
	lettre_motivation varchar(255),
	origine_candidature varchar(120),
	statut bigint NOT NULL,
	PRIMARY KEY (id_candidature),
    CONSTRAINT candidature_fk1 FOREIGN KEY (id_candidat) REFERENCES candidat (id_candidat),
    CONSTRAINT candidature_fk2 FOREIGN KEY (id_offre) REFERENCES offre_emploi (id_offre),
    CONSTRAINT candidature_fk7 FOREIGN KEY (statut) REFERENCES status_candidature (id)
);

CREATE TABLE evaluation_candidat (
    id_evaluation bigserial NOT NULL,
	id_candidature bigint NOT NULL,
	id_evaluateur bigint NOT NULL,
	criteres text,
	note numeric(5,2),
	commentaire text,
	date_evaluation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_evaluation),
    CONSTRAINT evaluation_candidat_fk1 FOREIGN KEY (id_candidature) REFERENCES candidature (id_candidature),
    CONSTRAINT evaluation_candidat_fk2 FOREIGN KEY (id_evaluateur) REFERENCES employe (id)
);

CREATE TABLE proposition_embauche (
    id_proposition bigserial NOT NULL,
	statut bigint,
	id_candidature bigint NOT NULL,
	date_proposition date NOT NULL,
	remuneration_proposee numeric(12,2),
	date_embauche_proposee date,
	date_reponse date,
	reponse text,
	PRIMARY KEY (id_proposition),
    CONSTRAINT proposition_embauche_fk0 FOREIGN KEY (statut) REFERENCES status_proposition_embauche (id),
    CONSTRAINT proposition_embauche_fk2 FOREIGN KEY (id_candidature) REFERENCES candidature (id_candidature)
);

CREATE TABLE utilisateur (
    id_utilisateur bigserial NOT NULL,
	id_employe bigint,
	identifiant varchar(120) NOT NULL UNIQUE,
	mot_de_passe_hash varchar(255) NOT NULL,
	role varchar(80) NOT NULL,
	actif boolean NOT NULL DEFAULT true,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	derniere_connexion timestamp with time zone,
	tentatives_echec integer NOT NULL DEFAULT 0,
	verrouille_jusqu_a timestamp with time zone,
	doit_changer_mot_de_passe boolean NOT NULL DEFAULT false,
	PRIMARY KEY (id_utilisateur),
    CONSTRAINT utilisateur_fk1 FOREIGN KEY (id_employe) REFERENCES employe (id)
);

CREATE TABLE revendication (
    id_revendication bigserial NOT NULL,
	id_proposition bigint NOT NULL,
	sens varchar(20) NOT NULL,
	id_candidat bigint,
	id_utilisateur bigint,
	nature_id bigint NOT NULL,
	valeur_demandee varchar(255),
	commentaire text,
	statut_id bigint NOT NULL,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	id_repondant bigint,
	reponse text,
	date_reponse timestamp with time zone,
	PRIMARY KEY (id_revendication),
    CONSTRAINT revendication_fk1 FOREIGN KEY (id_proposition) REFERENCES proposition_embauche (id_proposition),
    CONSTRAINT revendication_fk2 FOREIGN KEY (id_candidat) REFERENCES candidat (id_candidat),
    CONSTRAINT revendication_fk3 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur),
    CONSTRAINT revendication_fk4 FOREIGN KEY (nature_id) REFERENCES nature_revendication (id),
    CONSTRAINT revendication_fk5 FOREIGN KEY (statut_id) REFERENCES status_revendication (id),
    CONSTRAINT revendication_fk6 FOREIGN KEY (id_repondant) REFERENCES utilisateur (id_utilisateur)
);

CREATE TABLE demande_recrutement_status_historique (
    id_historique bigserial NOT NULL,
	id_demande bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique),
    CONSTRAINT demande_recrutement_status_historique_fk1 FOREIGN KEY (id_demande) REFERENCES demande_recrutement (id_demande),
    CONSTRAINT demande_recrutement_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur)
);

CREATE TABLE candidature_status_historique (
    id_historique bigserial NOT NULL,
	id_candidature bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique),
    CONSTRAINT candidature_status_historique_fk1 FOREIGN KEY (id_candidature) REFERENCES candidature (id_candidature),
    CONSTRAINT candidature_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur)
);

CREATE TABLE status_contrat (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE contrat (
    id_contrat bigserial NOT NULL,
	id_salarie bigint NOT NULL,
	id_poste bigint NOT NULL,
	type_contrat_id bigint NOT NULL,
	date_debut date NOT NULL,
	date_fin date,
	salaire_base numeric(12,2),
	periode_essai_debut date,
	periode_essai_fin date,
	date_signature date,
	statut bigint,
	PRIMARY KEY (id_contrat),
    CONSTRAINT contrat_fk1 FOREIGN KEY (id_salarie) REFERENCES salarie (id_salarie),
    CONSTRAINT contrat_fk2 FOREIGN KEY (id_poste) REFERENCES poste (id_poste),
    CONSTRAINT contrat_fk3 FOREIGN KEY (type_contrat_id) REFERENCES type_contrat (id),
    CONSTRAINT contrat_fk10 FOREIGN KEY (statut) REFERENCES status_contrat (id)
);

CREATE TABLE status_onboarding (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE onboarding (
    id_onboarding bigserial NOT NULL,
	statut bigint,
	id_salarie bigint NOT NULL,
	date_debut date NOT NULL,
	date_fin date,
	PRIMARY KEY (id_onboarding),
    CONSTRAINT onboarding_fk0 FOREIGN KEY (statut) REFERENCES status_onboarding (id),
    CONSTRAINT onboarding_fk2 FOREIGN KEY (id_salarie) REFERENCES salarie (id_salarie)
);

CREATE TABLE status_onboarding_tache (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE onboarding_tache (
    id_tache bigserial NOT NULL,
	statut bigint,
	id_onboarding bigint NOT NULL,
	intitule varchar(200) NOT NULL,
	description text,
	responsable varchar(150),
	date_echeance date,
	date_realisation timestamp with time zone,
	PRIMARY KEY (id_tache),
    CONSTRAINT onboarding_tache_fk0 FOREIGN KEY (statut) REFERENCES status_onboarding_tache (id),
    CONSTRAINT onboarding_tache_fk2 FOREIGN KEY (id_onboarding) REFERENCES onboarding (id_onboarding)
);

CREATE TABLE offre_emploi_status_historique (
    id_historique bigserial NOT NULL,
	id_offre bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique),
    CONSTRAINT offre_emploi_status_historique_fk1 FOREIGN KEY (id_offre) REFERENCES offre_emploi (id_offre),
    CONSTRAINT offre_emploi_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur)
);

CREATE TABLE proposition_embauche_status_historique (
    id_historique bigserial NOT NULL,
	id_proposition bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique),
    CONSTRAINT proposition_embauche_status_historique_fk1 FOREIGN KEY (id_proposition) REFERENCES proposition_embauche (id_proposition),
    CONSTRAINT proposition_embauche_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur)
);

CREATE TABLE revendication_status_historique (
    id_historique bigserial NOT NULL,
	id_revendication bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique),
    CONSTRAINT revendication_status_historique_fk1 FOREIGN KEY (id_revendication) REFERENCES revendication (id_revendication),
    CONSTRAINT revendication_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur)
);

CREATE TABLE salarie_status_historique (
    id_historique bigserial NOT NULL,
	id_salarie bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique),
    CONSTRAINT salarie_status_historique_fk1 FOREIGN KEY (id_salarie) REFERENCES salarie (id_salarie),
    CONSTRAINT salarie_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur)
);

CREATE TABLE contrat_status_historique (
    id_historique bigserial NOT NULL,
	id_contrat bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique),
    CONSTRAINT contrat_status_historique_fk1 FOREIGN KEY (id_contrat) REFERENCES contrat (id_contrat),
    CONSTRAINT contrat_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur)
);

CREATE TABLE onboarding_status_historique (
    id_historique bigserial NOT NULL,
	id_onboarding bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique),
    CONSTRAINT onboarding_status_historique_fk1 FOREIGN KEY (id_onboarding) REFERENCES onboarding (id_onboarding),
    CONSTRAINT onboarding_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur)
);

CREATE TABLE onboarding_tache_status_historique (
    id_historique bigserial NOT NULL,
	id_tache bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique),
    CONSTRAINT onboarding_tache_status_historique_fk1 FOREIGN KEY (id_tache) REFERENCES onboarding_tache (id_tache),
    CONSTRAINT onboarding_tache_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur)
);

CREATE TABLE action (
    id bigserial NOT NULL,
	action text NOT NULL UNIQUE,
	PRIMARY KEY (id)
);

CREATE TABLE journal_audit (
    id_audit bigserial NOT NULL,
	id_utilisateur bigint,
	action bigint NOT NULL,
	entite varchar(120) NOT NULL,
	id_entite varchar(60),
	date_action timestamp with time zone NOT NULL DEFAULT now(),
	ancienne_valeur text,
	nouvelle_valeur text,
	details text,
	adresse_ip varchar(45),
	PRIMARY KEY (id_audit),
    CONSTRAINT journal_audit_fk1 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur),
    CONSTRAINT journal_audit_fk2 FOREIGN KEY (action) REFERENCES action (id)
);

CREATE TABLE type_entrentien (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE entretien (
    id_entretien bigserial NOT NULL,
	id_candidature bigint NOT NULL,
	id_recruteur bigint NOT NULL,
	date_entretien timestamp with time zone NOT NULL,
	type_entretien bigint,
	compte_rendu text,
	statut bigint,
	PRIMARY KEY (id_entretien),
    CONSTRAINT entretien_fk1 FOREIGN KEY (id_candidature) REFERENCES candidature (id_candidature),
    CONSTRAINT entretien_fk2 FOREIGN KEY (id_recruteur) REFERENCES employe (id),
    CONSTRAINT entretien_fk4 FOREIGN KEY (type_entretien) REFERENCES type_entrentien (id),
    CONSTRAINT entretien_fk6 FOREIGN KEY (statut) REFERENCES status_entretien (id)
);

CREATE TABLE entretien_status_historique (
    id_historique bigserial NOT NULL,
	id_entretien bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique),
    CONSTRAINT entretien_status_historique_fk1 FOREIGN KEY (id_entretien) REFERENCES entretien (id_entretien),
    CONSTRAINT entretien_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur)
);

CREATE TABLE type_document (
    id bigserial NOT NULL,
	designation varchar(255) NOT NULL UNIQUE,
	PRIMARY KEY (id)
);

CREATE TABLE document (
    id_document bigserial NOT NULL,
	id_salarie bigint NOT NULL,
	type_document bigint NOT NULL,
	nom_fichier varchar(255),
	chemin_fichier varchar(500) NOT NULL,
	date_depot timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_document),
    CONSTRAINT document_fk1 FOREIGN KEY (id_salarie) REFERENCES salarie (id_salarie),
    CONSTRAINT document_fk2 FOREIGN KEY (type_document) REFERENCES type_document (id)
);

