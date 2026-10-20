<?php

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;

class EntretienRepository
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    public function inserer(array $e): int
    {
        $sql = 'INSERT INTO entretien (id_candidature, id_recruteur, date_entretien, type_entretien, statut)
                VALUES (?, ?, ?, ?, ?) RETURNING id_entretien';
        $row = $this->db->query($sql, [
            $e['id_candidature'],
            $e['id_recruteur'],
            $e['date_entretien'],
            $e['type_entretien'],
            $e['statut'],
        ])->getRowArray();

        return (int) $row['id_entretien'];
    }

    public function trouverParId(int $id): ?array
    {
        return $this->db->table('entretien')
            ->where('id_entretien', $id)
            ->get()
            ->getRowArray();
    }

    public function listerParCandidature(int $idCandidature): array
    {
        return $this->db->table('entretien')
            ->where('id_candidature', $idCandidature)
            ->orderBy('date_entretien')
            ->get()
            ->getResultArray();
    }

    public function lister(array $f): array
    {
        $b = $this->db->table('entretien e')
            ->select('e.*, c.id_offre')
            ->join('candidature c', 'c.id_candidature = e.id_candidature');

        if (!empty($f['idOffre'])) {
            $b->where('c.id_offre', $f['idOffre']);
        }
        if (!empty($f['idRecruteur'])) {
            $b->where('e.id_recruteur', $f['idRecruteur']);
        }
        if (!empty($f['statut'])) {
            $b->where('e.statut', $f['statut']);
        }
        if (!empty($f['dateDebut'])) {
            $b->where('e.date_entretien >=', $f['dateDebut']);
        }
        if (!empty($f['dateFin'])) {
            $b->where('e.date_entretien <=', $f['dateFin'] . ' 23:59:59');
        }

        return $b->orderBy('e.date_entretien')->get()->getResultArray();
    }

    public function mettreAJourCompteRendu(int $id, string $cr): void
    {
        $this->db->table('entretien')
            ->where('id_entretien', $id)
            ->update(['compte_rendu' => $cr]);
    }

    public function mettreAJourStatut(int $id, int $idStatut): void
    {
        $this->db->table('entretien')
            ->where('id_entretien', $id)
            ->update(['statut' => $idStatut]);
    }

    public function mettreAJourDate(int $id, string $date): void
    {
        $this->db->table('entretien')
            ->where('id_entretien', $id)
            ->update(['date_entretien' => $date]);
    }
}
