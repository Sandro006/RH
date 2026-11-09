<?php

declare(strict_types=1);

namespace App\Models\Conges;

use CodeIgniter\Model;

/**
 * Table : solde_conge (UNIQUE id_salarie, id_type_conge, annee).
 * Disponible = droit_acquis + report - pris - en_attente (calculé, jamais stocké).
 * CHECK garantit disponible >= 0 côté base (sauf avance autorisée : voir SQL).
 */
class SoldeCongeModel extends Model
{
    protected $table         = 'solde_conge';
    protected $primaryKey    = 'id_solde_conge';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'id_salarie',
        'id_type_conge',
        'annee',
        'droit_acquis',
        'report',
        'pris',
        'en_attente',
    ];
    protected $useTimestamps = false;

    public function trouver(int $idSalarie, int $idTypeConge, int $annee): ?object
    {
        return $this->where('id_salarie', $idSalarie)
            ->where('id_type_conge', $idTypeConge)
            ->where('annee', $annee)
            ->first();
    }

    /**
     * Verrouille la ligne de solde (SELECT ... FOR UPDATE) pour
     * SoldeService (création de demande, débit, recrédit).
     * À appeler DANS une transaction (voir TODO 4.5).
     */
    public function verrouiller(int $idSalarie, int $idTypeConge, int $annee): ?object
    {
        $row = $this->db->query(
            'SELECT * FROM solde_conge
             WHERE id_salarie = ? AND id_type_conge = ? AND annee = ?
             FOR UPDATE',
            [$idSalarie, $idTypeConge, $annee]
        )->getRow();

        return $row;
    }

    public function listerParSalarie(int $idSalarie): array
    {
        return $this->where('id_salarie', $idSalarie)
            ->orderBy('annee', 'DESC')
            ->orderBy('id_type_conge', 'ASC')
            ->findAll();
    }

    public function listerParSalarieEtAnnee(int $idSalarie, int $annee): array
    {
        return $this->where('id_salarie', $idSalarie)
            ->where('annee', $annee)
            ->findAll();
    }

    /**
     * Crée la ligne de solde si elle n'existe pas (défaut 0 partout).
     * Utilisé par SoldeService avant réservation / acquisition.
     */
    public function assurer(int $idSalarie, int $idTypeConge, int $annee): object
    {
        $existant = $this->trouver($idSalarie, $idTypeConge, $annee);
        if ($existant !== null) {
            return $existant;
        }

        $this->insert([
            'id_salarie'    => $idSalarie,
            'id_type_conge' => $idTypeConge,
            'annee'         => $annee,
            'droit_acquis'  => 0,
            'report'        => 0,
            'pris'          => 0,
            'en_attente'    => 0,
        ], true);

        $ligne = $this->trouver($idSalarie, $idTypeConge, $annee);
        if ($ligne === null) {
            throw new \RuntimeException('Impossible de créer la ligne de solde');
        }

        return $ligne;
    }

    /**
     * Calcule le disponible sans l'écrire en base.
     */
    public function disponible(object $solde): float
    {
        return (float) $solde->droit_acquis + (float) $solde->report
            - (float) $solde->pris - (float) $solde->en_attente;
    }
}
