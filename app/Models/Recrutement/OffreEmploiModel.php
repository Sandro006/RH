<?php
declare(strict_types=1);
namespace App\Models\Recrutement;
use CodeIgniter\Model;
class OffreEmploiModel extends Model
{
    protected $table = 'offre_emploi'; protected $primaryKey = 'id_offre';
    protected $returnType = 'array'; protected $useTimestamps = false;
    protected $allowedFields = ['statut','id_demande','titre','description','date_publication','date_cloture','canal_diffusion','date_creation'];
}
