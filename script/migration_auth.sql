BEGIN;

ALTER TABLE utilisateur
    ADD COLUMN IF NOT EXISTS tentatives_echec integer NOT NULL DEFAULT 0;

ALTER TABLE utilisateur
    ADD COLUMN IF NOT EXISTS verrouille_jusqu_a timestamp with time zone;

ALTER TABLE utilisateur
    ADD COLUMN IF NOT EXISTS doit_changer_mot_de_passe boolean NOT NULL DEFAULT false;

ALTER TABLE journal_audit
    ALTER COLUMN id_utilisateur DROP NOT NULL;

ALTER TABLE journal_audit
    ALTER COLUMN id_entite DROP NOT NULL;

CREATE INDEX IF NOT EXISTS idx_utilisateur_actif
    ON utilisateur (actif);

CREATE INDEX IF NOT EXISTS idx_journal_audit_utilisateur_date
    ON journal_audit (id_utilisateur, date_action);

COMMIT;
