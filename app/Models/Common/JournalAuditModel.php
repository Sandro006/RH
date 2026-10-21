<?php

declare(strict_types=1);

namespace App\Models\Common;

use App\Entities\Admin\JournalAudit;
use CodeIgniter\Model;

class JournalAuditModel extends Model
{
    protected $table            = 'journal_audit';
    protected $primaryKey       = 'id_audit';
    protected $useAutoIncrement = true;
    protected $returnType       = JournalAudit::class;
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id_utilisateur',
        'action',
        'entite',
        'id_entite',
        'ancienne_valeur',
        'nouvelle_valeur',
        'details',
        'adresse_ip',
    ];

    public function inserer(array $donnees): int
    {
        return (int) $this->insert($donnees, true);
    }

    public function listerParUtilisateur(int $idUtilisateur): array
    {
        return $this->where('id_utilisateur', $idUtilisateur)
            ->orderBy('date_action', 'DESC')
            ->findAll();
    }

    public function listerParEntite(string $entite, string $idEntite): array
    {
        return $this->where('entite', $entite)
            ->where('id_entite', $idEntite)
            ->orderBy('date_action', 'DESC')
            ->findAll();
    }
}
