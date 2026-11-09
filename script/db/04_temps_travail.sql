-- Ajout des tables du bloc 6, sans modifier les tables existantes. PostgreSQL.
CREATE TABLE IF NOT EXISTS temps_planning (
 id_planning bigserial PRIMARY KEY, id_salarie bigint NOT NULL REFERENCES salarie(id_salarie),
 jour date NOT NULL, debut time NOT NULL, fin time NOT NULL, pause_minutes integer NOT NULL DEFAULT 0,
 regime varchar(100) NOT NULL, CHECK (fin > debut), CHECK (pause_minutes >= 0 AND pause_minutes * interval '1 minute' < fin - debut), UNIQUE (id_salarie, jour)
);
CREATE TABLE IF NOT EXISTS temps_pointage (
 id_pointage bigserial PRIMARY KEY, id_salarie bigint NOT NULL REFERENCES salarie(id_salarie),
 instant timestamp NOT NULL, type varchar(6) NOT NULL CHECK (type IN ('entree', 'sortie')), UNIQUE (id_salarie, instant)
);
CREATE INDEX IF NOT EXISTS temps_pointage_salarie_instant ON temps_pointage(id_salarie, instant);
