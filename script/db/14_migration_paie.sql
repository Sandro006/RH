-- ============================================================================
-- 14_migration_paie.sql — Bloc Paie (bulletins, éléments variables, cotisations)
--
-- Contenu (idempotent) :
--   • taux_cotisation     (paramétrage CNPS employé / patronal — à ajuster)
--   • element_paie        (primes / retenues manuelles par salarié et par mois)
--   • bulletin_paie       (snapshots brut / cotisations / net / patronal par mois)
--
-- Principes (comme Bloc 7) :
--   • PostgreSQL uniquement : tables + contraintes déclaratives + index + vues.
--     Aucune fonction SQL, aucun trigger. Toute la logique est côté PHP.
--   • Les jours payés/sans solde/injustifiés viennent de
--     v_variables_paie_conges (Bloc 7), lue par PaieService.
--
-- Prérequis (dans cet ordre) : 01, 02, 08, (11, 12 correctifs)
-- Utilisation : psql -U postgres -d gestion_rh -f script/db/14_migration_paie.sql
-- ============================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS taux_cotisation (
    id_taux_cotisation bigserial NOT NULL,
    code               varchar(40) NOT NULL UNIQUE,
    designation        varchar(150) NOT NULL,
    taux_employe       numeric(6,3) NOT NULL DEFAULT 0
        CONSTRAINT taux_cotisation_employe_check CHECK (taux_employe >= 0),
    taux_patronal      numeric(6,3) NOT NULL DEFAULT 0
        CONSTRAINT taux_cotisation_patronal_check CHECK (taux_patronal >= 0),
    actif              boolean NOT NULL DEFAULT true,
    PRIMARY KEY (id_taux_cotisation)
);

CREATE TABLE IF NOT EXISTS element_paie (
    id_element_paie  bigserial NOT NULL,
    id_salarie       bigint NOT NULL
        CONSTRAINT element_paie_fk_salarie
        REFERENCES salarie(id_salarie) ON DELETE RESTRICT,
    mois             date NOT NULL,
    type_element     varchar(20) NOT NULL
        CONSTRAINT element_paie_type_check CHECK (type_element IN (
            'PRIME', 'HEURE_SUP', 'RETTENUE', 'AVANTAGE', 'AUTRE'
        )),
    libelle          varchar(150) NOT NULL,
    montant          numeric(12,2) NOT NULL DEFAULT 0,
    commentaire      text,
    id_saisi_par     bigint
        CONSTRAINT element_paie_fk_saisi_par
        REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT,
    date_saisie      timestamp with time zone NOT NULL DEFAULT now(),
    PRIMARY KEY (id_element_paie)
);

CREATE TABLE IF NOT EXISTS bulletin_paie (
    id_bulletin        bigserial NOT NULL,
    id_salarie         bigint NOT NULL
        CONSTRAINT bulletin_fk_salarie
        REFERENCES salarie(id_salarie) ON DELETE RESTRICT,
    mois               date NOT NULL,
    salaire_base       numeric(12,2) NOT NULL DEFAULT 0,
    jours_ouvres_mois  integer NOT NULL DEFAULT 0,
    jours_paye         numeric(5,2) NOT NULL DEFAULT 0,
    jours_sans_solde   numeric(5,2) NOT NULL DEFAULT 0,
    jours_injustifies  numeric(5,2) NOT NULL DEFAULT 0,
    retenue_conges     numeric(12,2) NOT NULL DEFAULT 0,
    elements_paie      numeric(12,2) NOT NULL DEFAULT 0,
    brut               numeric(12,2) NOT NULL DEFAULT 0,
    cotisations_employe numeric(12,2) NOT NULL DEFAULT 0,
    net                numeric(12,2) NOT NULL DEFAULT 0,
    cotisations_patronal numeric(12,2) NOT NULL DEFAULT 0,
    taux_employe_applique numeric(6,3),
    taux_patronal_applique numeric(6,3),
    date_calcul         timestamp with time zone NOT NULL DEFAULT now(),
    id_valideur         bigint
        CONSTRAINT bulletin_fk_valideur
        REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT,
    date_validation     timestamp with time zone,
    PRIMARY KEY (id_bulletin),
    CONSTRAINT bulletin_uq UNIQUE (id_salarie, mois),
    CONSTRAINT bulletin_mois_check CHECK (mois = date_trunc('month', mois)::date),
    CONSTRAINT bulletin_jours_check CHECK (jours_sans_solde >= 0 AND jours_injustifies >= 0 AND jours_paye >= 0)
);

-- Index : bulletins par mois / par salarié ; éléments par salarié/mois.
CREATE INDEX IF NOT EXISTS idx_bulletin_mois ON bulletin_paie (mois);
CREATE INDEX IF NOT EXISTS idx_bulletin_salarie ON bulletin_paie (id_salarie, mois DESC);
CREATE INDEX IF NOT EXISTS idx_element_paie_salarie ON element_paie (id_salarie, mois);

-- ----------------------------------------------------------------------------
-- Paramétrage de démo (CNPS Madagascar — À AJUSTER selon la législation en vigueur)
-- Taux exprimés en % (base = brut payé).
-- ----------------------------------------------------------------------------
INSERT INTO taux_cotisation (code, designation, taux_employe, taux_patronal, actif)
SELECT v.code, v.designation, v.taux_employe, v.taux_patronal, true
FROM (VALUES
    ('CNPS',  'Cotisation CNPS', 1.000, 13.000)
) AS v(code, designation, taux_employe, taux_patronal)
WHERE NOT EXISTS (SELECT 1 FROM taux_cotisation t WHERE t.code = v.code);

COMMENT ON TABLE taux_cotisation IS 'Paramétrage des taux de cotisations (employé / patronal, en %).';
COMMENT ON TABLE element_paie IS 'Éléments variables de paie par salarié/mois (primes, heures sup, retenues manuelles…)';
COMMENT ON TABLE bulletin_paie IS 'Bulletins de paie générés (snapshot brut, cotisations, net, charges patronales).';

-- ----------------------------------------------------------------------------
-- Vue d'aide : bulletins par mois (base de la feuille de paie RH)
-- ----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_bulletins_mois AS
SELECT
    b.mois,
    COUNT(*) AS nb_bulletins,
    COALESCE(SUM(b.brut), 0) AS total_brut,
    COALESCE(SUM(b.cotisations_employe), 0) AS total_cotisations_employe,
    COALESCE(SUM(b.net), 0) AS total_net,
    COALESCE(SUM(b.cotisations_patronal), 0) AS total_cout_patronal,
    COUNT(*) FILTER (WHERE b.date_validation IS NOT NULL) AS nb_valides
FROM bulletin_paie b
GROUP BY b.mois;

COMMIT;
