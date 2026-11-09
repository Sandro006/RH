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
        'date_emission', 'date_expiration', 'niveau_confidentialite', 'id_utilisateur_depot',
        'mime_type', 'taille_octets', 'checksum_sha256', 'remplace_par', 'archive',
    ];
    protected $useTimestamps = false;

    // listerParSalarie
    public function listerParSalarie(int $idSalarie): array
    {
        return $this->where('id_salarie', $idSalarie)
                    ->orderBy('date_depot', 'DESC')
                    ->findAll();
    }

    // documentsActifs
    public function documentsActifs(int $idSalarie): array
    {
        return $this->where('id_salarie', $idSalarie)
                    ->where('archive', false)
                    ->orderBy('date_depot', 'DESC')
                    ->findAll();
    }

    // listerAvecType
    public function listerAvecType(int $idSalarie, bool $inclureArchives = false): array
    {
        $requete = $this->select('document.*, type_document.designation AS type_designation, '
                . 'type_document.obligatoire AS type_obligatoire, '
                . 'type_document.duree_validite_mois AS type_duree_validite_mois, '
                . 'type_document.niveau_confidentialite AS type_niveau_confidentialite')
            ->join('type_document', 'type_document.id = document.type_document', 'left')
            ->where('document.id_salarie', $idSalarie);

        if (!$inclureArchives) {
            $requete->where('document.archive', false);
        }

        return $requete->orderBy('document.date_depot', 'DESC')->findAll();
    }

    // trouverParSalarieEtType
    public function trouverParSalarieEtType(int $idSalarie, int $typeDocument): array
    {
        return $this->where('id_salarie', $idSalarie)
                    ->where('type_document', $typeDocument)
                    ->where('archive', false)
                    ->orderBy('date_depot', 'DESC')
                    ->findAll();
    }

    // documentsParType
    public function documentsParType(int $idSalarie, int $typeDocument): array
    {
        return $this->trouverParSalarieEtType($idSalarie, $typeDocument);
    }

    // documentsExpirant
    public function documentsExpirant(int $jours = 30, ?int $idSalarie = null): array
    {
        $requete = $this->where('archive', false)
            ->where('date_expiration IS NOT NULL', null, false)
            ->where('date_expiration >=', date('Y-m-d'))
            ->where('date_expiration <=', date('Y-m-d', strtotime("+{$jours} days")));

        if ($idSalarie !== null) {
            $requete->where('id_salarie', $idSalarie);
        }

        return $requete->orderBy('date_expiration', 'ASC')->findAll();
    }

    // documentsExpires
    public function documentsExpires(?int $idSalarie = null): array
    {
        $requete = $this->where('archive', false)
            ->where('date_expiration IS NOT NULL', null, false)
            ->where('date_expiration <', date('Y-m-d'));

        if ($idSalarie !== null) {
            $requete->where('id_salarie', $idSalarie);
        }

        return $requete->orderBy('date_expiration', 'ASC')->findAll();
    }
}