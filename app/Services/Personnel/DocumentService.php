<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\DocumentModel;
use App\Models\Personnel\TypeDocumentModel;
use App\Models\Personnel\SalarieModel;
use App\Entities\Personnel\Document;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\AccesRefuseException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;

class DocumentService
{
    public function __construct(
        private readonly DocumentModel $documentModel,
        private readonly TypeDocumentModel $typeDocumentModel,
        private readonly SalarieModel $salarieModel,
        private readonly AuditService $auditService,
    ) {}

    // ajouter
    public function ajouter(
        int $idSalarie,
        int $typeDocument,
        string $nomFichier,
        string $cheminFichier,
        SessionUtilisateur $u
    ): int {
        if (!$u->aLeRole(['rh', 'recruteur', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour ajouter un document');
        }

        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        if ($this->typeDocumentModel->find($typeDocument) === null) {
            throw new ObjetIntrouvableException("Type de document #{$typeDocument} introuvable");
        }

        if (trim($cheminFichier) === '') {
            throw new DonneesIncoherentesException('cheminFichier obligatoire');
        }

        $id = $this->documentModel->insert([
            'id_salarie'    => $idSalarie,
            'type_document' => $typeDocument,
            'nom_fichier'   => $nomFichier,
            'chemin_fichier' => $cheminFichier,
            'date_depot'    => date('Y-m-d H:i:s'),
        ], true);

        if ($id === false) {
            throw new \RuntimeException('Échec insertion document : ' . json_encode($this->documentModel->errors()));
        }

        $this->auditService->journaliser(
            $u->idUtilisateur, 'CREATE', 'document', (int) $id,
            null, $u->adresseIp
        );

        return (int) $id;
    }

    // lister
    public function lister(int $idSalarie): array
    {
        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        return $this->documentModel->listerParSalarie($idSalarie);
    }

    // supprimer
    public function supprimer(int $idDocument, SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour supprimer un document');
        }

        $doc = $this->documentModel->find($idDocument);
        if ($doc === null) {
            throw new ObjetIntrouvableException("Document #{$idDocument} introuvable");
        }

        $this->documentModel->delete($idDocument);

        $this->auditService->journaliser(
            $u->idUtilisateur, 'DELETE', 'document', $idDocument,
            null, $u->adresseIp
        );
    }

    // consulter
    public function consulter(int $idDocument): ?Document
    {
        return $this->documentModel->find($idDocument);
    }

    // listerTypes
    public function listerTypes(): array
    {
        return $this->typeDocumentModel->listerTous();
    }
}