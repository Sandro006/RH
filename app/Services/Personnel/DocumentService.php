<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Entities\Personnel\Document;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Personnel\DocumentPolicy;
use App\Libraries\Personnel\StockageService;
use App\Models\Personnel\DocumentModel;
use App\Models\Personnel\SalarieModel;
use App\Models\Personnel\TypeDocumentModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class DocumentService
{
    private const NIVEAUX = ['public', 'interne', 'rh', 'restreint'];

    public function __construct(
        private readonly DocumentModel $documentModel,
        private readonly TypeDocumentModel $typeDocumentModel,
        private readonly SalarieModel $salarieModel,
        private readonly AuditService $auditService,
        private readonly StockageService $stockageService,
        private readonly DocumentPolicy $documentPolicy,
    ) {
    }

    /**
     * Dépose une nouvelle pièce : validation, stockage réel puis insertion.
     *
     * @param array{
     *     nom_fichier?: string|null,
     *     date_emission?: string|null,
     *     date_expiration?: string|null,
     *     niveau_confidentialite?: string|null
     * } $donnees
     */
    public function ajouter(
        int $idSalarie,
        int $typeDocument,
        UploadedFile $fichier,
        array $donnees,
        SessionUtilisateur $u
    ): int {
        if (!$this->documentPolicy->peutAjouter($u)) {
            throw new AccesRefuseException('Rôle insuffisant pour ajouter un document');
        }

        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        $type = $this->typeDocumentModel->find($typeDocument);

        if ($type === null) {
            throw new ObjetIntrouvableException("Type de document #{$typeDocument} introuvable");
        }

        if (isset($type->actif) && !$this->estVrai($type->actif)) {
            throw new DonneesIncoherentesException('Ce type de document est désactivé');
        }

        $dates  = $this->validerDates($donnees);
        $niveau = $this->niveauDemande($donnees, $type->niveau_confidentialite ?? 'interne');
        $stocke = $this->stockageService->stocker($fichier);

        try {
            $id = $this->documentModel->insert([
                'id_salarie'             => $idSalarie,
                'type_document'          => $typeDocument,
                'nom_fichier'            => $this->nomAffiche($donnees, $stocke['nom_original']),
                'chemin_fichier'         => $stocke['chemin_fichier'],
                'date_depot'             => date('Y-m-d H:i:s'),
                'date_emission'          => $dates['date_emission'],
                'date_expiration'        => $dates['date_expiration'],
                'niveau_confidentialite' => $niveau,
                'id_utilisateur_depot'   => $u->idUtilisateur,
                'mime_type'              => $stocke['mime_type'],
                'taille_octets'          => $stocke['taille_octets'],
                'checksum_sha256'        => $stocke['checksum_sha256'],
                'remplace_par'           => null,
                'archive'                => false,
            ], true);
        } catch (\Throwable $e) {
            $this->stockageService->supprimer($stocke['chemin_fichier']);
            throw $e;
        }

        if ($id === false) {
            $this->stockageService->supprimer($stocke['chemin_fichier']);

            throw new \RuntimeException('Échec insertion document : ' . json_encode($this->documentModel->errors()));
        }

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'CREATE',
            'document',
            (int) $id,
            [
                'idSalarie'             => $idSalarie,
                'typeDocument'          => $typeDocument,
                'niveauConfidentialite' => $niveau,
                'nomFichier'            => $stocke['nom_original'],
            ],
            $u->adresseIp
        );

        return (int) $id;
    }

    /**
     * Remplace une pièce : nouveau fichier actif, ancienne pièce archivée.
     * L'ancien fichier est conservé pour la traçabilité.
     *
     * @param array{
     *     nom_fichier?: string|null,
     *     date_emission?: string|null,
     *     date_expiration?: string|null,
     *     niveau_confidentialite?: string|null
     * } $donnees
     */
    public function remplacer(
        int $idDocument,
        UploadedFile $fichier,
        array $donnees,
        SessionUtilisateur $u
    ): int {
        if (!$this->documentPolicy->peutRemplacer($u)) {
            throw new AccesRefuseException('Rôle insuffisant pour remplacer un document');
        }

        $ancien = $this->documentModel->find($idDocument);

        if ($ancien === null) {
            throw new ObjetIntrouvableException("Document #{$idDocument} introuvable");
        }

        $type = $this->typeDocumentModel->find((int) $ancien->type_document);

        if ($type === null) {
            throw new ObjetIntrouvableException("Type de document #{$ancien->type_document} introuvable");
        }

        $dates = $this->validerDates([
            'date_emission'   => $donnees['date_emission'] ?? $ancien->date_emission?->format('Y-m-d'),
            'date_expiration' => $donnees['date_expiration'] ?? $ancien->date_expiration?->format('Y-m-d'),
        ]);

        $niveau = $this->niveauDemande(
            ['niveau_confidentialite' => $donnees['niveau_confidentialite'] ?? $ancien->niveau_confidentialite],
            $type->niveau_confidentialite ?? 'interne'
        );

        $stocke   = $this->stockageService->stocker($fichier);
        $nouvelId = null;

        try {
            $nouvelId = $this->documentModel->insert([
                'id_salarie'             => (int) $ancien->id_salarie,
                'type_document'          => (int) $ancien->type_document,
                'nom_fichier'            => $this->nomAffiche($donnees, $stocke['nom_original']),
                'chemin_fichier'         => $stocke['chemin_fichier'],
                'date_depot'             => date('Y-m-d H:i:s'),
                'date_emission'          => $dates['date_emission'],
                'date_expiration'        => $dates['date_expiration'],
                'niveau_confidentialite' => $niveau,
                'id_utilisateur_depot'   => $u->idUtilisateur,
                'mime_type'              => $stocke['mime_type'],
                'taille_octets'          => $stocke['taille_octets'],
                'checksum_sha256'        => $stocke['checksum_sha256'],
                'remplace_par'           => null,
                'archive'                => false,
            ], true);

            if ($nouvelId === false) {
                throw new \RuntimeException('Échec insertion du document de remplacement');
            }

            if ($this->documentModel->update($idDocument, [
                'archive'      => true,
                'remplace_par' => (int) $nouvelId,
            ]) === false) {
                throw new \RuntimeException("Échec de l'archivage du document remplacé");
            }
        } catch (\Throwable $e) {
            $this->stockageService->supprimer($stocke['chemin_fichier']);

            if ($nouvelId !== false && $nouvelId !== null) {
                $this->documentModel->delete((int) $nouvelId);
            }

            throw $e;
        }

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'UPDATE',
            'document',
            $idDocument,
            ['remplacePar' => (int) $nouvelId],
            $u->adresseIp
        );
        $this->auditService->journaliser(
            $u->idUtilisateur,
            'CREATE',
            'document',
            (int) $nouvelId,
            [
                'idSalarie'    => (int) $ancien->id_salarie,
                'typeDocument' => (int) $ancien->type_document,
                'remplace'     => $idDocument,
            ],
            $u->adresseIp
        );

        return (int) $nouvelId;
    }

    /**
     * Archivage logique : le fichier et la ligne sont conservés.
     */
    public function archiver(int $idDocument, SessionUtilisateur $u): void
    {
        if (!$this->documentPolicy->peutArchiver($u)) {
            throw new AccesRefuseException('Rôle insuffisant pour archiver un document');
        }

        if ($this->documentModel->find($idDocument) === null) {
            throw new ObjetIntrouvableException("Document #{$idDocument} introuvable");
        }

        if ($this->documentModel->update($idDocument, ['archive' => true]) === false) {
            throw new \RuntimeException("Échec de l'archivage du document");
        }

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'ARCHIVE',
            'document',
            $idDocument,
            null,
            $u->adresseIp
        );
    }

    /**
     * Compatibilité avec l'API DELETE existante : suppression logique.
     */
    public function supprimer(int $idDocument, SessionUtilisateur $u): void
    {
        $this->archiver($idDocument, $u);
    }

    public function consulter(int $idDocument, SessionUtilisateur $u): Document
    {
        $document = $this->documentModel->find($idDocument);

        if ($document === null) {
            throw new ObjetIntrouvableException("Document #{$idDocument} introuvable");
        }

        if (!$this->documentPolicy->peutLire($u, $document)) {
            throw new AccesRefuseException('Accès au document refusé');
        }

        $this->auditerLecture($document, $u);

        return $document;
    }

    /**
     * @return list<Document>
     */
    public function lister(int $idSalarie, SessionUtilisateur $u, bool $inclureArchives = false): array
    {
        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        $documents = $this->documentModel->listerAvecType($idSalarie, $inclureArchives);

        $documents = array_values(array_filter(
            $documents,
            fn (Document $document): bool => $this->documentPolicy->peutLire($u, $document)
        ));

        $estProprietaire = $this->documentPolicy->idSalarieDe($u) === $idSalarie;

        if (!$estProprietaire && !$u->aLeRole(['rh', 'admin'])) {
            $this->auditService->journaliser(
                $u->idUtilisateur,
                'READ',
                'dossier_documentaire',
                $idSalarie,
                ['nombre' => count($documents)],
                $u->adresseIp
            );
        }

        return $documents;
    }

    /**
     * Prépare le téléchargement d'une pièce après contrôle d'accès.
     *
     * @return array{chemin_absolu: string, nom_fichier: string, mime_type: string}
     */
    public function telecharger(int $idDocument, SessionUtilisateur $u): array
    {
        $document = $this->consulter($idDocument, $u);

        if (!$this->stockageService->existe((string) $document->chemin_fichier)) {
            throw new ObjetIntrouvableException('Fichier introuvable sur le disque');
        }

        $chemin = $this->stockageService->cheminAbsolu((string) $document->chemin_fichier);

        if ($chemin === null) {
            throw new ObjetIntrouvableException('Chemin de fichier invalide');
        }

        return [
            'chemin_absolu' => $chemin,
            'nom_fichier'   => (string) ($document->nom_fichier ?: basename($chemin)),
            'mime_type'     => (string) ($document->mime_type ?: 'application/octet-stream'),
        ];
    }

    /**
     * Types obligatoires pour lesquels le salarié n'a aucune pièce active.
     *
     * @return list<object>
     */
    public function piecesManquantes(int $idSalarie): array
    {
        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        $presents = array_map(
            static fn (Document $document): int => (int) $document->type_document,
            $this->documentModel->documentsActifs($idSalarie)
        );

        return array_values(array_filter(
            $this->typeDocumentModel->obligatoires(),
            static fn (object $type): bool => !in_array((int) $type->id, $presents, true)
        ));
    }

    /**
     * @return array{attendus: int, presents: int, manquants: int, pourcentage: int}
     */
    public function tauxCompletude(int $idSalarie): array
    {
        $attendus  = count($this->typeDocumentModel->obligatoires());
        $manquants = count($this->piecesManquantes($idSalarie));
        $presents  = $attendus - $manquants;

        return [
            'attendus'   => $attendus,
            'presents'   => $presents,
            'manquants'  => $manquants,
            'pourcentage' => $attendus === 0 ? 100 : (int) round($presents / $attendus * 100),
        ];
    }

    /**
     * Pièces actives qui expirent dans les N prochains jours (RH / admin).
     *
     * @return list<Document>
     */
    public function expirant(int $jours, SessionUtilisateur $u): array
    {
        if (!$u->aLeRole(['admin', 'rh'])) {
            throw new AccesRefuseException('Rôle insuffisant pour consulter les échéances documentaires');
        }

        $jours = max(1, min($jours, 365));

        return $this->documentModel->documentsExpirant($jours);
    }

    /**
     * @return list<object>
     */
    public function listerTypes(): array
    {
        return $this->typeDocumentModel->listerTous();
    }

    /**
     * @param array{date_emission?: string|null, date_expiration?: string|null} $donnees
     *
     * @return array{date_emission: string|null, date_expiration: string|null}
     */
    private function validerDates(array $donnees): array
    {
        $emission   = $this->dateOuNull($donnees['date_emission'] ?? null, "date d'émission");
        $expiration = $this->dateOuNull($donnees['date_expiration'] ?? null, "date d'expiration");

        if ($emission !== null && $expiration !== null && $expiration < $emission) {
            throw new DonneesIncoherentesException(
                "La date d'expiration ne peut pas précéder la date d'émission"
            );
        }

        return [
            'date_emission'   => $emission,
            'date_expiration' => $expiration,
        ];
    }

    private function dateOuNull(mixed $valeur, string $libelle): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $valeur);

        if ($date === false || $date->format('Y-m-d') !== (string) $valeur) {
            throw new DonneesIncoherentesException("Format de {$libelle} invalide (attendu AAAA-MM-JJ)");
        }

        return $date->format('Y-m-d');
    }

    private function niveauDemande(array $donnees, mixed $niveauParDefaut): string
    {
        $niveau = $donnees['niveau_confidentialite'] ?? null;

        if ($niveau === null || $niveau === '') {
            $niveau = $niveauParDefaut;
        }

        $niveau = (string) $niveau;

        if (!in_array($niveau, self::NIVEAUX, true)) {
            throw new DonneesIncoherentesException(
                'Niveau de confidentialité invalide (public, interne, rh ou restreint)'
            );
        }

        return $niveau;
    }

    /**
     * @param array{nom_fichier?: string|null} $donnees
     */
    private function nomAffiche(array $donnees, string $nomOriginal): string
    {
        $nom = trim((string) ($donnees['nom_fichier'] ?? ''));
        $nom = $nom !== '' ? $nom : $nomOriginal;
        $nom = str_replace(["\r", "\n", "\0"], '', $nom);

        return mb_substr($nom, 0, 255);
    }

    private function auditerLecture(Document $document, SessionUtilisateur $u): void
    {
        $niveau = $this->documentPolicy->niveauEffectif($document);

        if (!in_array($niveau, ['rh', 'restreint'], true)) {
            return;
        }

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'READ',
            'document',
            (int) $document->id_document,
            [
                'idSalarie'             => (int) $document->id_salarie,
                'niveauConfidentialite' => $niveau,
            ],
            $u->adresseIp
        );
    }

    private function estVrai(mixed $valeur): bool
    {
        if (is_bool($valeur)) {
            return $valeur;
        }

        return filter_var($valeur, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $valeur;
    }
}
