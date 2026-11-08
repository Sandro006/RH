-- ============================================================================
-- 06_view_salarie_utilisateur.sql — Vue : salarié + compte utilisateur lié
--
-- Cette vue agrège les informations d'un salarié avec son compte utilisateur
-- (identifiant, rôle, état actif, etc.) pour un affichage consolidé.
--
-- Tables jointes :
--   • salarie            (informations administratives du salarié)
--   • employe            (lien salarié <-> poste)
--   • poste              (intitulé du poste)
--   • service            (nom du service)
--   • departement        (nom du département)
--   • status_salarie     (libellé du statut du salarié)
--   • utilisateur        (compte de connexion lié à l'employé)
--
-- Utilisation :
--   psql -U postgres -d gestion_rh -f script/db/06_view_salarie_utilisateur.sql
--
-- Requête d'exemple :
--   SELECT * FROM vw_salarie_utilisateur WHERE matricule = 'MAT-INF-0001';
-- ============================================================================

BEGIN;

CREATE OR REPLACE VIEW vw_salarie_utilisateur AS
SELECT
    -- Salarié
    s.id_salarie,
    s.matricule,
    s.nom              AS nom_salarie,
    s.prenom,
    s.email            AS email_salarie,
    s.telephone,
    s.date_embauche,
    ss.designation     AS statut_salarie,

    -- Employé (lien salarié <-> poste)
    e.id               AS id_employe,
    e.nom              AS nom_employe,
    e.date_de_naissance,

    -- Poste
    p.id_poste,
    p.intitule         AS intitule_poste,

    -- Service
    sv.id_service,
    sv.nom             AS nom_service,

    -- Département
    d.id_departement,
    d.nom              AS nom_departement,

    -- Compte utilisateur lié
    u.id_utilisateur,
    u.identifiant,
    u.role,
    u.actif            AS utilisateur_actif,
    u.doit_changer_mot_de_passe,
    u.date_creation    AS date_creation_compte,
    u.derniere_connexion

FROM salarie s
LEFT JOIN status_salarie ss ON ss.id = s.statut
LEFT JOIN employe e ON e.id_salarie = s.id_salarie
LEFT JOIN poste p ON p.id_poste = e.poste_id
LEFT JOIN service sv ON sv.id_service = p.id_service
LEFT JOIN departement d ON d.id_departement = sv.id_departement
LEFT JOIN utilisateur u ON u.id_employe = e.id;

-- Index optionnel pour accélérer les recherches par matricule ou identifiant
-- (PostgreSQL crée automatiquement un index sur les vues matérialisées uniquement ;
--  ici c'est une vue classique, l'index sur les tables sous-jacentes suffit.)

COMMENT ON VIEW vw_salarie_utilisateur IS
    'Vue consolidée : salarié, employé, poste, service, département et compte utilisateur lié.';

-- ----------------------------------------------------------------------------
-- Vérification : exemple d'utilisation
-- ----------------------------------------------------------------------------
-- SELECT id_salarie, matricule, nom_salarie, prenom, identifiant, role,
--        utilisateur_actif, nom_departement, intitule_poste
-- FROM vw_salarie_utilisateur
-- ORDER BY matricule;

-- Recherche par matricule :
-- SELECT * FROM vw_salarie_utilisateur WHERE matricule = 'MAT-INF-0001';

-- Recherche par identifiant utilisateur :
-- SELECT * FROM vw_salarie_utilisateur WHERE identifiant = 'tdurand';

-- Salariés sans compte utilisateur (identifiant IS NULL) :
-- SELECT id_salarie, matricule, nom_salarie, prenom
-- FROM vw_salarie_utilisateur
-- WHERE identifiant IS NULL
-- ORDER BY matricule;

COMMIT;
