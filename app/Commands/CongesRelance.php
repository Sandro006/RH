<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;

/**
 * conges:relance — relance/escalade des demandes en retard (RG20).
 * Seuil : 7 jours (cohérent avec v_demandes_en_retard du SQL).
 */
class CongesRelance extends BaseCommand
{
    protected $group       = 'Conges';
    protected $name        = 'conges:relance';
    protected $description = 'Relance les demandes de congés en attente depuis plus de 7 jours (RG20).';
    protected $usage       = 'conges:relance';

    public function run(array $params)
    {
        $limite = date('Y-m-d H:i:s', strtotime('-7 days'));

        $demandeModel = model(\App\Models\Conges\DemandeCongeModel::class);
        $retards = $demandeModel->listerEnAttenteDepuis($limite);

        foreach ($retards as $demande) {
            CLI::write("Relance demande #{$demande->id_demande_conge} (salarié #{$demande->id_salarie}, depuis {$demande->date_demande}).");
            service('notificationCongeService')->notifierRh((int) $demande->id_demande_conge, [
                'etape' => 'relance_en_retard',
            ]);
        }

        CLI::write(count($retards) . " demande(s) relancée(s).");
        return Commands::EXIT_SUCCESS;
    }
}
