<?php

declare(strict_types=1);

namespace App\Models\Conges;

use CodeIgniter\Model;

/**
 * Table : demande_conge (nouveau schéma Bloc 7).
 * PK : id_demande_conge (renommée depuis id_conge par 08_migration_conges_bloc7.sql).
 * Les colonnes héritées (id_utilisateur, id_employe, type_conge, justification,
 * statut, date_creation) ne sont PAS dans allowedFields : le nouveau workflow
 * écrit id_salarie / id_type_conge / id_statut. Le Model App\Models\Personnel\DemandeCongeModel
 * reste en legacy jusqu'au réécriture des Services (étape 6).
 */
class DemandeCongeModel extends Model
{
    protected $table         = 'demande_conge';
    protected $primaryKey    = 'id_demande_conge';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'id_salarie',
        'id_type_conge',
        'id_statut',
        'date_debut',
        'date_fin',
        'demi_journee_debut',
        'demi_journee_fin',
        'nb_jours',
        'motif',
        'date_demande',
        'id_manager_valideur',
        'date_validation_manager',
        'commentaire_manager',
        'id_rh_valideur',
        'date_validation_rh',
        'commentaire_rh',
        'motif_refus',
        'date_annulation',
    ];
    protected $useTimestamps = false;

    public function listerParSalarie(int $idSalarie): array
    {
        return $this->where('id_salarie', $idSalarie)
            ->orderBy('date_debut', 'DESC')
            ->findAll();
    }

    public function listerParStatutCode(string $codeStatut): array
    {
        return $this->builder()
            ->select('demande_conge.*')
            ->join('statut_demande_conge', 'statut_demande_conge.id_statut = demande_conge.id_statut')
            ->where('statut_demande_conge.code', $codeStatut)
            ->orderBy('demande_conge.date_demande', 'ASC')
            ->get()
            ->getResult();
    }

    public function listerEnAttenteDepuis(string $dateLimite): array
    {
        return $this->builder()
            ->select('demande_conge.*')
            ->join('statut_demande_conge', 'statut_demande_conge.id_statut = demande_conge.id_statut')
            ->whereIn('statut_demande_conge.code', ['EN_ATTENTE_MANAGER', 'EN_ATTENTE_RH'])
            ->where('demande_conge.date_demande <', $dateLimite)
            ->orderBy('demande_conge.date_demande', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * Demandes d'un salarié qui chevauchent [debut, fin] avec une statut donné
     * (utilisé par ControleDemandeService::aChevauchement, RG05).
     * $exclureId permet d'ignorer la demande en cours de transition.
     *
     * @param list<string> $codesStatut
     */
    public function chercherChevauchantes(
        int $idSalarie,
        string $debut,
        string $fin,
        ?int $exclureId = null,
        array $codesStatut = ['EN_ATTENTE_MANAGER', 'EN_ATTENTE_RH', 'VALIDEE'],
    ): array {
        $builder = $this->builder()
            ->select('demande_conge.*')
            ->join('statut_demande_conge', 'statut_demande_conge.id_statut = demande_conge.id_statut')
            ->where('demande_conge.id_salarie', $idSalarie)
            ->where('demande_conge.date_debut <=', $fin)
            ->where('demande_conge.date_fin >=', $debut)
            ->whereIn('statut_demande_conge.code', $codesStatut);

        if ($exclureId !== null) {
            $builder->where('demande_conge.id_demande_conge !=', $exclureId);
        }

        return $builder->get()->getResult();
    }

    public function countChevauchantes(
        int $idSalarie,
        string $debut,
        string $fin,
        ?int $exclureId = null,
        array $codesStatut = ['EN_ATTENTE_MANAGER', 'EN_ATTENTE_RH', 'VALIDEE'],
    ): int {
        return count($this->chercherChevauchantes($idSalarie, $debut, $fin, $exclureId, $codesStatut));
    }
}
