-- ============================================================================
-- Migration : ajout de la table stage (pour la déduction d'essai)
-- Base cible : gestion_rh
-- Idempotente
-- ============================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS stage (
    id_stage        bigserial   PRIMARY KEY,
    id_salarie      bigint      NOT NULL,
    date_debut      date        NOT NULL,
    date_fin        date        NOT NULL,
    entreprise      varchar(200),
    commentaire     text,
    date_creation   timestamp with time zone NOT NULL DEFAULT now()
);

ALTER TABLE stage
    ADD CONSTRAINT stage_fk1
    FOREIGN KEY (id_salarie) REFERENCES salarie(id_salarie)
    ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_stage_salarie ON stage (id_salarie);

COMMENT ON TABLE stage IS 'Périodes de stage antérieures du salarié (déduction d''essai RH.md §III).';

COMMIT;