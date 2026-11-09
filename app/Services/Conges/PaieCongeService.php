<?php

declare(strict_types=1);

namespace App\Services\Conges;

/**
 * PaieCongeService (RG25) : variables de paie + planning d'équipe.
 * Lit les vues v_variables_paie_conges / v_planning_absences (SQL lecture seule).
 * Limitation : les demi-journées comptent 1 jour dans v_variables_paie_conges
 * (cf. header SQL) ; le Service ajuste les congés à demi-journées via
 * CalendrierService::repartirParMois quand nécessaire (à brancher selon besoin).
 */
class PaieCongeService
{
    public function __construct(
        private readonly CalendrierService $calendrier,
    ) {
    }

    /**
     * @return array{jours_payes: float, jours_sans_solde: float, jours_injustifies: float}
     */
    public function variablesPaie(int $idSalarie, string $mois): array
    {
        // v_variables_paie_conges est par mois (date_trunc) ; le paramètre $mois
        // = 'Y-m' ou 'Y-m-01'. Onlit la ligne du mois (vue pourvue de 0 si absente).
        $premier = date('Y-m-01', strtotime($mois));

        $ligne = $this->calendrierDb()->table('v_variables_paie_conges')
            ->where('id_salarie', $idSalarie)
            ->where('mois', $premier)
            ->get()
            ->getRow();

        if ($ligne === null) {
            return ['jours_payes' => 0.0, 'jours_sans_solde' => 0.0, 'jours_injustifies' => 0.0];
        }

        return [
            'jours_payes'       => (float) $ligne->jours_payes,
            'jours_sans_solde'  => (float) $ligne->jours_sans_solde,
            'jours_injustifies' => (float) $ligne->jours_injustifies,
        ];
    }

    public function planningEquipe(int $idService, string $debut, string $fin): array
    {
        return $this->calendrierDb()->table('v_planning_absences')
            ->where('id_service', $idService)
            ->where('jour >=', $debut)
            ->where('jour <=', $fin)
            ->orderBy('jour', 'ASC')
            ->orderBy('nom', 'ASC')
            ->get()
            ->getResult();
    }

    private function calendrierDb(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }
}
