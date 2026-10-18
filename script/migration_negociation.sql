-- ============================================================================
-- Migration : négociation de la proposition d'embauche (revendications)
-- Base cible : gestion_rh (schéma existant, sans les objets de négociation)
-- Exécution   : psql -U postgres -d gestion_rh -f migration_negociation.sql
-- Idempotente : peut être relancée sans générer d'erreur.
-- ============================================================================

BEGIN;

-- 1. Nouvelle colonne sur proposition_embauche
ALTER TABLE proposition_embauche
    ADD COLUMN IF NOT EXISTS date_embauche_proposee date;

COMMENT ON COLUMN proposition_embauche.date_embauche_proposee
    IS 'Date d''embauche proposée, négociable via les revendications.';

-- 2. Référentiels
CREATE TABLE IF NOT EXISTS nature_revendication (
    id bigserial NOT NULL,
    designation varchar(255) NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS status_revendication (
    id bigserial NOT NULL,
    designation varchar(255) NOT NULL,
    PRIMARY KEY (id)
);

-- 3. Fil de revendications (contre-propositions)
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

-- 4. Historique des statuts de revendication
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

-- 5. Clés étrangères (ajoutées seulement si absentes)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'revendication_fk1') THEN
        ALTER TABLE revendication ADD CONSTRAINT revendication_fk1
            FOREIGN KEY (id_proposition) REFERENCES proposition_embauche(id_proposition);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'revendication_fk2') THEN
        ALTER TABLE revendication ADD CONSTRAINT revendication_fk2
            FOREIGN KEY (id_candidat) REFERENCES candidat(id_candidat);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'revendication_fk3') THEN
        ALTER TABLE revendication ADD CONSTRAINT revendication_fk3
            FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'revendication_fk4') THEN
        ALTER TABLE revendication ADD CONSTRAINT revendication_fk4
            FOREIGN KEY (nature_id) REFERENCES nature_revendication(id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'revendication_fk5') THEN
        ALTER TABLE revendication ADD CONSTRAINT revendication_fk5
            FOREIGN KEY (statut_id) REFERENCES status_revendication(id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'revendication_fk6') THEN
        ALTER TABLE revendication ADD CONSTRAINT revendication_fk6
            FOREIGN KEY (id_repondant) REFERENCES utilisateur(id_utilisateur);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'revendication_status_historique_fk1') THEN
        ALTER TABLE revendication_status_historique ADD CONSTRAINT revendication_status_historique_fk1
            FOREIGN KEY (id_revendication) REFERENCES revendication(id_revendication);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'revendication_status_historique_fk5') THEN
        ALTER TABLE revendication_status_historique ADD CONSTRAINT revendication_status_historique_fk5
            FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur);
    END IF;
END $$;

-- 6. Index
CREATE INDEX IF NOT EXISTS idx_revendication_proposition
    ON revendication USING btree (id_proposition);
CREATE INDEX IF NOT EXISTS idx_revendication_hist_date
    ON revendication_status_historique USING btree (id_revendication, date_changement);

-- 7. Commentaires
COMMENT ON TABLE revendication
    IS 'Revendications (contre-propositions) lors de la négociation d''une proposition d''embauche.';
COMMENT ON TABLE nature_revendication
    IS 'Référentiel des natures de revendication.';
COMMENT ON TABLE status_revendication
    IS 'Référentiel des statuts d''une revendication.';
COMMENT ON TABLE revendication_status_historique
    IS 'Historique des changements de statut pour revendication.';

-- 8. Données de référence (ajoutées seulement si absentes)
INSERT INTO status_proposition_embauche (designation)
SELECT 'En négociation'
WHERE NOT EXISTS (
    SELECT 1 FROM status_proposition_embauche WHERE designation = 'En négociation'
);

INSERT INTO nature_revendication (designation)
SELECT v.designation
FROM (VALUES
    ('Rémunération'),
    ('Date d''embauche'),
    ('Temps de travail'),
    ('Avantages'),
    ('Poste / Intitulé'),
    ('Autre')
) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM nature_revendication n WHERE n.designation = v.designation
);

INSERT INTO status_revendication (designation)
SELECT v.designation
FROM (VALUES
    ('Soumise'),
    ('En discussion'),
    ('Acceptée'),
    ('Refusée'),
    ('Retirée')
) AS v(designation)
WHERE NOT EXISTS (
    SELECT 1 FROM status_revendication s WHERE s.designation = v.designation
);

COMMIT;
