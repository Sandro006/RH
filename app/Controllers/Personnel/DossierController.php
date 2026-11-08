<?php

declare(strict_types=1);

namespace App\Controllers\Personnel;

use App\Controllers\BaseController;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\ObjetIntrouvableException;
use App\Libraries\Common\SessionUtilisateur;
use App\Services\Personnel\DossierService;
use CodeIgniter\HTTP\ResponseInterface;

class DossierController extends BaseController
{
    private DossierService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('dossierService');
    }

    // GET /api/salaries/{id}/dossier — dossier agrégé complet
    public function show(int $idSalarie)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        try {
            return $this->response->setJSON($this->service->dossier($idSalarie, $u));
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)
                ->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        }
    }

    // GET /api/salaries/{id}/dossier/alertes — alertes documentaires
    public function alertes(int $idSalarie)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        try {
            return $this->response->setJSON($this->service->alertes($idSalarie, $u));
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)
                ->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        }
    }

    // GET /api/salaries/{id}/carriere — timeline contrats / affectations / statuts
    public function carriere(int $idSalarie)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        try {
            return $this->response->setJSON($this->service->carriere($idSalarie, $u));
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)
                ->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        }
    }

    private function reponseNonAuthentifie()
    {
        return $this->response->setStatusCode(401)->setJSON([
            'error'   => 'NON_AUTHENTIFIE',
            'message' => 'Authentification requise',
        ]);
    }
}
