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
<<<<<<< HEAD
=======

    /**
     * Insère un employé rattaché à un salarié et à un poste.
     * Table : employe (id, nom, date_de_naissance, poste_id, id_salarie)
     */
    public function insererDepuisSalarie(int $idSalarie, int $idPoste, string $nom, string $dateDeNaissance): int
    {
        $db = $this->db;
        $db->transStart();

        try {
            $db->table($this->table)->insert([
                'nom'               => $nom,
                'date_de_naissance' => $dateDeNaissance,
                'poste_id'          => $idPoste,
                'id_salarie'        => $idSalarie,
            ]);

            $id = (int) $db->insertID();
            $db->transComplete();

            return $id;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Retourne le nom du département d'un employé (via poste -> service -> departement).
     */
    public function getDepartementDeEmploye(int $idEmploye): ?string
    {
        $row = $this->db->table('employe e')
            ->select('d.nom AS departement')
            ->join('poste p', 'p.id_poste = e.poste_id')
            ->join('service s', 's.id_service = p.id_service')
            ->join('departement d', 'd.id_departement = s.id_departement')
            ->where('e.id', $idEmploye)
            ->get()
            ->getRow();

        return $row !== null ? (string) $row->departement : null;
    }
>>>>>>> 57f1b34f20901561d84f17b8b34950d5e25ea61e
}
