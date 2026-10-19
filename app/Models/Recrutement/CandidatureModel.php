<?php
declare(strict_types=1);
namespace App\Models\Recrutement;
use CodeIgniter\Model;
class CandidatureModel extends Model
{
    protected $table = 'candidature'; protected $primaryKey = 'id_candidature';
    protected $returnType = 'array'; protected $useTimestamps = false;
    protected $allowedFields = ['id_candidat','id_offre','date_candidature','cv','lettre_motivation','origine_candidature','statut'];
}
