CREATE DATABASE gestion_rh;
\c gestion_rh

CREATE TABLE IF NOT EXISTS departement (
	id_departement bigserial NOT NULL,
	nom varchar(150) NOT NULL UNIQUE,
	description text,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_departement)
);
CREATE TABLE IF NOT EXISTS service (
	id_service bigserial NOT NULL,
	id_departement bigint NOT NULL,
	nom varchar(150) NOT NULL UNIQUE,
	description text,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_service)
);
CREATE TABLE IF NOT EXISTS poste (
	id_poste bigserial NOT NULL,
	id_service bigint NOT NULL,
	intitule varchar(200) NOT NULL UNIQUE,
	description text,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	rang_id bigint NOT NULL,
	PRIMARY KEY (id_poste)
);
CREATE TABLE IF NOT EXISTS grille_salariale (
	id_grille bigserial NOT NULL,
	id_poste bigint NOT NULL,
	rang bigint NOT NULL,
	salaire_min numeric(12,2) NOT NULL,
	salaire_max numeric(12,2) NOT NULL,
	date_debut date NOT NULL,
	date_fin date,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_grille)
);
CREATE TABLE IF NOT EXISTS demande_recrutement (
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
	PRIMARY KEY (id_demande)
);
CREATE TABLE IF NOT EXISTS validation (
	id_validation bigserial NOT NULL,
	id_demande bigint NOT NULL,
	id_validateur bigint NOT NULL,
	statut_id bigint NOT NULL,
	date_validation timestamp with time zone NOT NULL DEFAULT now(),
	commentaire text,
	PRIMARY KEY (id_validation)
);
CREATE TABLE IF NOT EXISTS offre_emploi (
	id_offre bigserial NOT NULL,
	statut bigint,
	id_demande bigint NOT NULL,
	titre varchar(200) NOT NULL UNIQUE,
	description text,
	date_publication date,
	date_cloture date,
	canal_diffusion varchar(120),
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_offre)
);
CREATE TABLE IF NOT EXISTS candidat (
	id_candidat bigserial NOT NULL,
	nom varchar(150) NOT NULL,
	prenom varchar(150) NOT NULL,
	email varchar(200) UNIQUE,
	telephone varchar(30),
	adresse text,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_candidat)
);
CREATE TABLE IF NOT EXISTS candidature (
	id_candidature bigserial NOT NULL,
	id_candidat bigint NOT NULL,
	id_offre bigint NOT NULL,
	date_candidature timestamp with time zone NOT NULL DEFAULT now(),
	cv varchar(255),
	lettre_motivation varchar(255),
	origine_candidature varchar(120),
	statut bigint NOT NULL,
	PRIMARY KEY (id_candidature)
);
CREATE TABLE IF NOT EXISTS entretien (
	id_entretien bigserial NOT NULL,
	id_candidature bigint NOT NULL,
	id_recruteur bigint NOT NULL,
	date_entretien timestamp with time zone NOT NULL,
	type_entretien bigint,
	compte_rendu text,
	statut bigint,
	PRIMARY KEY (id_entretien)
);
CREATE TABLE IF NOT EXISTS evaluation_candidat (
	id_evaluation bigserial NOT NULL,
	id_candidature bigint NOT NULL,
	id_evaluateur bigint NOT NULL,
	criteres text,
	note numeric(5,2),
	commentaire text,
	date_evaluation timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_evaluation)
);
CREATE TABLE IF NOT EXISTS proposition_embauche (
	id_proposition bigserial NOT NULL,
	statut bigint,
	id_candidature bigint NOT NULL,
	date_proposition date NOT NULL,
	remuneration_proposee numeric(12,2),
	date_embauche_proposee date,
	date_reponse date,
	reponse text,
	PRIMARY KEY (id_proposition)
);
-- Revendications (contre-propositions) formulées par le candidat ou l'entreprise
-- lors de la négociation d'une proposition d'embauche, avant l'acceptation.
CREATE TABLE IF NOT EXISTS revendication (
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
	PRIMARY KEY (id_revendication)
);
CREATE TABLE IF NOT EXISTS salarie (
	id_salarie bigserial NOT NULL,
	id_candidat bigint,
	matricule varchar(50) NOT NULL UNIQUE,
	nom varchar(150) NOT NULL,
	prenom varchar(150) NOT NULL,
	email varchar(200),
	telephone varchar(30),
	date_embauche date,
	statut bigint,
	PRIMARY KEY (id_salarie)
);
CREATE TABLE IF NOT EXISTS contrat (
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
	PRIMARY KEY (id_contrat)
);
CREATE TABLE IF NOT EXISTS affectation (
	id_affectation bigserial NOT NULL,
	id_salarie bigint NOT NULL,
	id_poste bigint NOT NULL,
	date_debut date NOT NULL,
	date_fin date,
	motif text,
	PRIMARY KEY (id_affectation)
);
CREATE TABLE IF NOT EXISTS document (
	id_document bigserial NOT NULL,
	id_salarie bigint NOT NULL,
	type_document bigint NOT NULL,
	nom_fichier varchar(255),
	chemin_fichier varchar(500) NOT NULL,
	date_depot timestamp with time zone NOT NULL DEFAULT now(),
	PRIMARY KEY (id_document)
);
CREATE TABLE IF NOT EXISTS onboarding (
	id_onboarding bigserial NOT NULL,
	statut bigint,
	id_salarie bigint NOT NULL,
	date_debut date NOT NULL,
	date_fin date,
	PRIMARY KEY (id_onboarding)
);
CREATE TABLE IF NOT EXISTS onboarding_tache (
	id_tache bigserial NOT NULL,
	statut bigint,
	id_onboarding bigint NOT NULL,
	intitule varchar(200) NOT NULL,
	description text,
	responsable varchar(150),
	date_echeance date,
	date_realisation timestamp with time zone,
	PRIMARY KEY (id_tache)
);
CREATE TABLE IF NOT EXISTS utilisateur (
	id_utilisateur bigserial NOT NULL,
	id_employe bigint,
	identifiant varchar(120) NOT NULL UNIQUE,
	mot_de_passe_hash varchar(255) NOT NULL,
	role varchar(80) NOT NULL,
	actif boolean NOT NULL DEFAULT true,
	date_creation timestamp with time zone NOT NULL DEFAULT now(),
	derniere_connexion timestamp with time zone,
	PRIMARY KEY (id_utilisateur)
);
CREATE TABLE IF NOT EXISTS journal_audit (
	id_audit bigserial NOT NULL,
	id_utilisateur bigint NOT NULL,
	action bigint NOT NULL,
	entite varchar(120) NOT NULL,
	id_entite varchar(60) NOT NULL,
	date_action timestamp with time zone NOT NULL DEFAULT now(),
	ancienne_valeur text,
	nouvelle_valeur text,
	details text,
	adresse_ip varchar(45),
	PRIMARY KEY (id_audit)
);
CREATE TABLE IF NOT EXISTS type_contrat (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL UNIQUE,
	PRIMARY KEY (id)
);
CREATE TABLE IF NOT EXISTS employe (
	id bigserial NOT NULL,
	nom varchar(255) NOT NULL,
	date_de_naissance date NOT NULL,
	poste_id bigint NOT NULL,
	id_salarie bigint NOT NULL,
	PRIMARY KEY (id)
);
CREATE TABLE IF NOT EXISTS status (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL UNIQUE,
	PRIMARY KEY (id)
);
CREATE TABLE IF NOT EXISTS rang (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);
CREATE TABLE IF NOT EXISTS status_candidature (
	id bigserial NOT NULL,
	designation text NOT NULL,
	PRIMARY KEY (id)
);
-- Historique des changements de statut pour demande_recrutement.
CREATE TABLE IF NOT EXISTS demande_recrutement_status_historique (
	id_historique bigserial NOT NULL,
	id_demande bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique)
);
-- Historique des changements de statut pour candidature.
CREATE TABLE IF NOT EXISTS candidature_status_historique (
	id_historique bigserial NOT NULL,
	id_candidature bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique)
);
-- Référentiel des statuts pour offre_emploi.
CREATE TABLE IF NOT EXISTS status_offre_emploi (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);
-- Référentiel des statuts pour entretien.
CREATE TABLE IF NOT EXISTS status_entretien (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);
-- Référentiel des statuts pour proposition_embauche.
CREATE TABLE IF NOT EXISTS status_proposition_embauche (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);
-- Référentiel des natures de revendication (objet de la négociation).
CREATE TABLE IF NOT EXISTS nature_revendication (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);
-- Référentiel des statuts d'une revendication.
CREATE TABLE IF NOT EXISTS status_revendication (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);
-- Référentiel des statuts pour salarie.
CREATE TABLE IF NOT EXISTS status_salarie (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);
-- Référentiel des statuts pour contrat.
CREATE TABLE IF NOT EXISTS status_contrat (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);
-- Référentiel des statuts pour onboarding.
CREATE TABLE IF NOT EXISTS status_onboarding (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);
-- Référentiel des statuts pour onboarding_tache.
CREATE TABLE IF NOT EXISTS status_onboarding_tache (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);
-- Historique des changements de statut pour offre_emploi.
CREATE TABLE IF NOT EXISTS offre_emploi_status_historique (
	id_historique bigserial NOT NULL,
	id_offre bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique)
);
-- Historique des changements de statut pour entretien.
CREATE TABLE IF NOT EXISTS entretien_status_historique (
	id_historique bigserial NOT NULL,
	id_entretien bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique)
);
-- Historique des changements de statut pour proposition_embauche.
CREATE TABLE IF NOT EXISTS proposition_embauche_status_historique (
	id_historique bigserial NOT NULL,
	id_proposition bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique)
);
-- Historique des changements de statut pour revendication.
CREATE TABLE IF NOT EXISTS revendication_status_historique (
	id_historique bigserial NOT NULL,
	id_revendication bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique)
);
-- Historique des changements de statut pour salarie.
CREATE TABLE IF NOT EXISTS salarie_status_historique (
	id_historique bigserial NOT NULL,
	id_salarie bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique)
);
-- Historique des changements de statut pour contrat.
CREATE TABLE IF NOT EXISTS contrat_status_historique (
	id_historique bigserial NOT NULL,
	id_contrat bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique)
);
-- Historique des changements de statut pour onboarding.
CREATE TABLE IF NOT EXISTS onboarding_status_historique (
	id_historique bigserial NOT NULL,
	id_onboarding bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique)
);
-- Historique des changements de statut pour onboarding_tache.
CREATE TABLE IF NOT EXISTS onboarding_tache_status_historique (
	id_historique bigserial NOT NULL,
	id_tache bigint NOT NULL,
	statut_ancien bigint NOT NULL,
	statut_nouveau bigint NOT NULL,
	date_changement timestamp with time zone NOT NULL,
	id_utilisateur bigint,
	commentaire text,
	PRIMARY KEY (id_historique)
);
CREATE TABLE IF NOT EXISTS action (
	id bigserial NOT NULL,
	action text NOT NULL UNIQUE,
	PRIMARY KEY (id)
);
-- Référentiel des types d'entretien.
CREATE TABLE IF NOT EXISTS type_entrentien (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL,
	PRIMARY KEY (id)
);
CREATE TABLE IF NOT EXISTS type_document (
	id bigserial NOT NULL,
	designation varchar(255) NOT NULL UNIQUE,
	PRIMARY KEY (id)
);
ALTER TABLE service ADD CONSTRAINT service_fk1 FOREIGN KEY (id_departement) REFERENCES departement(id_departement);
ALTER TABLE poste ADD CONSTRAINT poste_fk1 FOREIGN KEY (id_service) REFERENCES service(id_service);
ALTER TABLE poste ADD CONSTRAINT poste_fk5 FOREIGN KEY (rang_id) REFERENCES rang(id);
ALTER TABLE grille_salariale ADD CONSTRAINT grille_salariale_fk1 FOREIGN KEY (id_poste) REFERENCES poste(id_poste);
ALTER TABLE grille_salariale ADD CONSTRAINT grille_salariale_fk2 FOREIGN KEY (rang) REFERENCES rang(id);
ALTER TABLE demande_recrutement ADD CONSTRAINT demande_recrutement_fk1 FOREIGN KEY (id_poste) REFERENCES poste(id_poste);
ALTER TABLE demande_recrutement ADD CONSTRAINT demande_recrutement_fk2 FOREIGN KEY (id_demandeur) REFERENCES employe(id);
ALTER TABLE demande_recrutement ADD CONSTRAINT demande_recrutement_fk6 FOREIGN KEY (type_contrat) REFERENCES type_contrat(id);
ALTER TABLE demande_recrutement ADD CONSTRAINT demande_recrutement_fk12 FOREIGN KEY (statut) REFERENCES status(id);
ALTER TABLE validation ADD CONSTRAINT validation_fk1 FOREIGN KEY (id_demande) REFERENCES demande_recrutement(id_demande);
ALTER TABLE validation ADD CONSTRAINT validation_fk2 FOREIGN KEY (id_validateur) REFERENCES employe(id);
ALTER TABLE validation ADD CONSTRAINT validation_fk3 FOREIGN KEY (statut_id) REFERENCES status(id);
ALTER TABLE offre_emploi ADD CONSTRAINT offre_emploi_fk0 FOREIGN KEY (statut) REFERENCES status_offre_emploi(id);
ALTER TABLE offre_emploi ADD CONSTRAINT offre_emploi_fk2 FOREIGN KEY (id_demande) REFERENCES demande_recrutement(id_demande);
ALTER TABLE candidature ADD CONSTRAINT candidature_fk1 FOREIGN KEY (id_candidat) REFERENCES candidat(id_candidat);
ALTER TABLE candidature ADD CONSTRAINT candidature_fk2 FOREIGN KEY (id_offre) REFERENCES offre_emploi(id_offre);
ALTER TABLE candidature ADD CONSTRAINT candidature_fk7 FOREIGN KEY (statut) REFERENCES status_candidature(id);
ALTER TABLE entretien ADD CONSTRAINT entretien_fk1 FOREIGN KEY (id_candidature) REFERENCES candidature(id_candidature);
ALTER TABLE entretien ADD CONSTRAINT entretien_fk2 FOREIGN KEY (id_recruteur) REFERENCES employe(id);
ALTER TABLE entretien ADD CONSTRAINT entretien_fk4 FOREIGN KEY (type_entretien) REFERENCES type_entrentien(id);
ALTER TABLE entretien ADD CONSTRAINT entretien_fk6 FOREIGN KEY (statut) REFERENCES status_entretien(id);
ALTER TABLE evaluation_candidat ADD CONSTRAINT evaluation_candidat_fk1 FOREIGN KEY (id_candidature) REFERENCES candidature(id_candidature);
ALTER TABLE evaluation_candidat ADD CONSTRAINT evaluation_candidat_fk2 FOREIGN KEY (id_evaluateur) REFERENCES employe(id);
ALTER TABLE proposition_embauche ADD CONSTRAINT proposition_embauche_fk0 FOREIGN KEY (statut) REFERENCES status_proposition_embauche(id);
ALTER TABLE proposition_embauche ADD CONSTRAINT proposition_embauche_fk2 FOREIGN KEY (id_candidature) REFERENCES candidature(id_candidature);
ALTER TABLE revendication ADD CONSTRAINT revendication_fk1 FOREIGN KEY (id_proposition) REFERENCES proposition_embauche(id_proposition);
ALTER TABLE revendication ADD CONSTRAINT revendication_fk2 FOREIGN KEY (id_candidat) REFERENCES candidat(id_candidat);
ALTER TABLE revendication ADD CONSTRAINT revendication_fk3 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
ALTER TABLE revendication ADD CONSTRAINT revendication_fk4 FOREIGN KEY (nature_id) REFERENCES nature_revendication(id);
ALTER TABLE revendication ADD CONSTRAINT revendication_fk5 FOREIGN KEY (statut_id) REFERENCES status_revendication(id);
ALTER TABLE revendication ADD CONSTRAINT revendication_fk6 FOREIGN KEY (id_repondant) REFERENCES utilisateur(id_utilisateur);
ALTER TABLE salarie ADD CONSTRAINT salarie_fk1 FOREIGN KEY (id_candidat) REFERENCES candidat(id_candidat);
ALTER TABLE salarie ADD CONSTRAINT salarie_fk8 FOREIGN KEY (statut) REFERENCES status_salarie(id);
ALTER TABLE contrat ADD CONSTRAINT contrat_fk1 FOREIGN KEY (id_salarie) REFERENCES salarie(id_salarie);
ALTER TABLE contrat ADD CONSTRAINT contrat_fk2 FOREIGN KEY (id_poste) REFERENCES poste(id_poste);
ALTER TABLE contrat ADD CONSTRAINT contrat_fk3 FOREIGN KEY (type_contrat_id) REFERENCES type_contrat(id);
ALTER TABLE contrat ADD CONSTRAINT contrat_fk10 FOREIGN KEY (statut) REFERENCES status_contrat(id);
ALTER TABLE affectation ADD CONSTRAINT affectation_fk1 FOREIGN KEY (id_salarie) REFERENCES salarie(id_salarie);
ALTER TABLE affectation ADD CONSTRAINT affectation_fk2 FOREIGN KEY (id_poste) REFERENCES poste(id_poste);
ALTER TABLE document ADD CONSTRAINT document_fk1 FOREIGN KEY (id_salarie) REFERENCES salarie(id_salarie);
ALTER TABLE document ADD CONSTRAINT document_fk2 FOREIGN KEY (type_document) REFERENCES type_document(id);
ALTER TABLE onboarding ADD CONSTRAINT onboarding_fk0 FOREIGN KEY (statut) REFERENCES status_onboarding(id);
ALTER TABLE onboarding ADD CONSTRAINT onboarding_fk2 FOREIGN KEY (id_salarie) REFERENCES salarie(id_salarie);
ALTER TABLE onboarding_tache ADD CONSTRAINT onboarding_tache_fk0 FOREIGN KEY (statut) REFERENCES status_onboarding_tache(id);
ALTER TABLE onboarding_tache ADD CONSTRAINT onboarding_tache_fk2 FOREIGN KEY (id_onboarding) REFERENCES onboarding(id_onboarding);
ALTER TABLE utilisateur ADD CONSTRAINT utilisateur_fk1 FOREIGN KEY (id_employe) REFERENCES employe(id);
ALTER TABLE journal_audit ADD CONSTRAINT journal_audit_fk1 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
ALTER TABLE journal_audit ADD CONSTRAINT journal_audit_fk2 FOREIGN KEY (action) REFERENCES action(id);
ALTER TABLE employe ADD CONSTRAINT employe_fk3 FOREIGN KEY (poste_id) REFERENCES poste(id_poste);
ALTER TABLE employe ADD CONSTRAINT employe_fk4 FOREIGN KEY (id_salarie) REFERENCES salarie(id_salarie);
ALTER TABLE demande_recrutement_status_historique ADD CONSTRAINT demande_recrutement_status_historique_fk1 FOREIGN KEY (id_demande) REFERENCES demande_recrutement(id_demande);
ALTER TABLE demande_recrutement_status_historique ADD CONSTRAINT demande_recrutement_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
ALTER TABLE candidature_status_historique ADD CONSTRAINT candidature_status_historique_fk1 FOREIGN KEY (id_candidature) REFERENCES candidature(id_candidature);
ALTER TABLE candidature_status_historique ADD CONSTRAINT candidature_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
ALTER TABLE offre_emploi_status_historique ADD CONSTRAINT offre_emploi_status_historique_fk1 FOREIGN KEY (id_offre) REFERENCES offre_emploi(id_offre);
ALTER TABLE offre_emploi_status_historique ADD CONSTRAINT offre_emploi_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
ALTER TABLE entretien_status_historique ADD CONSTRAINT entretien_status_historique_fk1 FOREIGN KEY (id_entretien) REFERENCES entretien(id_entretien);
ALTER TABLE entretien_status_historique ADD CONSTRAINT entretien_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
ALTER TABLE proposition_embauche_status_historique ADD CONSTRAINT proposition_embauche_status_historique_fk1 FOREIGN KEY (id_proposition) REFERENCES proposition_embauche(id_proposition);
ALTER TABLE proposition_embauche_status_historique ADD CONSTRAINT proposition_embauche_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
ALTER TABLE revendication_status_historique ADD CONSTRAINT revendication_status_historique_fk1 FOREIGN KEY (id_revendication) REFERENCES revendication(id_revendication);
ALTER TABLE revendication_status_historique ADD CONSTRAINT revendication_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
ALTER TABLE salarie_status_historique ADD CONSTRAINT salarie_status_historique_fk1 FOREIGN KEY (id_salarie) REFERENCES salarie(id_salarie);
ALTER TABLE salarie_status_historique ADD CONSTRAINT salarie_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
ALTER TABLE contrat_status_historique ADD CONSTRAINT contrat_status_historique_fk1 FOREIGN KEY (id_contrat) REFERENCES contrat(id_contrat);
ALTER TABLE contrat_status_historique ADD CONSTRAINT contrat_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
ALTER TABLE onboarding_status_historique ADD CONSTRAINT onboarding_status_historique_fk1 FOREIGN KEY (id_onboarding) REFERENCES onboarding(id_onboarding);
ALTER TABLE onboarding_status_historique ADD CONSTRAINT onboarding_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
ALTER TABLE onboarding_tache_status_historique ADD CONSTRAINT onboarding_tache_status_historique_fk1 FOREIGN KEY (id_tache) REFERENCES onboarding_tache(id_tache);
ALTER TABLE onboarding_tache_status_historique ADD CONSTRAINT onboarding_tache_status_historique_fk5 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
CREATE INDEX idx_drs_hist_demande_date ON demande_recrutement_status_historique USING btree (id_demande, date_changement);
CREATE INDEX idx_cand_hist_candidature_date ON candidature_status_historique USING btree (id_candidature, date_changement);
CREATE INDEX idx_offre_hist_demande_date ON offre_emploi_status_historique USING btree (id_offre, date_changement);
CREATE INDEX idx_entretien_hist_date ON entretien_status_historique USING btree (id_entretien, date_changement);
CREATE INDEX idx_prop_embauche_hist_date ON proposition_embauche_status_historique USING btree (id_proposition, date_changement);
CREATE INDEX idx_revendication_hist_date ON revendication_status_historique USING btree (id_revendication, date_changement);
CREATE INDEX idx_revendication_proposition ON revendication USING btree (id_proposition);
CREATE INDEX idx_salarie_hist_date ON salarie_status_historique USING btree (id_salarie, date_changement);
CREATE INDEX idx_contrat_hist_date ON contrat_status_historique USING btree (id_contrat, date_changement);
CREATE INDEX idx_onboarding_hist_date ON onboarding_status_historique USING btree (id_onboarding, date_changement);
CREATE INDEX idx_onboarding_tache_hist_date ON onboarding_tache_status_historique USING btree (id_tache, date_changement);
COMMENT ON TABLE demande_recrutement_status_historique IS 'Historique des changements de statut pour demande_recrutement.';
COMMENT ON TABLE candidature_status_historique IS 'Historique des changements de statut pour candidature.';
COMMENT ON TABLE status_offre_emploi IS 'Référentiel des statuts pour offre_emploi.';
COMMENT ON TABLE status_entretien IS 'Référentiel des statuts pour entretien.';
COMMENT ON TABLE status_proposition_embauche IS 'Référentiel des statuts pour proposition_embauche.';
COMMENT ON TABLE status_salarie IS 'Référentiel des statuts pour salarie.';
COMMENT ON TABLE status_contrat IS 'Référentiel des statuts pour contrat.';
COMMENT ON TABLE status_onboarding IS 'Référentiel des statuts pour onboarding.';
COMMENT ON TABLE status_onboarding_tache IS 'Référentiel des statuts pour onboarding_tache.';
COMMENT ON TABLE offre_emploi_status_historique IS 'Historique des changements de statut pour offre_emploi.';
COMMENT ON TABLE entretien_status_historique IS 'Historique des changements de statut pour entretien.';
COMMENT ON TABLE proposition_embauche_status_historique IS 'Historique des changements de statut pour proposition_embauche.';
COMMENT ON TABLE revendication IS 'Revendications (contre-propositions) lors de la négociation d''une proposition d''embauche.';
COMMENT ON TABLE nature_revendication IS 'Référentiel des natures de revendication.';
COMMENT ON TABLE status_revendication IS 'Référentiel des statuts d''une revendication.';
COMMENT ON TABLE revendication_status_historique IS 'Historique des changements de statut pour revendication.';
COMMENT ON TABLE salarie_status_historique IS 'Historique des changements de statut pour salarie.';
COMMENT ON TABLE contrat_status_historique IS 'Historique des changements de statut pour contrat.';
COMMENT ON TABLE onboarding_status_historique IS 'Historique des changements de statut pour onboarding.';
COMMENT ON TABLE onboarding_tache_status_historique IS 'Historique des changements de statut pour onboarding_tache.';
COMMENT ON TABLE type_entrentien IS 'Référentiel des types d''entretien.';
