<?php

declare(strict_types=1);

namespace App\Controllers\Selection;

use App\Controllers\BaseController;
use App\Services\Selection\SelectionService;
use App\Libraries\Common\SessionUtilisateur;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use CodeIgniter\HTTP\ResponseInterface;

class SelectionController extends BaseController
{
    private SelectionService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('selectionService');
    }

    // GET /api/offres/{id}/classement
    public function classement(int $idOffre)
    {
        return $this->response->setJSON($this->service->classerPourOffre($idOffre));
    }

    // GET /api/offres/{id}/candidats-eligibles
    public function eligibles(int $idOffre)
    {
        return $this->response->setJSON([
            'seuil'      => $this->service->seuilRetention(),
            'candidats'  => $this->service->candidatsEligibles($idOffre),
        ]);
    }

    // POST /api/candidatures/{id}/retenir
    public function retenir(int $id)
    {
        return $this->executer(function (SessionUtilisateur $u) use ($id) {
            $this->service->retenir($id, $u);

            return $this->response->setJSON(['ok' => true, 'statut' => 'Retenue']);
        });
    }

    // POST /api/candidatures/{id}/refuser
    public function refuser(int $id)
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();

        return $this->executer(function (SessionUtilisateur $u) use ($id, $body) {
            $this->service->refuser($id, $body['motif'] ?? null, $u);

            return $this->response->setJSON(['ok' => true, 'statut' => 'Refusée']);
        });
    }

    // POST /api/candidatures/{id}/decision
    public function decider(int $id)
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();

        return $this->executer(function (SessionUtilisateur $u) use ($id, $body) {
            $resultat = $this->service->decider($id, (bool) ($body['retenue'] ?? false), $u, $body['motif'] ?? null);

            return $this->response->setJSON($resultat);
        });
    }

    private function executer(callable $action)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'NON_AUTHENTIFIE']);
        }

        try {
            return $action($u);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        } catch (DonneesIncoherentesException | StatutInvalideException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'ERREUR_INTERNE', 'message' => $e->getMessage()]);
        }
    }
}
