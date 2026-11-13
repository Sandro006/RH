<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;

/**
 * conges:taches — point d'entrée unique des tâches planifiées Congés.
 *
 * À appeler chaque jour par le cron système (voir script/taches-planifiees.md) :
 *   php spark conges:taches
 *
 * Exécute, selon la date du jour :
 *   - chaque jour : conges:relance (RG20) + conges:requalifier-absences (RG26)
 *   - le 1er du mois : conges:acquisition du mois courant (RG11)
 *   - le 1er janvier : conges:cloture-annee de l'année précédente (RG12)
 *
 * Les commandes individuelles restent utilisables manuellement
 * (ex. `php spark conges:acquisition 2026-11`).
 */
class CongesTaches extends BaseCommand
{
    protected $group       = 'Conges';
    protected $name        = 'conges:taches';
    protected $description = 'Tâches planifiées quotidiennes/mensuelles/annuelles des congés (à appeler par cron chaque jour).';
    protected $usage       = 'conges:taches';

    public function run(array $params)
    {
        $aujourdhui = date('Y-m-d');
        CLI::write("Tâches congés du {$aujourdhui} :");

        CLI::write('- relance des demandes en retard (RG20)…');
        $this->call('conges:relance');

        CLI::write('- requalification des maladies non justifiées (RG26)…');
        $this->call('conges:requalifier-absences');

        if (date('d') === '01') {
            CLI::write('- acquisition mensuelle (RG11)…');
            $this->call('conges:acquisition', [date('Y-m')]);
        }

        if (date('m-d') === '01-01') {
            $anneePrecedente = (int) date('Y') - 1;
            CLI::write("- clôture de l'année {$anneePrecedente} (RG12)…");
            $this->call('conges:cloture-annee', [(string) $anneePrecedente]);
        }

        CLI::write('Tâches congés terminées.');

        return Commands::EXIT_SUCCESS;
    }
}
