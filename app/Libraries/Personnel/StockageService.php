<?php

declare(strict_types=1);

namespace App\Libraries\Personnel;

use App\Exceptions\DonneesIncoherentesException;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Stockage physique des pièces du dossier administratif.
 *
 * Les fichiers sont déposés hors de public/, sous writable/uploads/documents/,
 * avec un nom UUID. Le nom fourni par le client n'est jamais utilisé comme nom
 * de stockage : il est seulement conservé comme libellé d'affichage.
 */
class StockageService
{
    public const TAILLE_MAX_OCTETS = 10_485_760; // 10 Mo

    /** @var array<string, string> MIME autorisé => extension de stockage */
    private const TYPES_AUTORISES = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];

    public function __construct(private readonly ?string $repertoireBase = null)
    {
    }

    public function repertoireBase(): string
    {
        $base = $this->repertoireBase
            ?? WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'documents';

        return rtrim($base, '/\\');
    }

    /**
     * @return list<string>
     */
    public function typesAutorises(): array
    {
        return array_keys(self::TYPES_AUTORISES);
    }

    /**
     * Valide et enregistre un fichier uploadé.
     *
     * @return array{
     *     nom_original: string,
     *     chemin_fichier: string,
     *     chemin_absolu: string,
     *     mime_type: string,
     *     taille_octets: int,
     *     checksum_sha256: string
     * }
     *
     * @throws DonneesIncoherentesException
     */
    public function stocker(UploadedFile $fichier): array
    {
        if ($fichier->getError() !== UPLOAD_ERR_OK) {
            throw new DonneesIncoherentesException('Fichier invalide : ' . $fichier->getErrorString());
        }

        $source = $fichier->getTempName();

        if (!is_file($source)) {
            throw new DonneesIncoherentesException('Fichier temporaire introuvable');
        }

        $taille = filesize($source);

        if ($taille === false || $taille === 0) {
            throw new DonneesIncoherentesException('Le fichier est vide');
        }

        if ($taille > self::TAILLE_MAX_OCTETS) {
            throw new DonneesIncoherentesException(sprintf(
                'Fichier trop volumineux (%d Mo maximum)',
                intdiv(self::TAILLE_MAX_OCTETS, 1_048_576)
            ));
        }

        $mime = $this->detecterMime($source);

        if (!isset(self::TYPES_AUTORISES[$mime])) {
            throw new DonneesIncoherentesException(
                'Type de fichier non autorisé. Formats acceptés : PDF, JPEG, PNG, WEBP.'
            );
        }

        $nomStocke  = $this->uuidV4() . '.' . self::TYPES_AUTORISES[$mime];
        $repertoire = $this->repertoireBase();

        if (!is_dir($repertoire) && !mkdir($repertoire, 0750, true) && !is_dir($repertoire)) {
            throw new \RuntimeException('Impossible de créer le répertoire de stockage des documents');
        }

        $destination = $repertoire . DIRECTORY_SEPARATOR . $nomStocke;
        $checksum    = hash_file('sha256', $source);

        if ($checksum === false) {
            throw new \RuntimeException("Impossible de calculer l'empreinte du fichier");
        }

        if (is_uploaded_file($source)) {
            $fichier->move($repertoire, $nomStocke);
        } elseif (!copy($source, $destination)) {
            throw new \RuntimeException("Impossible d'enregistrer le fichier sur le disque");
        }

        @chmod($destination, 0640);

        $tailleFinale = filesize($destination);

        return [
            'nom_original'    => $this->nomOriginalSur($fichier),
            'chemin_fichier'  => 'documents/' . $nomStocke,
            'chemin_absolu'   => $destination,
            'mime_type'       => $mime,
            'taille_octets'   => $tailleFinale !== false ? $tailleFinale : $taille,
            'checksum_sha256' => $checksum,
        ];
    }

    public function supprimer(string $cheminFichier): bool
    {
        $absolu = $this->cheminAbsolu($cheminFichier);

        if ($absolu === null || !is_file($absolu)) {
            return false;
        }

        return @unlink($absolu);
    }

    public function existe(string $cheminFichier): bool
    {
        $absolu = $this->cheminAbsolu($cheminFichier);

        return $absolu !== null && is_file($absolu);
    }

    /**
     * Résout un chemin relatif de type "documents/uuid.pdf" en chemin absolu.
     * Refuse toute tentative de sortie du répertoire de stockage.
     */
    public function cheminAbsolu(string $cheminFichier): ?string
    {
        $cheminFichier = str_replace('\\', '/', trim($cheminFichier));

        if ($cheminFichier === '' || str_contains($cheminFichier, '..')) {
            return null;
        }

        $prefixe = 'documents/';

        if (!str_starts_with($cheminFichier, $prefixe)) {
            return null;
        }

        $nom = substr($cheminFichier, strlen($prefixe));

        if ($nom === '' || basename($nom) !== $nom) {
            return null;
        }

        return $this->repertoireBase() . DIRECTORY_SEPARATOR . $nom;
    }

    private function detecterMime(string $chemin): string
    {
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($chemin);

            if (is_string($mime) && isset(self::TYPES_AUTORISES[strtolower($mime)])) {
                return strtolower($mime);
            }
        }

        // Repli sans extension fileinfo : lecture des signatures binaires.
        return $this->detecterMimeParSignature($chemin);
    }

    private function detecterMimeParSignature(string $chemin): string
    {
        $entete = (string) file_get_contents($chemin, false, null, 0, 16);

        if (str_starts_with($entete, '%PDF-')) {
            return 'application/pdf';
        }

        if (str_starts_with($entete, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }

        if (str_starts_with($entete, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($entete, 'RIFF') && substr($entete, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return 'application/octet-stream';
    }

    private function nomOriginalSur(UploadedFile $fichier): string
    {
        $nom = trim($fichier->getClientName());

        return $nom !== '' ? mb_substr($nom, 0, 255) : 'document';
    }

    private function uuidV4(): string
    {
        $donnees    = random_bytes(16);
        $donnees[6] = chr((ord($donnees[6]) & 0x0f) | 0x40);
        $donnees[8] = chr((ord($donnees[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($donnees), 4));
    }
}
