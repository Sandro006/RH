-- Taux horaires versionnés par régime, sans modification des tables existantes.
CREATE TABLE IF NOT EXISTS temps_taux_supplementaire (
 id_taux bigserial PRIMARY KEY,
 regime varchar(100) NOT NULL,
 date_debut date NOT NULL,
 taux_horaire numeric(14,2) NOT NULL CHECK (taux_horaire >= 0),
 devise varchar(10) NOT NULL,
 CHECK (length(trim(regime)) > 0),
 CHECK (length(trim(devise)) > 0)
);
CREATE UNIQUE INDEX IF NOT EXISTS temps_taux_regime_date_unique ON temps_taux_supplementaire (lower(trim(regime)), date_debut);
