CREATE TABLE IF NOT EXISTS temps_heures_saisies (
 id bigserial PRIMARY KEY, id_salarie bigint NOT NULL REFERENCES salarie(id_salarie), jour date NOT NULL,
 minutes integer NOT NULL CHECK (minutes > 0 AND minutes <= 1440), commentaire text NOT NULL DEFAULT '',
 UNIQUE (id_salarie, jour)
);
