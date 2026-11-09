<?php

declare(strict_types=1);

namespace App\Models\Paie;

use CodeIgniter\Model;

/**
 * Table : bulletin_paie (snapshot brut / cotisations / net / patronal par mois).
 * Calculé par BulletinService, jamais recalculé après validation RH.
 */
class BulletinPaieModel extends Model
{
    protected $table         = 'bulletin_paie';
    protected $primaryKey    = 'id_bulletin';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'id_salarie',
        'mois',
        'salaire_base',
        'jours_ouvres_mois',
        'jours_paye',
        'jours_sans_solde',
        'jours_injustifies',
        'retenue_conges',
        'elements_paie',
        'brut',
        'cotisations_employe',
        'net',
        'cotisations_patronal',
        'taux_employe_applique',
        'taux_patronal_applique',
        'date_calcul',
        'id_valideur',
        'date_validation',
    ];
    protected $useTimestamps = false;

    public function trouver(int $idSalarie, string $mois): ?object
    {
        return $this->where('id_salarie', $idSalarie)
            ->where('mois', $mois)
            ->first();
    }

    public function listerParMois(string $mois): array
    {
        return $this->where('mois', $mois)
            ->orderBy('id_salarie', 'ASC')
            ->findAll();
    }

    public function listerParSalarie(int $idSalarie, ?string $mois = null): array
    {
        $builder = $this->where('id_salarie', $idSalarie)
            ->orderBy('mois', 'DESC');

        if ($mois !== null) {
            $builder->where('mois', $mois);
        }

        return $builder->findAll();
    }
}
