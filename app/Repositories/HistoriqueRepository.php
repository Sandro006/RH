<?php

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;

class HistoriqueRepository
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    public function inserer(array $historique): int
    {
        $sql = 'INSERT INTO entretien_status_historique
                    (id_entretien, statut_ancien, statut_nouveau, date_changement, id_utilisateur, commentaire)
                VALUES (?, ?, ?, ?, ?, ?) RETURNING id_historique';
        $row = $this->db->query($sql, [
            $historique['id_entretien'],
            $historique['statut_ancien'],
            $historique['statut_nouveau'],
            $historique['date_changement'],
            $historique['id_utilisateur'] ?? null,
            $historique['commentaire'] ?? null,
        ])->getRowArray();

        return (int) $row['id_historique'];
    }

    public function listerParEntretien(int $idEntretien): array
    {
        return $this->db->table('entretien_status_historique')
            ->where('id_entretien', $idEntretien)
            ->orderBy('date_changement')
            ->orderBy('id_historique')
            ->get()
            ->getResultArray();
    }
}
