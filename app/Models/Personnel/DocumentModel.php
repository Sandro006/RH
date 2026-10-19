<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;
use App\Entities\Personnel\Document;

class DocumentModel extends Model
{
    protected $table         = 'document';
    protected $primaryKey    = 'id_document';
    protected $returnType    = Document::class;
    protected $allowedFields = [
        'id_salarie', 'type_document', 'nom_fichier', 'chemin_fichier', 'date_depot',
    ];
    protected $useTimestamps = false;

    // listerParSalarie
    public function listerParSalarie(int $idSalarie): array
    {
        return $this->where('id_salarie', $idSalarie)
                    ->orderBy('date_depot', 'DESC')
                    ->findAll();
    }

    // trouverParSalarieEtType
    public function trouverParSalarieEtType(int $idSalarie, int $typeDocument): array
    {
        return $this->where('id_salarie', $idSalarie)
                    ->where('type_document', $typeDocument)
                    ->findAll();
    }
}