<?php

declare(strict_types=1);

namespace App\Models\Conges;

use CodeIgniter\Model;

/**
 * Table : justificatif_absence (au moins une cible : absence ou demande).
 * CHECK garantit id_absence IS NOT NULL OR id_demande_conge IS NOT NULL.
 */
class JustificatifAbsenceModel extends Model
{
    protected $table         = 'justificatif_absence';
    protected $primaryKey    = 'id_justificatif';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'id_absence',
        'id_demande_conge',
        'nom_fichier',
        'chemin',
        'date_depot',
        'valide',
        'id_validateur',
    ];
    protected $useTimestamps = false;

    public function listerParAbsence(int $idAbsence): array
    {
        return $this->where('id_absence', $idAbsence)
            ->orderBy('date_depot', 'DESC')
            ->findAll();
    }

    public function listerParDemande(int $idDemandeConge): array
    {
        return $this->where('id_demande_conge', $idDemandeConge)
            ->orderBy('date_depot', 'DESC')
            ->findAll();
    }

    public function enAttenteValidation(): array
    {
        return $this->where('valide', null)
            ->orderBy('date_depot', 'DESC')
            ->findAll();
    }
}
