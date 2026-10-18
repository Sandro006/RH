<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;

/**
 * Model dédié aux lectures de la grille salariale
 * Aucune écriture autorisée.
 */
class GrilleModel extends Model
{
    protected $table         = 'grille_salariale';
    protected $primaryKey    = 'id_grille';
    protected $returnType    = 'object';
    protected $allowedFields = [];
    protected $useTimestamps = false;

    /**
     * Retourne la fourchette (min, max) active pour un poste donné.
     * @return array{min: float, max: float}|null
     */
    public function fourchettePoste(int $idPoste): ?array
    {
        $row = $this->where('id_poste', $idPoste)
                    ->where('date_debut <=', date('Y-m-d'))
                    ->groupStart()
                        ->where('date_fin', null)
                        ->orWhere('date_fin >=', date('Y-m-d'))
                    ->groupEnd()
                    ->orderBy('date_debut', 'DESC')
                    ->first();

        if ($row === null) {
            return null;
        }

        return [
            'min' => (float) $row->salaire_min,
            'max' => (float) $row->salaire_max,
        ];
    }
}