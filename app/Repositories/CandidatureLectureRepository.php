<?php

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;

class CandidatureLectureRepository
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    public function trouverParId(int $idCandidature): ?array
    {
        return $this->db->table('candidature c')
            ->select('c.*, sc.designation AS statut_libelle')
            ->join('status_candidature sc', 'sc.id = c.statut')
            ->where('c.id_candidature', $idCandidature)
            ->get()
            ->getRowArray();
    }

    public function changerStatut(int $idCandidature, string $designation): void
    {
        $statut = $this->db->table('status_candidature')
            ->select('id')
            ->where('designation', $designation)
            ->get()
            ->getRowArray();

        if ($statut === null) {
            throw new \RuntimeException("Statut de candidature inconnu : {$designation}");
        }

        $this->db->table('candidature')
            ->where('id_candidature', $idCandidature)
            ->update(['statut' => (int) $statut['id']]);

        if ($this->db->affectedRows() === 0 && $this->trouverParId($idCandidature) === null) {
            throw new \RuntimeException('Candidature introuvable');
        }
    }
}
