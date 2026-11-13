-- ============================================================================
-- 08_migration_conges_bloc7.sql — Bloc 7 : Congés et absences
--
-- Contenu :
--   1. Tables de référence (types, statuts, jours fériés) + données de base
--   2. Tables métier (droits, soldes, demandes, absences, justificatifs,
--      mouvements de solde, délégations)
--   3. Contraintes déclaratives (PK, FK, UNIQUE, CHECK) + index
--   4. Vues (SQL en lecture seule) — section 5 du TODO
--
-- Principes (voir todo-bloc7-gestion-Congés.md) :
--   • PostgreSQL uniquement : tables + contraintes déclaratives + index + vues.
--     AUCUNE fonction SQL, AUCUN trigger.
--   • Toute la logique (calculs, contrôles, workflow, soldes, audit,
--     notifications) est côté application (CodeIgniter 4).
--
-- Adaptations au modèle existant de la base gestion_rh :
--   • Salarié      : salarie (id_salarie)
--   • Contrat      : contrat (id_contrat, id_salarie, id_poste, type_contrat_id)
--   • Type contrat : type_contrat (id, designation) — la colonne s'appelle
--     donc id_type_contrat ici, mais sa FK vise type_contrat(id), PAS
--     type_contrat(id_type_contrat) qui n'existe pas.
--   • Service      : service (id_service, id_departement),
--                    poste (id_poste, id_service, rang_id)
--   • Valideurs (id_manager_valideur, id_rh_valideur) -> salarie(id_salarie)
--     : ce sont des personnes, pas des comptes d'accès.
--   • id_saisi_par / id_validateur (justificatifs) -> utilisateur(id_utilisateur)
--     : ce sont des comptes d'accès, comme journal_audit.id_utilisateur et
--       document.id_utilisateur_depot.
--   • delegation_validation (id_manager, id_delegue) -> salarie(id_salarie).
--   • L'audit par transition est assuré par la table journal_audit existante
--     (colonne action -> action(id)) via AuditService::journaliser —
--     il n'y a donc PAS de table d'audit « congés » dédiée.
--
-- Migration de l'ancienne table demande_conge (script 07_migration_conge.sql) :
--   • La table créée par 01_migrations.sql / 07_migration_conge.sql
--     (id_conge, id_utilisateur, id_employe, type_conge varchar, statut varchar)
--     est CONSERVÉE et migrée sur place : elle ne peut pas être recréée comme
--     table fraîche sans perdre les demandes existantes.
--   • id_conge est RENOMMÉ en id_demande_conge (la séquence suit le rename).
--   • id_salarie est déduit de id_employe via employe, ou de id_utilisateur
--     via utilisateur -> employe -> employe.id_salarie.
--   • Le statut text est converti vers id_statut (voir la table
--     statut_demande_conge). Le type text est converti vers id_type_conge
--     UNIQUEMENT si la valeur correspond à un code du référentiel
--     (ANNUEL, MALADIE, ...). Les anciennes valeurs libres (« Congé payé »,
--     « Autre », ...) ne peuvent pas êtreConverties : ces lignes garderont
--     id_type_conge / id_salarie à NULL pour re-saisie RH.
--   • Les colonnes héritées (id_utilisateur, id_employe, type_conge,
--     justification, statut) passent NULLABLE pour que le nouveau workflow
--     (qui écrit id_salarie / id_type_conge / id_statut) puisse insérer des
--     lignes sans renseigner les anciennes colonnes.
--
-- POINTS À CONFIRMER AVANT PRODUCTION (décisions métier, non bloquantes) :
--   • Règle RG03 : jours ouvrés = lun-ven (CalenderService) ou lun-sam ?
--     Le script n'encode pas cette règle (pas de CHECK inter-jours) ; c'est
--     à décider dans CalendrierService.
--   • RG04 / seuils des vues : délai de prévenance par défaut ANNUEL = 7 j.
--     RG20 « en retard » = 7 j ; alertes de solde = disponible > 10 j ;
--     absences non justifiées = date_fin + 2 j (48 h maladie).
--     Ces seuils sont codés en dur dans les vues (commentés « SEUIL »).
--   • RG09 RG14 : le CHECK solde >= 0 autorise l'affichage d'un solde
--     négatif en cas de bug, mais l'INSERT/UPDATE qui le rendrait négatif
--     échouera. Si les avances de congés sont autorisées, SUPPRIMER ce CHECK.
--   • RG19 : taux de présence « bloquant ou non » — v_taux_presence_service
--     fournit la mesure, la décision de blocage est côté app.
--   • Jour_ferie.recurrent : date_ferie est la date d'une occurrence
--     (2026). Si recurrent = true, CalendrierService doit comparer
--     (mois, jour) et non la date complète.
--   • Données de base 2026 (jours fériés FR) et quotas (ANNuel 25 j, etc.)
--     sont des exemples : adapter votre règlement interne avant production.
--   • Il n'existe AUCUNE colonne lien manager dans salarie. Le « manager
--     direct » (RG15/RG16) doit être résolu côté app, par exemple via
--     delegation_validation, le rang du poste (poste -> rang, ex. Manager) ou
--     un ajout ultérieur salarie.id_manager_salarie. La vue
--     v_demandes_a_valider_manager retourne donc TOUTES les demandes
--     EN_ATTENTE_MANAGER (avec service et solde), le filtre « équipe du
--     manager » étant appliqué côté CodeIgniter.
--
-- Utilisation (base existante) : psql -U postgres -d gestion_rh -f script/db/08_migration_conges_bloc7.sql
-- Utilisation (base vierge)     : 01_migrations.sql PUIS 02_seeds.sql PUIS
--                                 ce script (les réf. existantes
--                                 type_contrat, salarie, contrat sont déjà là).
-- Idempotent : peut être relancé sans erreur ni doublon.
-- ============================================================================

BEGIN;

-- ============================================================================
-- 1. TABLES DE RÉFÉRENCE
-- ============================================================================

CREATE TABLE IF NOT EXISTS type_conge (
    id_type_conge           bigserial NOT NULL,
    code                    varchar(20) NOT NULL UNIQUE,
    libelle                 varchar(100) NOT NULL,
    est_paye                boolean NOT NULL DEFAULT true,
    deduit_solde            boolean NOT NULL DEFAULT false,
    justificatif_requis     boolean NOT NULL DEFAULT false,
    delai_prevenance_jours  integer NOT NULL DEFAULT 0
        CONSTRAINT type_conge_delai_check CHECK (delai_prevenance_jours >= 0),
    duree_max_jours         numeric(5,2),
    demi_journee_autorisee  boolean NOT NULL DEFAULT false,
    actif                   boolean NOT NULL DEFAULT true,
    PRIMARY KEY (id_type_conge),
    CONSTRAINT type_conge_duree_max_check
        CHECK (duree_max_jours IS NULL OR duree_max_jours > 0)
);

CREATE TABLE IF NOT EXISTS statut_demande_conge (
    id_statut bigserial NOT NULL,
    code      varchar(40) NOT NULL UNIQUE,
    libelle   varchar(100) NOT NULL,
    PRIMARY KEY (id_statut)
);

CREATE TABLE IF NOT EXISTS type_absence (
    id_type_absence bigserial NOT NULL,
    code            varchar(30) NOT NULL UNIQUE,
    libelle         varchar(100) NOT NULL,
    retenue_paie    boolean NOT NULL DEFAULT false,
    PRIMARY KEY (id_type_absence)
);

CREATE TABLE IF NOT EXISTS jour_ferie (
    id_jour_ferie bigserial NOT NULL,
    date_ferie    date NOT NULL,
    libelle       varchar(100) NOT NULL,
    recurrent     boolean NOT NULL DEFAULT false,
    PRIMARY KEY (id_jour_ferie),
    CONSTRAINT jour_ferie_uq UNIQUE (date_ferie, libelle)
);

-- Données de base : types de congé (préremplissage type_conge selon TODO §1.1).
-- delai_prevenance_jours : ANNUEL 7 j (à confirmer), malade / urgence 0.
INSERT INTO type_conge (code, libelle, est_paye, deduit_solde, justificatif_requis,
                        delai_prevenance_jours, duree_max_jours, demi_journee_autorisee, actif)
SELECT v.code, v.libelle, v.est_paye, v.deduit_solde, v.justificatif_requis,
       v.delai_prevenance_jours, v.duree_max_jours, v.demi_journee_autorisee, true
FROM (VALUES
    ('ANNUEL',      'Congé annuel payé',       true,  true,  false, 7,  NULL::numeric(5,2), true),
    ('MALADIE',     'Congé maladie',            true,  false, true,  0,  NULL::numeric(5,2), false),
    ('MATERNITE',   'Congé maternité',          true,  false, true,  0,  112::numeric(5,2),  false),
    ('PATERNITE',   'Congé paternité',          true,  false, true,  0,  14::numeric(5,2),   false),
    ('SANS_SOLDE',  'Congé sans solde',         false, false, false, 0,  NULL::numeric(5,2), true),
    ('FAMILIAL',    'Évènement familial',       true,  true,  true,  0,  3::numeric(5,2),    false),
    ('FORMATION',   'Congé formation',          true,  true,  true,  7,  NULL::numeric(5,2), true),
    ('RECUP',       'Récupération',             true,  true,  false, 0,  NULL::numeric(5,2), true)
) AS v(code, libelle, est_paye, deduit_solde, justificatif_requis, delai_prevenance_jours, duree_max_jours, demi_journee_autorisee)
WHERE NOT EXISTS (SELECT 1 FROM type_conge WHERE type_conge.code = v.code);

-- Statuts de demande (même logique que statut_* : id, code, libellé).
-- Valeurs workflow : EN_ATTENTE_MANAGER -> EN_ATTENTE_RH -> VALIDEE,
-- avec REFUSEE / ANNULEE comme sorties.
INSERT INTO statut_demande_conge (code, libelle)
SELECT v.code, v.libelle
FROM (VALUES
    ('EN_ATTENTE_MANAGER', 'En attente du manager'),
    ('EN_ATTENTE_RH',      'En attente de la RH'),
    ('VALIDEE',            'Validée'),
    ('REFUSEE',            'Refusée'),
    ('ANNULEE',            'Annulée')
) AS v(code, libelle)
WHERE NOT EXISTS (SELECT 1 FROM statut_demande_conge WHERE statut_demande_conge.code = v.code);

-- Types d'absence (planning/paie).
INSERT INTO type_absence (code, libelle, retenue_paie)
SELECT v.code, v.libelle, v.retenue_paie
FROM (VALUES
    ('CONGE',                'Congé',               false),
    ('MALADIE',              'Maladie',             false),
    ('ABSENCE_INJUSTIFIEE',  'Absence injustifiée', true),
    ('MISSION',              'Mission',             false),
    ('RETARD',               'Retard',              false),
    ('AUTORISATION',         'Autorisation',        false)
) AS v(code, libelle, retenue_paie)
WHERE NOT EXISTS (SELECT 1 FROM type_absence WHERE type_absence.code = v.code);

-- Jours fériés FR 2026 (EXEMPLE — adapter au pays et à l'année d'exploitation).
-- recurrent = true  : la date fixe revient chaque année (l'app compare mois/jour).
-- recurrent = false : date mobile (Pâques, Ascension, Pentecôte...).
INSERT INTO jour_ferie (date_ferie, libelle, recurrent)
SELECT v.date_ferie, v.libelle, v.recurrent
FROM (VALUES
    (DATE '2026-01-01', 'Jour de l''An',                 true),
    (DATE '2026-04-06', 'Lundi de Pâques',               false),
    (DATE '2026-05-01', 'Fête du Travail',               true),
    (DATE '2026-05-08', 'Victoire 1945',                 true),
    (DATE '2026-05-14', 'Ascension',                     false),
    (DATE '2026-05-25', 'Lundi de Pentecôte',            false),
    (DATE '2026-07-14', 'Fête Nationale',                true),
    (DATE '2026-08-15', 'Assomption',                    true),
    (DATE '2026-11-01', 'Toussaint',                     true),
    (DATE '2026-11-11', 'Armistice 1918',                true),
    (DATE '2026-12-25', 'Noël',                          true)
) AS v(date_ferie, libelle, recurrent)
WHERE NOT EXISTS (
    SELECT 1 FROM jour_ferie WHERE jour_ferie.date_ferie = v.date_ferie AND jour_ferie.libelle = v.libelle
);

-- ============================================================================
-- 2. TABLES MÉTIER
-- ============================================================================

CREATE TABLE IF NOT EXISTS droit_conge (
    id_droit_conge        bigserial NOT NULL,
    id_type_conge         bigint NOT NULL
        CONSTRAINT droit_conge_fk_type_conge
        REFERENCES type_conge(id_type_conge) ON DELETE RESTRICT,
    id_type_contrat       bigint
        CONSTRAINT droit_conge_fk_type_contrat
        REFERENCES type_contrat(id) ON DELETE RESTRICT,
    nb_jours_annuel       numeric(5,2) NOT NULL
        CONSTRAINT droit_conge_nb_jours_check CHECK (nb_jours_annuel > 0),
    acquisition_mensuelle numeric(4,2) NOT NULL DEFAULT 0
        CONSTRAINT droit_conge_acquisition_check CHECK (acquisition_mensuelle >= 0),
    anciennete_min_mois   integer NOT NULL DEFAULT 0
        CONSTRAINT droit_conge_anciennete_check CHECK (anciennete_min_mois >= 0),
    date_debut            date NOT NULL,
    date_fin              date,
    actif                 boolean NOT NULL DEFAULT true,
    PRIMARY KEY (id_droit_conge),
    CONSTRAINT droit_conge_dates_check
        CHECK (date_fin IS NULL OR date_fin >= date_debut)
);

CREATE TABLE IF NOT EXISTS solde_conge (
    id_solde_conge  bigserial NOT NULL,
    id_salarie      bigint NOT NULL
        CONSTRAINT solde_conge_fk_salarie
        REFERENCES salarie(id_salarie) ON DELETE RESTRICT,
    id_type_conge   bigint NOT NULL
        CONSTRAINT solde_conge_fk_type_conge
        REFERENCES type_conge(id_type_conge) ON DELETE RESTRICT,
    annee           integer NOT NULL
        CONSTRAINT solde_conge_annee_check CHECK (annee >= 2000 AND annee <= 2100),
    droit_acquis    numeric(5,2) NOT NULL DEFAULT 0
        CONSTRAINT solde_conge_droit_check CHECK (droit_acquis >= 0),
    report          numeric(5,2) NOT NULL DEFAULT 0
        CONSTRAINT solde_conge_report_check CHECK (report >= 0),
    pris            numeric(5,2) NOT NULL DEFAULT 0
        CONSTRAINT solde_conge_pris_check CHECK (pris >= 0),
    en_attente      numeric(5,2) NOT NULL DEFAULT 0
        CONSTRAINT solde_conge_en_attente_check CHECK (en_attente >= 0),
    PRIMARY KEY (id_solde_conge),
    CONSTRAINT solde_conge_uq UNIQUE (id_salarie, id_type_conge, annee),
    -- Filet de sécurité : le disponible ne peut pas passer sous 0
    -- (sauf si les avances sont autorisées : voir en-tête).
    -- Le disponible (droit_acquis + report - pris - en_attente) n'est PAS
    -- stocké : il est calculé par la vue et par SoldeService.
    CONSTRAINT solde_conge_disponible_check
        CHECK (droit_acquis + report - pris - en_attente >= 0)
);

-- ----------------------------------------------------------------------------
-- 2bis. Table métier principale : demande_conge
-- Cas A (base vierge)  : CREATE TABLE crée directement le schéma cible.
-- Cas B (base existante avec 07_migration_conge.sql) : CREATE TABLE est
-- un no-op, puis les blocs DO ci-dessous migrent la table sur place.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS demande_conge (
    id_demande_conge           bigserial NOT NULL
        CONSTRAINT demande_conge_pk PRIMARY KEY,
    id_salarie                 bigint NOT NULL
        CONSTRAINT demande_conge_fk_salarie
        REFERENCES salarie(id_salarie) ON DELETE RESTRICT,
    id_type_conge              bigint NOT NULL
        CONSTRAINT demande_conge_fk_type_conge
        REFERENCES type_conge(id_type_conge) ON DELETE RESTRICT,
    id_statut                  bigint NOT NULL
        CONSTRAINT demande_conge_fk_statut
        REFERENCES statut_demande_conge(id_statut) ON DELETE RESTRICT,
    date_debut                 date NOT NULL,
    date_fin                   date NOT NULL,
    demi_journee_debut         boolean NOT NULL DEFAULT false,
    demi_journee_fin           boolean NOT NULL DEFAULT false,
    nb_jours                   numeric(5,2) NOT NULL
        CONSTRAINT demande_conge_nb_jours_check CHECK (nb_jours > 0),
    motif                      text,
    date_demande               timestamp with time zone NOT NULL DEFAULT now(),
    id_manager_valideur        bigint
        CONSTRAINT demande_conge_fk_manager_valideur
        REFERENCES salarie(id_salarie) ON DELETE RESTRICT,
    date_validation_manager    timestamp with time zone,
    commentaire_manager        text,
    id_rh_valideur             bigint
        CONSTRAINT demande_conge_fk_rh_valideur
        REFERENCES salarie(id_salarie) ON DELETE RESTRICT,
    date_validation_rh         timestamp with time zone,
    commentaire_rh             text,
    motif_refus                text,
    date_annulation            timestamp with time zone,
    CONSTRAINT demande_conge_dates_check CHECK (date_fin >= date_debut),
    CONSTRAINT demande_conge_demi_journees_check
        CHECK (NOT (demi_journee_debut AND demi_journee_fin))
);

-- Migration de l'ancienne demande_conge (07_) vers le schéma Bloc 7.
-- rename id_conge -> id_demande_conge (conserve la séquence).
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name = 'demande_conge' AND column_name = 'id_conge')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns
                       WHERE table_name = 'demande_conge' AND column_name = 'id_demande_conge') THEN
        ALTER TABLE demande_conge RENAME COLUMN id_conge TO id_demande_conge;
    END IF;
END $$;

ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS id_salarie bigint;
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS id_type_conge bigint;
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS id_statut bigint;
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS demi_journee_debut boolean NOT NULL DEFAULT false;
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS demi_journee_fin boolean NOT NULL DEFAULT false;
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS nb_jours numeric(5,2);
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS date_demande timestamp with time zone NOT NULL DEFAULT now();
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS id_manager_valideur bigint;
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS date_validation_manager timestamp with time zone;
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS commentaire_manager text;
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS id_rh_valideur bigint;
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS date_validation_rh timestamp with time zone;
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS commentaire_rh text;
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS motif_refus text;
ALTER TABLE demande_conge ADD COLUMN IF NOT EXISTS date_annulation timestamp with time zone;

-- Backfill id_salarie : employe.id_salarie direct, puis via utilisateur.
-- CONDITIONNEL : ces UPDATE ne s'exécutent QUE si la table héritée possède
-- réellement ces colonnes (id_employe / id_utilisateur). Sur base vierge,
-- demande_conge est créée directement en schéma Bloc 7 et ces colonnes
-- n'existent pas : sans garde, PostgreSQL lève « la colonne
-- dc.id_employe n'existe pas » (erreur remontée par l'utilisateur).
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'demande_conge'
                 AND column_name = 'id_employe') THEN
        EXECUTE 'UPDATE demande_conge AS dc SET id_salarie = e.id_salarie
                 FROM employe AS e
                 WHERE dc.id_employe = e.id AND dc.id_salarie IS NULL';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'demande_conge'
                 AND column_name = 'id_utilisateur') THEN
        EXECUTE 'UPDATE demande_conge AS dc SET id_salarie = e.id_salarie
                 FROM utilisateur AS u
                 JOIN employe AS e ON e.id = u.id_employe
                 WHERE dc.id_utilisateur = u.id_utilisateur AND dc.id_salarie IS NULL';
    END IF;
END $$;

-- Backfill date_demande depuis date_creation (ancien schéma, si la colonne existe).
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'demande_conge'
                 AND column_name = 'date_creation') THEN
        EXECUTE 'UPDATE demande_conge
                 SET date_demande = date_creation
                 WHERE date_demande IS NULL AND date_creation IS NOT NULL';
    END IF;
END $$;

-- Backfill id_statut depuis le statut text hérité (si la colonne existe).
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'demande_conge'
                 AND column_name = 'statut') THEN
        EXECUTE 'UPDATE demande_conge AS dc
                 SET id_statut = s.id_statut
                 FROM statut_demande_conge AS s
                 WHERE dc.id_statut IS NULL
                   AND dc.statut = CASE s.code
                       WHEN ''EN_ATTENTE_MANAGER'' THEN ''En attente''
                       WHEN ''EN_ATTENTE_RH''      THEN ''En attente''
                       WHEN ''VALIDEE''            THEN ''Validée''
                       WHEN ''REFUSEE''            THEN ''Refusée''
                       WHEN ''ANNULEE''            THEN ''Annulée''
                   END';
    END IF;
END $$;

-- Backfill id_type_conge quand l'ancien texte correspond exactement à un code.
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'demande_conge'
                 AND column_name = 'type_conge') THEN
        EXECUTE 'UPDATE demande_conge AS dc
                 SET id_type_conge = t.id_type_conge
                 FROM type_conge AS t
                 WHERE dc.id_type_conge IS NULL AND dc.type_conge = t.code';
    END IF;
END $$;

-- Contraintes de la demande (CHECK et FK), via DO pour l'idempotence
-- sur la table héritée comme sur la table fraîche.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'demande_conge_dates_check' AND conrelid = 'demande_conge'::regclass) THEN
        ALTER TABLE demande_conge ADD CONSTRAINT demande_conge_dates_check CHECK (date_fin >= date_debut);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'demande_conge_nb_jours_check' AND conrelid = 'demande_conge'::regclass) THEN
        ALTER TABLE demande_conge ADD CONSTRAINT demande_conge_nb_jours_check CHECK (nb_jours IS NULL OR nb_jours > 0);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'demande_conge_demi_journees_check' AND conrelid = 'demande_conge'::regclass) THEN
        ALTER TABLE demande_conge ADD CONSTRAINT demande_conge_demi_journees_check CHECK (NOT (demi_journee_debut AND demi_journee_fin));
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'demande_conge_fk_salarie' AND conrelid = 'demande_conge'::regclass) THEN
        ALTER TABLE demande_conge ADD CONSTRAINT demande_conge_fk_salarie FOREIGN KEY (id_salarie) REFERENCES salarie(id_salarie) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'demande_conge_fk_type_conge' AND conrelid = 'demande_conge'::regclass) THEN
        ALTER TABLE demande_conge ADD CONSTRAINT demande_conge_fk_type_conge FOREIGN KEY (id_type_conge) REFERENCES type_conge(id_type_conge) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'demande_conge_fk_statut' AND conrelid = 'demande_conge'::regclass) THEN
        ALTER TABLE demande_conge ADD CONSTRAINT demande_conge_fk_statut FOREIGN KEY (id_statut) REFERENCES statut_demande_conge(id_statut) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'demande_conge_fk_manager_valideur' AND conrelid = 'demande_conge'::regclass) THEN
        ALTER TABLE demande_conge ADD CONSTRAINT demande_conge_fk_manager_valideur FOREIGN KEY (id_manager_valideur) REFERENCES salarie(id_salarie) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'demande_conge_fk_rh_valideur' AND conrelid = 'demande_conge'::regclass) THEN
        ALTER TABLE demande_conge ADD CONSTRAINT demande_conge_fk_rh_valideur FOREIGN KEY (id_rh_valideur) REFERENCES salarie(id_salarie) ON DELETE RESTRICT;
    END IF;
END $$;

-- NOT NULL sur les colonnes cibles UNIQUEMENT s'il ne reste aucun NULL
-- (lignes héritées non convertibles gardées en NULL pour re-saisie RH).
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM demande_conge WHERE id_salarie IS NULL) THEN
        ALTER TABLE demande_conge ALTER COLUMN id_salarie SET NOT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM demande_conge WHERE id_type_conge IS NULL) THEN
        ALTER TABLE demande_conge ALTER COLUMN id_type_conge SET NOT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM demande_conge WHERE id_statut IS NULL) THEN
        ALTER TABLE demande_conge ALTER COLUMN id_statut SET NOT NULL;
    END IF;
END $$;

-- Colonnes héritées : NULLABLE pour ne pas bloquer le nouveau workflow,
-- et index hérités conservés tels quels (idx_conge_*).
-- CONDITIONNEL : chaque ALTER ne tourne que si la colonne existe
-- (sur base vierge, id_utilisateur/type_conge/statut ne sont pas créés).
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'demande_conge'
                 AND column_name = 'id_utilisateur') THEN
        ALTER TABLE demande_conge ALTER COLUMN id_utilisateur DROP NOT NULL;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'demande_conge'
                 AND column_name = 'type_conge') THEN
        ALTER TABLE demande_conge ALTER COLUMN type_conge DROP NOT NULL;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'demande_conge'
                 AND column_name = 'motif') THEN
        ALTER TABLE demande_conge ALTER COLUMN motif DROP NOT NULL;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = current_schema() AND table_name = 'demande_conge'
                 AND column_name = 'statut') THEN
        ALTER TABLE demande_conge ALTER COLUMN statut DROP NOT NULL;
    END IF;
END $$;

-- Tables référençant demande_conge : créées APRÈS la migration ci-dessus
-- (id_conge -> id_demande_conge) pour que les FK inline soient valides
-- sur base fraîche comme sur base existante avec 07_migration_conge.sql.

CREATE TABLE IF NOT EXISTS absence (
    id_absence       bigserial NOT NULL,
    id_salarie       bigint NOT NULL
        CONSTRAINT absence_fk_salarie
        REFERENCES salarie(id_salarie) ON DELETE RESTRICT,
    id_type_absence  bigint NOT NULL
        CONSTRAINT absence_fk_type_absence
        REFERENCES type_absence(id_type_absence) ON DELETE RESTRICT,
    id_demande_conge bigint
        CONSTRAINT absence_fk_demande_conge
        REFERENCES demande_conge(id_demande_conge) ON DELETE RESTRICT,
    date_debut       date NOT NULL,
    date_fin         date NOT NULL,
    nb_jours         numeric(5,2) NOT NULL
        CONSTRAINT absence_nb_jours_check CHECK (nb_jours > 0),
    justifiee        boolean NOT NULL DEFAULT false,
    commentaire      text,
    id_saisi_par     bigint
        CONSTRAINT absence_fk_saisi_par
        REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT,
    date_saisie      timestamp with time zone NOT NULL DEFAULT now(),
    PRIMARY KEY (id_absence),
    CONSTRAINT absence_dates_check CHECK (date_fin >= date_debut)
);

CREATE TABLE IF NOT EXISTS justificatif_absence (
    id_justificatif  bigserial NOT NULL,
    id_absence       bigint
        CONSTRAINT justificatif_fk_absence
        REFERENCES absence(id_absence) ON DELETE RESTRICT,
    id_demande_conge bigint
        CONSTRAINT justificatif_fk_demande_conge
        REFERENCES demande_conge(id_demande_conge) ON DELETE RESTRICT,
    nom_fichier      varchar(255) NOT NULL,
    chemin           varchar(500) NOT NULL,
    date_depot       timestamp with time zone NOT NULL DEFAULT now(),
    valide           boolean,
    id_validateur    bigint
        CONSTRAINT justificatif_fk_validateur
        REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT,
    PRIMARY KEY (id_justificatif),
    CONSTRAINT justificatif_cible_check
        CHECK (id_absence IS NOT NULL OR id_demande_conge IS NOT NULL)
);

CREATE TABLE IF NOT EXISTS mouvement_solde (
    id_mouvement     bigserial NOT NULL,
    id_solde_conge   bigint NOT NULL
        CONSTRAINT mouvement_fk_solde_conge
        REFERENCES solde_conge(id_solde_conge) ON DELETE RESTRICT,
    id_demande_conge bigint
        CONSTRAINT mouvement_fk_demande_conge
        REFERENCES demande_conge(id_demande_conge) ON DELETE RESTRICT,
    type_mouvement   varchar(20) NOT NULL
        CONSTRAINT mouvement_type_check CHECK (type_mouvement IN (
            'ACQUISITION', 'RESERVATION', 'DEBIT', 'RECREDIT',
            'REPORT', 'AJUSTEMENT', 'EXPIRATION'
        )),
    nb_jours         numeric(5,2) NOT NULL,
    date_mouvement   timestamp with time zone NOT NULL DEFAULT now(),
    commentaire      text,
    PRIMARY KEY (id_mouvement)
);

CREATE TABLE IF NOT EXISTS delegation_validation (
    id_delegation bigserial NOT NULL,
    id_manager    bigint NOT NULL
        CONSTRAINT delegation_fk_manager
        REFERENCES salarie(id_salarie) ON DELETE RESTRICT,
    id_delegue    bigint NOT NULL
        CONSTRAINT delegation_fk_delegue
        REFERENCES salarie(id_salarie) ON DELETE RESTRICT,
    date_debut    date NOT NULL,
    date_fin      date NOT NULL,
    motif         text,
    PRIMARY KEY (id_delegation),
    CONSTRAINT delegation_dates_check CHECK (date_fin >= date_debut),
    CONSTRAINT delegation_differente_check CHECK (id_manager <> id_delegue)
);

-- ----------------------------------------------------------------------------
-- 2ter. Droit annuel de démonstration (quota ANNUEL par défaut)
-- id_type_contrat NULL = tous les contrats. À valider contre le règlement
-- interne avant production (25 j/an, acquisition mensuelle ≈ 2,08 j).
-- ----------------------------------------------------------------------------
INSERT INTO droit_conge (id_type_conge, id_type_contrat, nb_jours_annuel,
                         acquisition_mensuelle, anciennete_min_mois,
                         date_debut, date_fin, actif)
SELECT tc.id_type_conge, NULL, v.nb_jours_annuel, v.acquisition_mensuelle,
       v.anciennete_min_mois, DATE '2026-01-01', NULL, true
FROM type_conge AS tc
CROSS JOIN (VALUES
    ('ANNUEL',  25.00::numeric(5,2), 2.08::numeric(4,2), 0),
    ('FAMILIAL', 3.00::numeric(5,2), 0.25::numeric(4,2), 0),
    ('FORMATION', 5.00::numeric(5,2), 0.42::numeric(4,2), 0),
    ('RECUP',     4.00::numeric(5,2), 0.33::numeric(4,2), 0)
) AS v(code, nb_jours_annuel, acquisition_mensuelle, anciennete_min_mois)
WHERE tc.code = v.code
  AND NOT EXISTS (
      SELECT 1 FROM droit_conge AS dc
      WHERE dc.id_type_conge = tc.id_type_conge
        AND dc.id_type_contrat IS NOT DISTINCT FROM NULL
        AND dc.date_debut = DATE '2026-01-01'
  );

-- ============================================================================
-- 3. CONTRAINTES ET INDEX (déclaratifs uniquement)
-- ============================================================================

-- FK déjà inline pour les tables fraîches ; ces DO couvrent uniquement le
-- cas où une table existerait déjà sans les contraintes.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'droit_conge_fk_type_conge' AND conrelid = 'droit_conge'::regclass) THEN
        ALTER TABLE droit_conge ADD CONSTRAINT droit_conge_fk_type_conge FOREIGN KEY (id_type_conge) REFERENCES type_conge(id_type_conge) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'droit_conge_fk_type_contrat' AND conrelid = 'droit_conge'::regclass) THEN
        ALTER TABLE droit_conge ADD CONSTRAINT droit_conge_fk_type_contrat FOREIGN KEY (id_type_contrat) REFERENCES type_contrat(id) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'solde_conge_fk_salarie' AND conrelid = 'solde_conge'::regclass) THEN
        ALTER TABLE solde_conge ADD CONSTRAINT solde_conge_fk_salarie FOREIGN KEY (id_salarie) REFERENCES salarie(id_salarie) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'solde_conge_fk_type_conge' AND conrelid = 'solde_conge'::regclass) THEN
        ALTER TABLE solde_conge ADD CONSTRAINT solde_conge_fk_type_conge FOREIGN KEY (id_type_conge) REFERENCES type_conge(id_type_conge) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'absence_fk_salarie' AND conrelid = 'absence'::regclass) THEN
        ALTER TABLE absence ADD CONSTRAINT absence_fk_salarie FOREIGN KEY (id_salarie) REFERENCES salarie(id_salarie) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'absence_fk_type_absence' AND conrelid = 'absence'::regclass) THEN
        ALTER TABLE absence ADD CONSTRAINT absence_fk_type_absence FOREIGN KEY (id_type_absence) REFERENCES type_absence(id_type_absence) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'absence_fk_demande_conge' AND conrelid = 'absence'::regclass) THEN
        ALTER TABLE absence ADD CONSTRAINT absence_fk_demande_conge FOREIGN KEY (id_demande_conge) REFERENCES demande_conge(id_demande_conge) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'absence_fk_saisi_par' AND conrelid = 'absence'::regclass) THEN
        ALTER TABLE absence ADD CONSTRAINT absence_fk_saisi_par FOREIGN KEY (id_saisi_par) REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'justificatif_fk_absence' AND conrelid = 'justificatif_absence'::regclass) THEN
        ALTER TABLE justificatif_absence ADD CONSTRAINT justificatif_fk_absence FOREIGN KEY (id_absence) REFERENCES absence(id_absence) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'justificatif_fk_demande_conge' AND conrelid = 'justificatif_absence'::regclass) THEN
        ALTER TABLE justificatif_absence ADD CONSTRAINT justificatif_fk_demande_conge FOREIGN KEY (id_demande_conge) REFERENCES demande_conge(id_demande_conge) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'justificatif_fk_validateur' AND conrelid = 'justificatif_absence'::regclass) THEN
        ALTER TABLE justificatif_absence ADD CONSTRAINT justificatif_fk_validateur FOREIGN KEY (id_validateur) REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'mouvement_fk_solde_conge' AND conrelid = 'mouvement_solde'::regclass) THEN
        ALTER TABLE mouvement_solde ADD CONSTRAINT mouvement_fk_solde_conge FOREIGN KEY (id_solde_conge) REFERENCES solde_conge(id_solde_conge) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'mouvement_fk_demande_conge' AND conrelid = 'mouvement_solde'::regclass) THEN
        ALTER TABLE mouvement_solde ADD CONSTRAINT mouvement_fk_demande_conge FOREIGN KEY (id_demande_conge) REFERENCES demande_conge(id_demande_conge) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'delegation_fk_manager' AND conrelid = 'delegation_validation'::regclass) THEN
        ALTER TABLE delegation_validation ADD CONSTRAINT delegation_fk_manager FOREIGN KEY (id_manager) REFERENCES salarie(id_salarie) ON DELETE RESTRICT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'delegation_fk_delegue' AND conrelid = 'delegation_validation'::regclass) THEN
        ALTER TABLE delegation_validation ADD CONSTRAINT delegation_fk_delegue FOREIGN KEY (id_delegue) REFERENCES salarie(id_salarie) ON DELETE RESTRICT;
    END IF;
END $$;

-- Index : demandes par salarié/dates et par statut (workflow), solde,
-- absences, mouvements, justificatifs, délégations, jours fériés.
CREATE INDEX IF NOT EXISTS idx_demande_conge_salarie_dates
    ON demande_conge (id_salarie, date_debut, date_fin);
CREATE INDEX IF NOT EXISTS idx_demande_conge_statut
    ON demande_conge (id_statut);
CREATE INDEX IF NOT EXISTS idx_demande_conge_statut_date
    ON demande_conge (id_statut, date_demande);
CREATE INDEX IF NOT EXISTS idx_solde_conge_salarie
    ON solde_conge (id_salarie, annee);
CREATE INDEX IF NOT EXISTS idx_droit_conge_type
    ON droit_conge (id_type_conge, actif, date_debut);
CREATE INDEX IF NOT EXISTS idx_absence_salarie_dates
    ON absence (id_salarie, date_debut, date_fin);
CREATE INDEX IF NOT EXISTS idx_absence_dates
    ON absence (date_debut, date_fin);
CREATE INDEX IF NOT EXISTS idx_absence_demande
    ON absence (id_demande_conge);
CREATE INDEX IF NOT EXISTS idx_mouvement_solde
    ON mouvement_solde (id_solde_conge, date_mouvement);
CREATE INDEX IF NOT EXISTS idx_mouvement_demande
    ON mouvement_solde (id_demande_conge);
CREATE INDEX IF NOT EXISTS idx_justificatif_absence
    ON justificatif_absence (id_absence);
CREATE INDEX IF NOT EXISTS idx_justificatif_demande
    ON justificatif_absence (id_demande_conge);
CREATE INDEX IF NOT EXISTS idx_delegation_manager
    ON delegation_validation (id_manager, date_debut, date_fin);
CREATE INDEX IF NOT EXISTS idx_jour_ferie_date
    ON jour_ferie (date_ferie);
CREATE INDEX IF NOT EXISTS idx_type_conge_actif
    ON type_conge (actif);

COMMENT ON TABLE type_conge IS 'Référentiel des types de congé (paye, débit solde, justificatif, délais).';
COMMENT ON TABLE statut_demande_conge IS 'Référentiel des statuts de demande de congé (workflow).';
COMMENT ON TABLE type_absence IS 'Référentiel des types d''absence (planning et retenue paie).';
COMMENT ON TABLE jour_ferie IS 'Jours fériés (recurrent = comparaison mois/jour côté app).';
COMMENT ON TABLE droit_conge IS 'Quotas de congé par type (id_type_contrat NULL = tous les contrats), historisés.';
COMMENT ON TABLE solde_conge IS 'Soldes de congé par salarié/type/année. Le disponible est calculé, jamais stocké.';
COMMENT ON TABLE demande_conge IS 'Demandes de congé (workflow manager puis RH), migrée depuis l''ancien schéma 07_.';
COMMENT ON TABLE absence IS 'Absences effectives (générées depuis un congé validé ou saisies directement).';
COMMENT ON TABLE justificatif_absence IS 'Fichiers justificatifs rattachés à une absence ou à une demande.';
COMMENT ON TABLE mouvement_solde IS 'Journal des mouvements de solde (écrit par SoldeService, jamais supprimé).';
COMMENT ON TABLE delegation_validation IS 'Délégations de validation (manager absent ou en congé).';

-- ============================================================================
-- 4. VUES (lecture seule, calculées à la volée — aucune logique d'écriture)
-- ============================================================================

-- Salarié + type + année + droit + report + pris + en attente + DISPONIBLE.
-- Le disponible est recalculé ici (et dans SoldeService) ; il n'est pas stocké.
CREATE OR REPLACE VIEW v_solde_conge_salarie AS
SELECT
    sc.id_solde_conge,
    sc.id_salarie,
    s.matricule,
    s.nom,
    s.prenom,
    sc.id_type_conge,
    tc.code AS code_type_conge,
    tc.libelle AS libelle_type_conge,
    sc.annee,
    sc.droit_acquis,
    sc.report,
    sc.pris,
    sc.en_attente,
    (sc.droit_acquis + sc.report - sc.pris - sc.en_attente) AS disponible
FROM solde_conge sc
JOIN salarie s ON s.id_salarie = sc.id_salarie
JOIN type_conge tc ON tc.id_type_conge = sc.id_type_conge;

-- Détail complet d'une demande (base de toutes les listes).
-- Le service est déduit du contrat actif (date) -> poste -> service.
-- Limitation : contrat courant, pas contrat historique à la date de demande.
CREATE OR REPLACE VIEW v_demande_conge_detail AS
SELECT
    dc.id_demande_conge,
    dc.id_salarie,
    s.matricule,
    s.nom,
    s.prenom,
    dc.id_type_conge,
    tc.code AS code_type_conge,
    tc.libelle AS libelle_type_conge,
    dc.id_statut,
    sd.code AS code_statut,
    sd.libelle AS libelle_statut,
    dc.date_debut,
    dc.date_fin,
    dc.demi_journee_debut,
    dc.demi_journee_fin,
    dc.nb_jours,
    dc.motif,
    dc.date_demande,
    dc.id_manager_valideur,
    m.nom AS nom_manager_valideur,
    dc.date_validation_manager,
    dc.commentaire_manager,
    dc.id_rh_valideur,
    r.nom AS nom_rh_valideur,
    dc.date_validation_rh,
    dc.commentaire_rh,
    dc.motif_refus,
    dc.date_annulation,
    sv.id_service,
    sv.nom AS nom_service
FROM demande_conge dc
JOIN salarie s ON s.id_salarie = dc.id_salarie
JOIN type_conge tc ON tc.id_type_conge = dc.id_type_conge
JOIN statut_demande_conge sd ON sd.id_statut = dc.id_statut
LEFT JOIN salarie m ON m.id_salarie = dc.id_manager_valideur
LEFT JOIN salarie r ON r.id_salarie = dc.id_rh_valideur
LEFT JOIN contrat c ON c.id_salarie = dc.id_salarie
    AND c.date_debut <= CURRENT_DATE
    AND (c.date_fin IS NULL OR c.date_fin >= CURRENT_DATE)
LEFT JOIN poste p ON p.id_poste = c.id_poste
LEFT JOIN service sv ON sv.id_service = p.id_service;

-- Demandes en attente de validation manager (+ solde du demandeur).
-- NOTE : aucune colonne « manager » n'existe dans salarie (voir en-tête) :
-- la vue retourne toutes les EN_ATTENTE_MANAGER ; le filtre « équipe du
-- manager » (et delegation_validation) est appliqué côté CodeIgniter.
CREATE OR REPLACE VIEW v_demandes_a_valider_manager AS
SELECT
    d.id_demande_conge,
    d.id_salarie,
    d.matricule,
    d.nom,
    d.prenom,
    d.code_type_conge,
    d.date_debut,
    d.date_fin,
    d.nb_jours,
    d.date_demande,
    (CURRENT_DATE - d.date_demande::date) AS jours_attente,
    d.id_service,
    d.nom_service,
    solde.disponible AS solde_disponible_demandeur
FROM v_demande_conge_detail d
LEFT JOIN v_solde_conge_salarie solde
    ON solde.id_salarie = d.id_salarie
   AND solde.id_type_conge = d.id_type_conge
   AND solde.annee = EXTRACT(YEAR FROM d.date_debut)::integer
WHERE d.code_statut = 'EN_ATTENTE_MANAGER';

-- Demandes en attente de validation RH (+ solde + justificatif présent ?).
CREATE OR REPLACE VIEW v_demandes_a_valider_rh AS
SELECT
    d.id_demande_conge,
    d.id_salarie,
    d.matricule,
    d.nom,
    d.prenom,
    d.code_type_conge,
    d.libelle_type_conge,
    d.date_debut,
    d.date_fin,
    d.nb_jours,
    d.date_demande,
    (CURRENT_DATE - d.date_demande::date) AS jours_attente,
    d.date_validation_manager,
    solde.disponible AS solde_disponible_demandeur,
    tc.justificatif_requis,
    EXISTS (
        SELECT 1 FROM justificatif_absence j
        WHERE j.id_demande_conge = d.id_demande_conge
    ) AS justificatif_present,
    d.id_service,
    d.nom_service
FROM v_demande_conge_detail d
JOIN type_conge tc ON tc.id_type_conge = d.id_type_conge
LEFT JOIN v_solde_conge_salarie solde
    ON solde.id_salarie = d.id_salarie
   AND solde.id_type_conge = d.id_type_conge
   AND solde.annee = EXTRACT(YEAR FROM d.date_debut)::integer
WHERE d.code_statut = 'EN_ATTENTE_RH';

-- Historique complet des demandes d'un salarié (statuts et dates de validation).
CREATE OR REPLACE VIEW v_historique_conges_salarie AS
SELECT
    dc.id_salarie,
    s.matricule,
    s.nom,
    s.prenom,
    dc.id_demande_conge,
    tc.code AS code_type_conge,
    sd.code AS code_statut,
    dc.date_debut,
    dc.date_fin,
    dc.nb_jours,
    dc.date_demande,
    dc.date_validation_manager,
    dc.date_validation_rh,
    dc.motif_refus,
    dc.date_annulation
FROM demande_conge dc
JOIN salarie s ON s.id_salarie = dc.id_salarie
JOIN type_conge tc ON tc.id_type_conge = dc.id_type_conge
JOIN statut_demande_conge sd ON sd.id_statut = dc.id_statut;

-- Planning d'équipe : une ligne par salarié et par jour (generate_series).
-- Congés et absences confondus. Les demi-journées restent à jour entière ici ;
-- PaieCongeService::planningEquipe affine avec demi_journee_* via l'app.
CREATE OR REPLACE VIEW v_planning_absences AS
SELECT
    a.id_absence,
    a.id_salarie,
    s.matricule,
    s.nom,
    s.prenom,
    a.id_type_absence,
    ta.code AS code_type_absence,
    ta.libelle AS libelle_type_absence,
    a.id_demande_conge,
    (gs::date) AS jour,
    a.date_debut,
    a.date_fin,
    a.nb_jours,
    a.justifiee,
    sv.id_service,
    sv.nom AS nom_service
FROM absence a
JOIN salarie s ON s.id_salarie = a.id_salarie
JOIN type_absence ta ON ta.id_type_absence = a.id_type_absence
CROSS JOIN generate_series(a.date_debut, a.date_fin, INTERVAL '1 day') AS gs
LEFT JOIN contrat c ON c.id_salarie = a.id_salarie
    AND c.date_debut <= a.date_fin
    AND (c.date_fin IS NULL OR c.date_fin >= a.date_debut)
LEFT JOIN poste p ON p.id_poste = c.id_poste
LEFT JOIN service sv ON sv.id_service = p.id_service;

-- Salariés actuellement absents (via leurs absences, pas via les demandes).
CREATE OR REPLACE VIEW v_conges_en_cours AS
SELECT
    a.id_absence,
    a.id_salarie,
    s.matricule,
    s.nom,
    s.prenom,
    ta.code AS code_type_absence,
    a.date_debut,
    a.date_fin,
    a.nb_jours,
    a.justifiee,
    a.id_demande_conge
FROM absence a
JOIN salarie s ON s.id_salarie = a.id_salarie
JOIN type_absence ta ON ta.id_type_absence = a.id_type_absence
WHERE a.date_debut <= CURRENT_DATE AND a.date_fin >= CURRENT_DATE;

-- Congés validés des 30 prochains jours (demandes VALIDEE à venir).
CREATE OR REPLACE VIEW v_conges_a_venir AS
SELECT
    dc.id_demande_conge,
    dc.id_salarie,
    s.matricule,
    s.nom,
    s.prenom,
    tc.code AS code_type_conge,
    dc.date_debut,
    dc.date_fin,
    dc.nb_jours
FROM demande_conge dc
JOIN salarie s ON s.id_salarie = dc.id_salarie
JOIN type_conge tc ON tc.id_type_conge = dc.id_type_conge
JOIN statut_demande_conge sd ON sd.id_statut = dc.id_statut
WHERE sd.code = 'VALIDEE'
  AND dc.date_debut BETWEEN CURRENT_DATE AND (CURRENT_DATE + INTERVAL '30 days')::date;

-- Taux de présence par service et par jour.
-- Fenêtre : 90 derniers jours + 30 prochains jours. Effectif = salariés avec
-- un contrat couvrant le jour (contrat.date_debut <= jour <= contrat.date_fin
-- ou date_fin NULL) via poste -> service. Le statut de contrat
-- (status_contrat) n'est pas filtré ici : contrat actif = couverture par dates.
CREATE OR REPLACE VIEW v_taux_presence_service AS
WITH jours AS (
    SELECT (gs::date) AS jour
    FROM generate_series(
        (CURRENT_DATE - INTERVAL '90 days')::date,
        (CURRENT_DATE + INTERVAL '30 days')::date,
        INTERVAL '1 day'
    ) AS gs
),
effectif AS (
    SELECT
        j.jour,
        p.id_service,
        COUNT(DISTINCT s.id_salarie) AS nb_effectif
    FROM jours j
    JOIN salarie s ON true
    JOIN contrat c ON c.id_salarie = s.id_salarie
        AND c.date_debut <= j.jour
        AND (c.date_fin IS NULL OR c.date_fin >= j.jour)
    JOIN poste p ON p.id_poste = c.id_poste
    GROUP BY j.jour, p.id_service
),
absents AS (
    SELECT
        j.jour,
        p.id_service,
        COUNT(DISTINCT a.id_salarie) AS nb_absents
    FROM jours j
    JOIN absence a ON a.date_debut <= j.jour AND a.date_fin >= j.jour
    JOIN contrat c ON c.id_salarie = a.id_salarie
        AND c.date_debut <= j.jour
        AND (c.date_fin IS NULL OR c.date_fin >= j.jour)
    JOIN poste p ON p.id_poste = c.id_poste
    GROUP BY j.jour, p.id_service
)
SELECT
    e.jour,
    e.id_service,
    sv.nom AS nom_service,
    e.nb_effectif,
    COALESCE(a.nb_absents, 0) AS nb_absents,
    (e.nb_effectif - COALESCE(a.nb_absents, 0)) AS nb_presents,
    CASE WHEN e.nb_effectif > 0
         THEN ROUND((e.nb_effectif - COALESCE(a.nb_absents, 0))::numeric * 100 / e.nb_effectif, 2)
         ELSE 0
    END AS taux_presence_pct
FROM effectif e
LEFT JOIN absents a ON a.jour = e.jour AND a.id_service = e.id_service
JOIN service sv ON sv.id_service = e.id_service;

-- Paires de salariés d'un même service absents aux mêmes dates (chevauchements).
-- Service déduit du contrat courant (même limitation que v_demande_conge_detail).
CREATE OR REPLACE VIEW v_chevauchements_equipe AS
SELECT
    a1.id_salarie AS id_salarie_1,
    a2.id_salarie AS id_salarie_2,
    a1.date_debut AS debut_1,
    a1.date_fin AS fin_1,
    a2.date_debut AS debut_2,
    a2.date_fin AS fin_2,
    GREATEST(a1.date_debut, a2.date_debut) AS debut_chevauchement,
    LEAST(a1.date_fin, a2.date_fin) AS fin_chevauchement,
    sv.id_service,
    sv.nom AS nom_service
FROM absence a1
JOIN absence a2 ON a1.id_salarie < a2.id_salarie
    AND a1.date_debut <= a2.date_fin
    AND a2.date_debut <= a1.date_fin
LEFT JOIN contrat c1 ON c1.id_salarie = a1.id_salarie
    AND c1.date_debut <= CURRENT_DATE
    AND (c1.date_fin IS NULL OR c1.date_fin >= CURRENT_DATE)
LEFT JOIN poste p1 ON p1.id_poste = c1.id_poste
LEFT JOIN contrat c2 ON c2.id_salarie = a2.id_salarie
    AND c2.date_debut <= CURRENT_DATE
    AND (c2.date_fin IS NULL OR c2.date_fin >= CURRENT_DATE)
LEFT JOIN poste p2 ON p2.id_poste = c2.id_poste
LEFT JOIN service sv ON sv.id_service = p1.id_service AND p2.id_service = p1.id_service
WHERE p1.id_service IS NOT NULL
  AND p1.id_service = p2.id_service;

-- Absences non justifiées à régulariser, délai dépassé.
-- SEUIL : absences terminées depuis plus de 2 jours (48 h, délai maladie RG08/RG26).
CREATE OR REPLACE VIEW v_absences_non_justifiees AS
SELECT
    a.id_absence,
    a.id_salarie,
    s.matricule,
    s.nom,
    s.prenom,
    ta.code AS code_type_absence,
    a.date_debut,
    a.date_fin,
    a.nb_jours,
    a.date_saisie,
    (CURRENT_DATE - a.date_fin) AS jours_depuis_fin,
    a.commentaire
FROM absence a
JOIN salarie s ON s.id_salarie = a.id_salarie
JOIN type_absence ta ON ta.id_type_absence = a.id_type_absence
WHERE a.justifiee = false
  AND a.date_fin < (CURRENT_DATE - INTERVAL '2 days')::date;

-- Alertes de solde : disponible négatif (ne devrait jamais arriver grâce
-- au CHECK) ou disponible élevé non consommé (risque d'expiration).
-- SEUIL : disponible > 10 jours sur l'année en cours (à ajuster selon RG12).
CREATE OR REPLACE VIEW v_alertes_solde AS
SELECT
    v.id_solde_conge,
    v.id_salarie,
    v.matricule,
    v.nom,
    v.prenom,
    v.code_type_conge,
    v.annee,
    v.disponible,
    CASE
        WHEN v.disponible < 0 THEN 'SOLDE_NEGATIF'
        ELSE 'SOLDE_ELEVE'
    END AS type_alerte
FROM v_solde_conge_salarie v
WHERE v.disponible < 0
   OR (v.disponible > 10 AND v.annee = EXTRACT(YEAR FROM CURRENT_DATE)::integer);

-- Variables de paie par salarié et par mois (jours payés, sans solde, injustifiés).
-- Chaque absence est découpée en jours (generate_series) puis regroupée par mois.
-- Limitation : les demi-journées comptent 1 jour dans cette vue ;
-- PaieCongeService::repartirParMois ajuste côté app.
CREATE OR REPLACE VIEW v_variables_paie_conges AS
SELECT
    a.id_salarie,
    s.matricule,
    s.nom,
    s.prenom,
    (date_trunc('month', gs::date))::date AS mois,
    SUM(CASE
        WHEN a.justifiee = false OR ta.code = 'ABSENCE_INJUSTIFIEE' THEN 0
        WHEN COALESCE(tc.est_paye, true) = false AND ta.code = 'CONGE' THEN 0
        WHEN tc.code = 'SANS_SOLDE' THEN 0
        ELSE 1
    END) AS jours_payes,
    SUM(CASE
        WHEN a.justifiee = false OR ta.code = 'ABSENCE_INJUSTIFIEE' THEN 0
        WHEN tc.code = 'SANS_SOLDE' THEN 1
        WHEN ta.code = 'CONGE' AND COALESCE(tc.est_paye, true) = false THEN 1
        ELSE 0
    END) AS jours_sans_solde,
    SUM(CASE
        WHEN a.justifiee = false OR ta.code = 'ABSENCE_INJUSTIFIEE' THEN 1
        ELSE 0
    END) AS jours_injustifies
FROM absence a
JOIN salarie s ON s.id_salarie = a.id_salarie
JOIN type_absence ta ON ta.id_type_absence = a.id_type_absence
LEFT JOIN demande_conge dc ON dc.id_demande_conge = a.id_demande_conge
LEFT JOIN type_conge tc ON tc.id_type_conge = dc.id_type_conge
CROSS JOIN generate_series(a.date_debut, a.date_fin, INTERVAL '1 day') AS gs
GROUP BY a.id_salarie, s.matricule, s.nom, s.prenom, date_trunc('month', gs::date);

-- Demandes en attente depuis plus de X jours (relance / escalade, RG20).
-- SEUIL : 7 jours — à aligner avec la commande conges:relance.
CREATE OR REPLACE VIEW v_demandes_en_retard AS
SELECT
    dc.id_demande_conge,
    dc.id_salarie,
    s.matricule,
    s.nom,
    s.prenom,
    tc.code AS code_type_conge,
    sd.code AS code_statut,
    dc.date_demande,
    (CURRENT_DATE - dc.date_demande::date) AS jours_attente,
    dc.date_debut,
    dc.date_fin
FROM demande_conge dc
JOIN salarie s ON s.id_salarie = dc.id_salarie
JOIN type_conge tc ON tc.id_type_conge = dc.id_type_conge
JOIN statut_demande_conge sd ON sd.id_statut = dc.id_statut
WHERE sd.code IN ('EN_ATTENTE_MANAGER', 'EN_ATTENTE_RH')
  AND dc.date_demande < (CURRENT_DATE - INTERVAL '7 days')::timestamptz;

-- Journal lisible des mouvements de solde par salarié.
CREATE OR REPLACE VIEW v_mouvements_solde AS
SELECT
    m.id_mouvement,
    m.id_solde_conge,
    sc.id_salarie,
    s.matricule,
    s.nom,
    s.prenom,
    sc.id_type_conge,
    tc.code AS code_type_conge,
    sc.annee,
    m.type_mouvement,
    m.nb_jours,
    m.date_mouvement,
    m.id_demande_conge,
    m.commentaire
FROM mouvement_solde m
JOIN solde_conge sc ON sc.id_solde_conge = m.id_solde_conge
JOIN salarie s ON s.id_salarie = sc.id_salarie
JOIN type_conge tc ON tc.id_type_conge = sc.id_type_conge;

COMMENT ON VIEW v_solde_conge_salarie IS 'Soldes par salarié/type/année avec copie du disponible calculé.';
COMMENT ON VIEW v_demande_conge_detail IS 'Vue de base de toutes les listes de demandes de congé.';
COMMENT ON VIEW v_demandes_a_valider_manager IS 'Demandes EN_ATTENTE_MANAGER (filtre équipe côté app, voir en-tête).';
COMMENT ON VIEW v_demandes_a_valider_rh IS 'Demandes EN_ATTENTE_RH avec solde et présence de justificatif.';
COMMENT ON VIEW v_historique_conges_salarie IS 'Historique des demandes d''un salarié.';
COMMENT ON VIEW v_planning_absences IS 'Planning d''équipe : une ligne par salarié et par jour.';
COMMENT ON VIEW v_conges_en_cours IS 'Salariés actuellement absents.';
COMMENT ON VIEW v_conges_a_venir IS 'Congés validés des 30 prochains jours.';
COMMENT ON VIEW v_taux_presence_service IS 'Taux de présence par service et par jour (fenêtre -90 j / +30 j).';
COMMENT ON VIEW v_chevauchements_equipe IS 'Paires de salariés du même service absents aux mêmes dates.';
COMMENT ON VIEW v_absences_non_justifiees IS 'Absences non justifiées terminées depuis plus de 2 jours.';
COMMENT ON VIEW v_alertes_solde IS 'Soldes négatifs ou élevés (disponible > 10 j, année en cours).';
COMMENT ON VIEW v_variables_paie_conges IS 'Variables de paie par salarié/mois : jours payés, sans solde, injustifiés.';
COMMENT ON VIEW v_demandes_en_retard IS 'Demandes en attente depuis plus de 7 jours.';
COMMENT ON VIEW v_mouvements_solde IS 'Journal lisible des mouvements de solde par salarié.';

COMMIT;



INSERT INTO jour_ferie (date_ferie, libelle, recurrent)
SELECT v.date_ferie, v.libelle, v.recurrent
FROM (VALUES
    (DATE '2026-06-26', 'Jour de l''Indépendance',                          true),
    (DATE '2026-03-29', '29 Mars',                 true)
) AS v(date_ferie, libelle, recurrent)
WHERE NOT EXISTS (
    SELECT 1 FROM jour_ferie WHERE jour_ferie.date_ferie = v.date_ferie AND jour_ferie.libelle = v.libelle
);