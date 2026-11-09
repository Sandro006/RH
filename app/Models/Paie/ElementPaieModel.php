<?php

declare(strict_types=1);

namespace App\Models\Paie;

use CodeIgniter\Model;

/**
 * Table : element_paie (éléments variables de paie par salarié et par mois).
 * type_element : PRIME, HEURE_SUP, RETTENUE, AVANTAGE, AUTRE.
 * montant signé : positif = gain, négatif = retenue.
 */
class ElementPaieModel extends Model
{
    protected $table         = 'element_paie';
    protected $primaryKey    = 'id_element_paie';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'id_salarie',
        'mois',
        'type_element',
        'libelle',
        'montant',
        'commentaire',
        'id_saisi_par',
        'date_saisie',
    ];
    protected $useTimestamps = false;

    public function listerParSalarieAndMois(int $idSalarie, string $mois): array
    {
        return $this->where('id_salarie', $idSalarie)
            ->where('mois', $mois)
            ->orderBy('date_saisie', 'ASC')
            ->findAll();
    }

    public function sommeParSalarieAndMois(int $idSalarie, string $mois): float
    {
        $ligne = $this->db->table('element_paie')
            ->select('COALESCE(SUM(montant), 0) AS total')
            ->where('id_salarie', $idSalarie)
            ->where('mois', $mois)
            ->get()
            ->getRow();

        return $ligne !== null ? (float) $ligne->total : 0.0;
    }
}
