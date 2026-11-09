<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;

/**
 * conges:cloture-annee — clôture de fin d'année (RG12).
 * Usage : php spark conges:cloture-annee [AAAA] (défaut : année courante)
 */
class CongesClotureAnnee extends BaseCommand
{
    protected $group       = 'Conges';
    protected $name        = 'conges:cloture-annee';
    protected $description = 'Clôture les soldes de l’année (report + expiration, RG12).';
    protected $usage       = 'conges:cloture-annee [AAAA]';

    public function run(array $params)
    {
        $annee = isset($params[0]) ? (int) $params[0] : (int) date('Y');

        service('soldeCongeService')->cloturerAnnee($annee);

        CLI::write("Clôture {$annee} terminée (report + expiration).");
        return Commands::EXIT_SUCCESS;
    }
}
