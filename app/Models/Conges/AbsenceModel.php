<?php

declare(strict_types=1);

namespace App\Models\Conges;

use CodeIgniter\Model;

/**
 * Table : absence (planning : générée depuis un congé validé, ou saisie directement).
 * id_saisi_par -> utilisateur.id_utilisateur (compte d'accès).
 */
class AbsenceModel extends Model
{
    protected $table         = 'absence';
    protected $primaryKey    = 'id_absence';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'id_salarie',
        'id_type_absence',
        'id_demande_conge',
        'date_debut',
        'date_fin',
        'nb_jours',
        'justifiee',
        'commentaire',
        'id_saisi_par',
        'date_saisie',
    ];
    protected $useTimestamps = false;

    public function listerParSalarie(int $idSalarie): array
    {
        return $this->where('id_salarie', $idSalarie)
            ->orderBy('date_debut', 'DESC')
            ->findAll();
    }

    public function listerParDemande(int $idDemandeConge): array
    {
        return $this->where('id_demande_conge', $idDemandeConge)
            ->orderBy('date_debut', 'ASC')
            ->findAll();
    }

    public function supprimerPourDemande(int $idDemandeConge): bool
    {
        return $this->where('id_demande_conge', $idDemandeConge)->delete();
    }

    /**
     * Absences qui chevauchent [debut, fin] pour un salarié (RG05).
     */
    public function chercherChevauchantes(int $idSalarie, string $debut, string $fin): array
    {
        return $this->where('id_salarie', $idSalarie)
            ->where('date_debut <=', $fin)
            ->where('date_fin >=', $debut)
            ->orderBy('date_debut', 'ASC')
            ->findAll();
    }

    /**
     * Absences terminées depuis $limite (Y-m-d) et toujours non justifiées
     * (utilisé par v_absences_non_justifiees / requalification RG26).
     */
    public function listerNonJustifieesDepassees(string $limite): array
    {
        return $this->where('justifiee', false)
            ->where('date_fin <', $limite)
            ->orderBy('date_fin', 'ASC')
            ->findAll();
    }

    public function listerEnCours(string $date): array
    {
        return $this->where('date_debut <=', $date)
            ->where('date_fin >=', $date)
            ->orderBy('id_salarie', 'ASC')
            ->findAll();
    }
}
