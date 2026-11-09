# SQL — source unique de vérité

Ce dossier remplace tous les anciens scripts SQL, désormais déplacés dans
`script/archive/`. Il contient **3 fichiers** à exécuter dans cet ordre.

| Fichier | Rôle | Contenu |
| --- | --- | --- |
| `01_migrations.sql` | Schéma | 48 tables, clés étrangères, index, commentaires |
| `02_seeds.sql` | Données réelles | référentiels + départements/services/postes + grilles + comptes de démo |
| `03_seeds_demo.sql` | Démo (optionnel) | 50 candidats, 50 offres, 50 candidatures, 50 propositions |
| `04_migration_dossier_administratif.sql` | Migration Bloc 5 | colonnes identité, validité et confidentialité des documents |
| `08_migration_conges_bloc7.sql` | Migration Bloc 7 | tables congés/absences/soldes, contraintes, index, 15 vues |
| `09_seeds_conges_bloc7.sql` | Fixtures Bloc 7 | contrats, soldes, demandes, absences, mouvements, justificatifs |
| `10_employes_workflow_conges.sql` | Fixtures Workflow | 5 employés multi-départements (contrats, historique, affectations) |
| `11_correctif_conges_paternite.sql` | Correctif | `PATERNITE deduit_solde = false` |
| `12_correctif_conges_sans_solde.sql` | Correctif | `SANS_SOLDE deduit_solde = false`, `justificatif_requis = false` |
| `13_responsables_services_conges.sql` | Fixtures | responsables par service (Développement, Comptabilité, Ventes) + délégation |
| `14_migration_paie.sql` | Migration Bloc Paie | tables bulletins/éléments/taux + vue `v_bulletins_mois` |
| `15_test_paie_retenues.sql` | Test paie (optionnel) | absences/congés/éléments de novembre 2026 pour tester la retenue automatique sur bulletin |

## Installation sur une base vierge

```
createdb -U postgres gestion_rh
psql -U postgres -d gestion_rh -f script/db/01_migrations.sql
psql -U postgres -d gestion_rh -f script/db/02_seeds.sql
```

Puis, seulement si vous voulez le jeu de démonstration :

```
psql -U postgres -d gestion_rh -f script/db/03_seeds_demo.sql
```

## Mise à jour d'une base existante (Bloc 5)

```
psql -U postgres -d gestion_rh -f script/db/04_migration_dossier_administratif.sql
psql -U postgres -d gestion_rh -f script/db/02_seeds.sql
```

Le script `04` ajoute les colonnes manquantes sans supprimer de données. Le rejeu de
`02_seeds.sql` complète les métadonnées des types de documents (obligation, validité,
confidentialité).

Tous les scripts sont **idempotents** : on peut les relancer sans erreur ni doublon.

## Comptes de démonstration

| Identifiant | Mot de passe | Rôle |
| --- | --- | --- |
| `admin` | `admin123` | admin |
| `rh` | `rh123` | rh |
| `manager` | `manager123` | manager |
| `recruteur` | `recruteur123` | recruteur |
| `direction` | `direction123` | direction |
| `controle` | `controle123` | controle\_gestion |
| `salarie` | `salarie123` | salarie |

## Anciens fichiers remplacés

| Ancien fichier | Remplacé par | Remarque |
| --- | --- | --- |
| `Gestion RH - Modèle relationnel complet-*.sql` | `01_migrations.sql` | le `DROP DATABASE` a été retiré |
| `migration_auth.sql` | `01_migrations.sql` | colonnes et index déjà intégrés |
| `migration_negociation.sql` | `01_migrations.sql` | tables déjà intégrées |
| `Donne_status.sql` | `02_seeds.sql` |   |
| `fixtures_auth.sql` | `02_seeds.sql` |   |
| `fixtures_grille.sql` | `02_seeds.sql` |   |
| `fixtures_embauche.sql` | `03_seeds_demo.sql` | IDs support décalés à 9001 |
| `fixtures_recrutement.sql` | — | non utilisé par la base réelle ; ses référentiels sont déjà dans `02_seeds.sql` |

> Les anciens fichiers sont déplacés dans `script/archive/` pour l'historique, mais **ne doivent plus être exécutés**.

## Ce qui n'est volontairement pas dans les seeds

Les données créées par l'application (salariés `MAT-2026-*`, contrats, demandes,  
historiques, `journal_audit`, etc.) ne sont **pas** des seeds : ce sont des données  
d'exploitation. Elles restent dans la base et ne sont pas versionnées ici.