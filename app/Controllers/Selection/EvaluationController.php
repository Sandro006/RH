<?php

declare(strict_types=1);

namespace App\Controllers\Selection;

use App\Controllers\BaseController;
use App\Services\Selection\EvaluationService;
use App\Libraries\Common\SessionUtilisateur;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use CodeIgniter\HTTP\ResponseInterface;

class EvaluationController extends BaseController
{
    private EvaluationService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('evaluationService');
    }

    // GET /api/evaluations/{id}
    public function show(int $id)
    {
        $evaluation = $this->service->consulter($id);

        if ($evaluation === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND']);
        }

        return $this->response->setJSON($evaluation);
    }

    // GET /api/candidatures/{id}/evaluations
    public function listerParCandidature(int $id)
    {
        return $this->response->setJSON($this->service->listerParCandidature($id));
    }

    // GET /api/offres/{id}/evaluations
    public function listerParOffre(int $id)
    {
        return $this->response->setJSON($this->service->listerParOffre($id));
    }

    // GET /api/candidatures/{id}/evaluations/synthese
    public function synthese(int $id)
    {
        return $this->response->setJSON($this->service->synthese($id));
    }

    // POST /api/candidatures/{id}/evaluations
    public function evaluer(int $id)
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();
        $body['idCandidature'] = $id;

        return $this->executer(function (SessionUtilisateur $u) use ($body) {
            $idEvaluation = $this->service->evaluer($body, $u);

            return $this->response->setStatusCode(201)->setJSON(['idEvaluation' => $idEvaluation]);
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
