<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;

/**
 * conges:acquisition — acquisition mensuelle (RG11).
 * Usage : php spark conges:acquisition 2026-11
 * Sans argument : mois courant.
 */
class CongesAcquisition extends BaseCommand
{
    protected $group       = 'Conges';
    protected $name        = 'conges:acquisition';
    protected $description = 'Crédite les acquisitions mensuelles de congés (RG11).';
    protected $usage       = 'conges:acquisition [YYYY-MM]';

    public function run(array $params)
    {
        $mois = $params[0] ?? null;
        if ($mois !== null && ! preg_match('/^\d{4}-\d{2}$/', $mois)) {
            CLI::error('Format attendu : YYYY-MM');
            return Commands::EXIT_ERROR;
        }
        $mois ??= date('Y-m');

        $soldeService = service('soldeCongeService');
        $nb = $soldeService->acquerirMensuel($mois);

        CLI::write("Acquisition {$mois} : {$nb} ligne(s) créditée(s).");
        return Commands::EXIT_SUCCESS;
    }
}
