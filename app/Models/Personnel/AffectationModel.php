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
        return $this->where('id_salarie', $idSalarie)
                    ->orderBy('date_debut', 'DESC')
                    ->findAll();
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