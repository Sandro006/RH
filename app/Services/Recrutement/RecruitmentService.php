<?php
declare(strict_types=1);
namespace App\Services\Recrutement;
use CodeIgniter\Database\BaseConnection;

abstract class RecruitmentService
{
    protected BaseConnection $db;
    public function __construct(?BaseConnection $db = null) { $this->db = $db ?? db_connect(); }
    protected function id(string $table, string $designation): int
    {
        $row = $this->db->table($table)->where('designation', $designation)->get()->getRowArray();
        if (!$row) throw new \RuntimeException("Statut introuvable: {$designation}");
        return (int) $row['id'];
    }
    protected function row(string $table, string $pk, int $id): array
    {
        $row = $this->db->table($table)->where($pk, $id)->get()->getRowArray();
        if (!$row) throw new \RuntimeException("Ressource introuvable: {$table} #{$id}");
        return $row;
    }
    protected function actor(?array $user): ?int { return isset($user['idUtilisateur']) ? (int)$user['idUtilisateur'] : (isset($user['id_utilisateur']) ? (int)$user['id_utilisateur'] : null); }
    protected function actorEmploye(?array $user): ?int { return isset($user['idEmploye']) && $user['idEmploye'] !== null ? (int)$user['idEmploye'] : (isset($user['id_employe']) && $user['id_employe'] !== null ? (int)$user['id_employe'] : null); }
}
