<?php

declare(strict_types=1);

namespace App\Controllers\Personnel;

use App\Controllers\BaseController;
use App\Services\Personnel\PropositionEmbaucheService;
use App\Services\Personnel\RevendicationService;
use App\Libraries\Common\SessionUtilisateur;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\ConflitException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use CodeIgniter\HTTP\ResponseInterface;

class PropositionEmbaucheController extends BaseController
{
    private PropositionEmbaucheService $service;
    private RevendicationService $revendicationService;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service            = service('propositionEmbaucheService');
        $this->revendicationService = service('revendicationService');
    }

    // GET /api/propositions
    public function index()
    {
        $filtre = [
            'statut'        => $this->request->getGet('statut'),
            'idCandidature' => $this->request->getGet('idCandidature'),
            'idOffre'       => $this->request->getGet('idOffre'),
            'tri'           => $this->request->getGet('tri'),
            'ordre'         => $this->request->getGet('ordre'),
        ];

        return $this->response->setJSON($this->service->lister($filtre));
    }

    // GET /api/propositions/{id}
    public function show(int $id)
    {
        $proposition = $this->service->consulter($id);

        if ($proposition === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND']);
        }

        return $this->response->setJSON($proposition);
    }

    // GET /api/candidatures/{id}/proposition
    public function parCandidature(int $idCandidature)
    {
        $proposition = $this->service->trouverParCandidature($idCandidature);

        if ($proposition === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND']);
        }

        return $this->response->setJSON($proposition);
    }

    // POST /api/propositions
    public function create()
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();

        return $this->executer(function (SessionUtilisateur $u) use ($body) {
            $id = $this->service->creerDepuisCandidature(
                (int) ($body['idCandidature'] ?? 0),
                (float) ($body['remunerationProposee'] ?? 0),
                $body['dateEmbaucheProposee'] ?? null,
                $u
            );

            return $this->response->setStatusCode(201)->setJSON(['idProposition' => $id]);
        });
    }

    // POST /api/propositions/{id}/envoyer
    public function envoyer(int $id)
    {
        return $this->executer(function (SessionUtilisateur $u) use ($id) {
            $this->service->envoyer($id, $u);

            return $this->response->setJSON(['ok' => true, 'statut' => 'Envoyée']);
        });
    }

    // POST /api/propositions/{id}/repondre
    public function repondre(int $id)
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();

        return $this->executer(function (SessionUtilisateur $u) use ($id, $body) {
            $this->service->enregistrerReponse($id, $body, $u);

            return $this->response->setJSON(['ok' => true]);
        });
    }

    // POST /api/propositions/{id}/contre-proposer
    public function contreProposer(int $id)
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();

        return $this->executer(function (SessionUtilisateur $u) use ($id, $body) {
            $this->service->contreProposer(
                $id,
                (float) ($body['remunerationProposee'] ?? 0),
                $body['dateEmbaucheProposee'] ?? null,
                $u
            );

            return $this->response->setJSON(['ok' => true]);
        });
    }

    // POST /api/propositions/{id}/expirer
    public function expirer(int $id)
    {
        return $this->executer(function (SessionUtilisateur $u) use ($id) {
            $this->service->marquerExpiree($id, $u);

            return $this->response->setJSON(['ok' => true, 'statut' => 'Expirée']);
        });
    }

    // GET /api/propositions/{id}/historique
    public function historique(int $id)
    {
        return $this->response->setJSON($this->service->historique($id));
    }

    // GET /api/propositions/{id}/revendications
    public function listerRevendications(int $id)
    {
        return $this->response->setJSON($this->revendicationService->lister($id));
    }

    // POST /api/propositions/{id}/revendications
    public function soumettreRevendication(int $id)
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();
        $body['idProposition'] = $id;

        try {
            $idRevendication = $this->revendicationService->soumettre(
                $body,
                SessionUtilisateur::courante()
            );

            return $this->response->setStatusCode(201)->setJSON(['idRevendication' => $idRevendication]);
        } catch (\Throwable $e) {
            return $this->erreur($e);
        }
    }

    // POST /api/revendications/{id}/repondre
    public function repondreRevendication(int $id)
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();

        return $this->executer(function (SessionUtilisateur $u) use ($id, $body) {
            $this->revendicationService->repondre($id, $body, $u);

            return $this->response->setJSON(['ok' => true]);
        });
    }

    // POST /api/revendications/{id}/retirer
    public function retirerRevendication(int $id)
    {
        try {
            $this->revendicationService->retirer($id, SessionUtilisateur::courante());

            return $this->response->setJSON(['ok' => true, 'statut' => 'Retirée']);
        } catch (\Throwable $e) {
            return $this->erreur($e);
        }
    }

    // GET /api/revendications/{id}/historique
    public function historiqueRevendication(int $id)
    {
        return $this->response->setJSON($this->revendicationService->historique($id));
    }

    private function executer(callable $action)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'NON_AUTHENTIFIE']);
        }

        try {
            return $action($u);
        } catch (\Throwable $e) {
            return $this->erreur($e);
        }
    }

    private function erreur(\Throwable $e)
    {
        return match (true) {
            $e instanceof AccesRefuseException => $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]),
            $e instanceof ObjetIntrouvableException => $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]),
            $e instanceof ConflitException => $this->response->setStatusCode(409)->setJSON(['error' => 'CONFLIT', 'message' => $e->getMessage()]),
            $e instanceof DonneesIncoherentesException, $e instanceof StatutInvalideException => $this->response->setStatusCode(400)->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => $e->getMessage()]),
            default => $this->response->setStatusCode(500)->setJSON(['error' => 'ERREUR_INTERNE', 'message' => $e->getMessage()]),
        };
    }
}
