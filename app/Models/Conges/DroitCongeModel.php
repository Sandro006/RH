<?php

declare(strict_types=1);

namespace App\Models\Conges;

use CodeIgniter\Model;

/**
 * Table : droit_conge (quota par type, historisé).
 * id_type_contrat NULL = tous les contrats.
 * id_type_contrat vise type_contrat(id) (colonne `id` dans le schéma existant).
 */
class DroitCongeModel extends Model
{
    protected $table         = 'droit_conge';
    protected $primaryKey    = 'id_droit_conge';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'id_type_conge',
        'id_type_contrat',
        'nb_jours_annuel',
        'acquisition_mensuelle',
        'anciennete_min_mois',
        'date_debut',
        'date_fin',
        'actif',
    ];
    protected $useTimestamps = false;

    /**
     * Droit applicable à une date pour un type et (optionnellement) un contrat.
     * Priorité : ligne spécifique au contrat, sinon ligne « tous contrats ».
     */
    public function droitActif(int $idTypeConge, ?int $idTypeContrat, string $date): ?object
    {
        $specifique = $this->builder()
            ->where('id_type_conge', $idTypeConge)
            ->where('actif', true)
            ->where('date_debut <=', $date)
            ->groupStart()
                ->where('date_fin', null)
                ->orWhere('date_fin >=', $date)
            ->groupEnd()
            ->orderBy('date_debut', 'DESC');

        if ($idTypeContrat !== null) {
            $row = $specifique
                ->where('id_type_contrat', $idTypeContrat)
                ->get()
                ->getRow();
            if ($row !== null) {
                return $row;
            }

            $specifique = $this->builder()
                ->where('id_type_conge', $idTypeConge)
                ->where('actif', true)
                ->where('date_debut <=', $date)
                ->groupStart()
                    ->where('date_fin', null)
                    ->orWhere('date_fin >=', $date)
                ->groupEnd()
                ->orderBy('date_debut', 'DESC');
        }

        return $specifique
            ->where('id_type_contrat', null)
            ->get()
            ->getRow();
    }

    public function listerActifsParType(int $idTypeConge): array
    {
        return $this->where('id_type_conge', $idTypeConge)
            ->where('actif', true)
            ->orderBy('date_debut', 'DESC')
            ->findAll();
    }

    public function listerTous(): array
    {
        return $this->orderBy('date_debut', 'DESC')->findAll();
    }
}
