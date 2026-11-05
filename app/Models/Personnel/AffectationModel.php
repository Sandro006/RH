<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;
use App\Entities\Personnel\Affectation;

class AffectationModel extends Model
{
    protected $table         = 'affectation';
    protected $primaryKey    = 'id_affectation';
    protected $returnType    = Affectation::class;
    protected $allowedFields = [
        'id_salarie', 'id_poste', 'date_debut', 'date_fin', 'motif',
    ];
    protected $useTimestamps = false;

    public function listerParSalarie(int $idSalarie): array
    {
        return $this->db->table('affectation a')
            ->select('a.id_affectation, a.id_salarie, a.id_poste, a.date_debut, a.date_fin, a.motif, p.intitule AS poste, d.nom AS departement')
            ->join('poste p', 'p.id_poste = a.id_poste', 'left')
            ->join('service sv', 'sv.id_service = p.id_service', 'left')
            ->join('departement d', 'd.id_departement = sv.id_departement', 'left')
            ->where('a.id_salarie', $idSalarie)
            ->orderBy('a.date_debut', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Retourne l'affectation en cours (date_fin = NULL), s'il y en a une.
     */
    public function affectationEnCours(int $idSalarie): ?Affectation
    {
        /** @var Affectation|null $a */
        $a = $this->where('id_salarie', $idSalarie)
                  ->where('date_fin', null)
                  ->first();
        return $a;
    }
}
