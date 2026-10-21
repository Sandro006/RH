<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Entities\Admin\Employe;
use CodeIgniter\Model;

class EmployeModel extends Model
{
    protected $table            = 'employe';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Employe::class;
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = [];

    public function listerPourSelect(): array
    {
        $lignes = $this->db->table($this->table . ' e')
            ->select('e.id, e.nom, e.poste_id, e.id_salarie, p.intitule AS poste')
            ->join('poste p', 'p.id_poste = e.poste_id', 'left')
            ->orderBy('e.nom', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $ligne): array => [
            'id'         => (int) $ligne['id'],
            'libelle'    => $ligne['nom'],
            'idPoste'    => $ligne['poste_id'] !== null ? (int) $ligne['poste_id'] : null,
            'poste'      => $ligne['poste'],
            'idSalarie'  => $ligne['id_salarie'] !== null ? (int) $ligne['id_salarie'] : null,
        ], $lignes);
    }

    public function existe(int $id): bool
    {
        return $this->db->table($this->table)->where('id', $id)->countAllResults() > 0;
    }

    public function trouverParId(int $id): ?Employe
    {
        return $this->find($id);
    }

    public function trouverParSalarie(int $idSalarie): ?Employe
    {
        return $this->where('id_salarie', $idSalarie)->first();
    }
}
