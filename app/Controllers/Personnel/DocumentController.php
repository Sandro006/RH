<?php

declare(strict_types=1);

namespace App\Controllers\Personnel;

use App\Controllers\BaseController;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Libraries\Common\SessionUtilisateur;
use App\Services\Personnel\DocumentService;
use CodeIgniter\HTTP\ResponseInterface;

class DocumentController extends BaseController
{
    private DocumentService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('documentService');
    }

    public function index(int $idSalarie)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        $inclureArchives = filter_var($this->request->getGet('archives'), FILTER_VALIDATE_BOOLEAN);

        try {
            $documents = array_map(
                static fn ($document): array => $document->toPublicArray(),
                $this->service->lister($idSalarie, $u, $inclureArchives)
            );

            return $this->response->setJSON($documents);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        }
    }

    public function ajouter(int $idSalarie)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        $donnees      = $this->donneesRequete();
        $typeDocument = (int) ($donnees['typeDocument'] ?? 0);
        $fichier      = $this->request->getFile('fichier');

        if ($typeDocument <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'error'   => 'DONNEES_INCOHERENTES',
                'message' => 'typeDocument requis',
            ]);
        }

        if ($fichier === null || $fichier->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->response->setStatusCode(400)->setJSON([
                'error'   => 'FICHIER_REQUIS',
                'message' => 'Le fichier est requis (multipart/form-data, champ "fichier")',
            ]);
        }

        try {
            $id = $this->service->ajouter(
                $idSalarie,
                $typeDocument,
                $fichier,
                $this->metadonnees($donnees),
                $u
            );

            return $this->response->setStatusCode(201)->setJSON(['idDocument' => $id]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        } catch (DonneesIncoherentesException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => $e->getMessage()]);
        }
    }

    public function remplacer(int $idDocument)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        $fichier = $this->request->getFile('fichier');

        if ($fichier === null || $fichier->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->response->setStatusCode(400)->setJSON([
                'error'   => 'FICHIER_REQUIS',
                'message' => 'Le fichier de remplacement est requis (multipart/form-data, champ "fichier")',
            ]);
        }

        try {
            $id = $this->service->remplacer(
                $idDocument,
                $fichier,
                $this->metadonnees($this->donneesRequete()),
                $u
            );

            return $this->response->setStatusCode(201)->setJSON(['idDocument' => $id]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        } catch (DonneesIncoherentesException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => $e->getMessage()]);
        }
    }

    public function telecharger(int $idDocument)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        try {
            $fichier = $this->service->telecharger($idDocument, $u);

            $nomTelechargement = str_replace(["\r", "\n", "\0"], '', $fichier['nom_fichier']) ?: 'document';

            return $this->response
                ->download($fichier['chemin_absolu'], null, true)
                ->setFileName($nomTelechargement)
                ->setHeader('X-Content-Type-Options', 'nosniff');
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    public function supprimer(int $idDocument)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        try {
            $this->service->supprimer($idDocument, $u);

            return $this->response->setStatusCode(200)->setJSON(['ok' => true, 'archive' => true]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    public function expirant()
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        $jours = (int) ($this->request->getGet('jours') ?? 30);

        try {
            $documents = array_map(
                static fn ($document): array => $document->toPublicArray(),
                $this->service->expirant($jours, $u)
            );

            return $this->response->setJSON($documents);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        }
    }

    public function listerTypes()
    {
        return $this->response->setJSON($this->service->listerTypes());
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesRequete(): array
    {
        $post = $this->request->getPost();

        if (is_array($post) && $post !== []) {
            return $post;
        }

        $json = $this->request->getJSON(true);

        return is_array($json) ? $json : [];
    }

    /**
     * @param array<string, mixed> $donnees
     *
     * @return array{
     *     nom_fichier: string|null,
     *     date_emission: string|null,
     *     date_expiration: string|null,
     *     niveau_confidentialite: string|null
     * }
     */
    private function metadonnees(array $donnees): array
    {
        return [
            'nom_fichier'            => isset($donnees['nomFichier']) ? (string) $donnees['nomFichier'] : null,
            'date_emission'          => isset($donnees['dateEmission']) ? (string) $donnees['dateEmission'] : null,
            'date_expiration'        => isset($donnees['dateExpiration']) ? (string) $donnees['dateExpiration'] : null,
            'niveau_confidentialite' => isset($donnees['niveauConfidentialite']) ? (string) $donnees['niveauConfidentialite'] : null,
        ];
    }

    private function reponseNonAuthentifie()
    {
        return $this->response->setStatusCode(401)->setJSON([
            'error'   => 'NON_AUTHENTIFIE',
            'message' => 'Authentification requise',
        ]);
    }
}
