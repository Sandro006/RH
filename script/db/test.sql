ALTER TABLE salarie
ADD COLUMN date_naissance DATE,
ADD COLUMN adresse TEXT,
ADD COLUMN ville VARCHAR(100),
ADD COLUMN nationalite VARCHAR(100),
ADD COLUMN personne_urgence VARCHAR(150),
ADD COLUMN telephone_urgence VARCHAR(30);

ALTER TABLE document
ADD COLUMN numero_document VARCHAR(100),
ADD COLUMN date_expiration DATE,
ADD COLUMN date_verification DATE,
ADD COLUMN commentaire TEXT,
ADD COLUMN id_utilisateur_depot INTEGER;