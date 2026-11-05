<?php
declare(strict_types=1);
namespace App\Models\Recrutement;
use CodeIgniter\Model;
class CandidatModel extends Model
{
    protected $table = 'candidat'; protected $primaryKey = 'id_candidat';
    protected $returnType = 'array'; protected $useTimestamps = false;
    protected $allowedFields = ['nom','prenom','email','telephone','adresse','date_creation'];
}
