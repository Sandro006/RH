<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;


class PeriodeEssaiModel extends Model
{
    protected $table         = 'salarie'; // table "support" (jamais écrite ici)
    protected $primaryKey    = 'id_salarie';
    protected $returnType    = 'object';
    protected $allowedFields = [];
    protected $useTimestamps = false;

    /**
     * Lit la désignation du rang d'un poste.
     */
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

    /**
     * Somme des jours de stage antérieurs d'un salarié.
     *  Nécessite la table `stage` (migration).
     * Retourne 0 si la table n'existe pas (fallback sûr).
     */
    public function joursStageAnterieurs(int $idSalarie): int
    {
        try {
            $row = $this->db->table('stage')
                            ->selectSum('(date_fin - date_debut)', 'total_jours')
                            ->where('id_salarie', $idSalarie)
                            ->get()
                            ->getRow();
            return (int) ($row->total_jours ?? 0);
        } catch (\Throwable $e) {
            // Table `stage` absente : fallback à 0
            return 0;
        }
    }
}