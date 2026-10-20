<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

class PeriodeEssaiModel extends Model
{
    protected $table         = 'salarie';
    protected $primaryKey    = 'id_salarie';
    protected $returnType    = 'object';
    protected $allowedFields = [];
    protected $useTimestamps = false;

    // rangDuPoste
    public function rangDuPoste(int $idPoste): ?string
    {
        $row = $this->db->table('poste')
                        ->select('rang.designation AS designation')
                        ->join('rang', 'rang.id = poste.rang_id', 'left')
                        ->where('poste.id_poste', $idPoste)
                        ->get()
                        ->getRow();
        return $row?->designation;
    }

    // joursStageAnterieurs
    public function joursStageAnterieurs(int $idSalarie, ?string $dateDebutContratActuel = null): int
    {
        $typeStage = 3; // type_contrat "Stage"

        $builder = $this->db->table('contrat')
                            ->selectSum('(date_fin - date_debut)', 'total_jours')
                            ->where('id_salarie', $idSalarie)
                            ->where('type_contrat_id', $typeStage)
                            ->where('date_fin IS NOT NULL');

        if ($dateDebutContratActuel !== null) {
            $builder->where('date_fin <', $dateDebutContratActuel);
        }

        $row = $builder->get()->getRow();

        return (int) ($row->total_jours ?? 0);
    }
}