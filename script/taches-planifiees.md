# Tâches planifiées Congés — mise en service (cron)

Les commandes Congés existent (`conges:acquisition`, `conges:cloture-annee`,
`conges:relance`, `conges:requalifier-absences`), mais **rien ne les déclenche
automatiquement** : sans planification, les soldes ne sont jamais crédités
(RG11), les demandes en retard ne sont jamais relancées (RG20) et les maladies
non justifiées ne sont jamais requalifiées (RG26 — donc jamais retenues en paie).

CodeIgniter 4.7 n'inclut pas de scheduler : on passe par le **cron système**.
Un seul point d'entrée, à appeler **chaque jour** :

```bash
php spark conges:taches
```

`conges:taches` (`app/Commands/CongesTaches.php`) enchaîne automatiquement :

| Quand | Tâches exécutées |
|---|---|
| Chaque jour | `conges:relance` (RG20) + `conges:requalifier-absences` (RG26) |
| Le 1er du mois | + `conges:acquisition` du mois courant (RG11) |
| Le 1er janvier | + `conges:cloture-annee` de l'année précédente (RG12) |

## Linux (crontab)

```cron
# Tâches Congés quotidiennes (relance, requalification, acquisition le 1er, clôture le 1er janvier)
0 2 * * * cd /chemin/vers/RH && php spark conges:taches >> writable/logs/cron-conges.log 2>&1
```

## Windows (Planificateur de tâches)

1. Ouvrir le Planificateur de tâches → **Créer une tâche de base**.
2. Déclencheur : **Quotidiennement** (ex. 02:00).
3. Action : **Démarrer un programme**
   - Programme : `C:\php\php.exe` (chemin de ton PHP)
   - Arguments : `spark conges:taches`
   - Démarrer dans : `D:\alefas4\RH`
4. Cocher « Exécuter même si l'utilisateur n'est pas connecté ».

## Vérification

```bash
php spark conges:taches
# Tâches congés du 2026-11-09 :
# - relance des demandes en retard (RG20)…
# …
```

Les commandes individuelles restent utilisables à la main pour rejouer une
période précise, ex. `php spark conges:acquisition 2026-11` ou
`php spark conges:cloture-annee 2026`.

> La base (Neon) n'exécute pas de cron : la commande doit tourner sur la
> machine/serveur qui a accès au code et au `.env` (connexion Neon).
