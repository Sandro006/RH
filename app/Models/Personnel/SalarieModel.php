<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;
use App\Entities\Personnel\Salarie;

class SalarieModel extends Model
{
    protected $table            = 'salarie';
    protected $primaryKey       = 'id_salarie';
    protected $useAutoIncrement = true;
    protected $returnType       = Salarie::class;
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'id_candidat', 'matricule', 'nom', 'prenom',
        'email', 'telephone',
        'date_naissance', 'lieu_naissance', 'nationalite', 'situation_familiale',
        'adresse', 'ville', 'pays',
        'date_embauche', 'date_sortie', 'statut',
    ];
    protected $useTimestamps = false;

    /**
     * Retrouve un salarié par son matricule (UNIQUE).
     */
    public function trouverParMatricule(string $matricule): ?Salarie
    {
        $s = $this->where('matricule', $matricule)->first();
        return $s;
    }

    /**
     * Retrouve le salarié lié à un candidat (évite les doublons).
     */
    public function trouverParCandidat(int $idCandidat): ?Salarie
    {
        $s = $this->where('id_candidat', $idCandidat)->first();
        return $s;
    }

    /**
     * Retourne le plus grand matricule pour une année (ex: MAT-2026-00042).
     */
    public function dernierMatriculeDeLAnnee(int $annee): ?string
    {
        $row = $this->select('matricule')
                    ->like('matricule', "MAT-{$annee}-", 'after')
                    ->orderBy('matricule', 'DESC')
                    ->first();
        return $row?->matricule;
    }
}