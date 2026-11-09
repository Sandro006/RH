<?php
declare(strict_types=1);
namespace App\Models\Recrutement;
use CodeIgniter\Model;

class DemandeRecrutementModel extends Model
{
    protected $table = 'demande_recrutement'; protected $primaryKey = 'id_demande';
    protected $returnType = 'array'; protected $useTimestamps = false;
    protected $allowedFields = ['id_poste','id_demandeur','motif','id_profil','nom_personne_remplacee','type_contrat','duree_contrat','missions','date_souhaitee','remuneration_min','remuneration_max','statut','date_creation'];
}
