<?php

declare(strict_types=1);

namespace App\Controllers\Personnel;

use App\Controllers\BaseController;
use App\Services\Personnel\DocumentService;
use App\Libraries\Common\SessionUtilisateur;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\AccesRefuseException;
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

    // index
    public function index(int $idSalarie)
    {
        try {
            return $this->response->setJSON($this->service->lister($idSalarie));
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // ajouter
    public function ajouter(int $idSalarie)
    {
        $body = $this->request->getJSON(true) ?? [];

        $typeDocument  = (int) ($body['typeDocument'] ?? 0);
        $nomFichier    = (string) ($body['nomFichier'] ?? '');
        $cheminFichier = (string) ($body['cheminFichier'] ?? '');

        if ($typeDocument <= 0 || $cheminFichier === '') {
            return $this->response->setStatusCode(400)->setJSON([
                'error'   => 'DONNEES_INCOHERENTES',
                'message' => 'typeDocument et cheminFichier requis',
            ]);
        }

        try {
            $id = $this->service->ajouter(
                $idSalarie, $typeDocument, $nomFichier, $cheminFichier,
                SessionUtilisateur::courante()
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

    // supprimer
    public function supprimer(int $idDocument)
    {
        try {
            $this->service->supprimer($idDocument, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // listerTypes
    public function listerTypes()
    {
        return $this->response->setJSON($this->service->listerTypes());
    }
}