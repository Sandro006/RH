<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;

/**
 * conges:requalifier-absences — requalification des maladies non justifiées (RG26).
 */
class CongesRequalifierAbsences extends BaseCommand
{
    protected $group       = 'Conges';
    protected $name        = 'conges:requalifier-absences';
    protected $description = 'Requalifie les maladies non justifiées en absences injustifiées (RG26).';
    protected $usage       = 'conges:requalifier-absences';

    public function run(array $params)
    {
        $nb = service('absenceCongeService')->requalifierMaladiesNonJustifiees();

        CLI::write("{$nb} absence(s) requalifiée(s).");
        return Commands::EXIT_SUCCESS;
    }
}
