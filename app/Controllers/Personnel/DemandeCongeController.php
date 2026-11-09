<?php

declare(strict_types=1);

namespace App\Controllers\Personnel;

use App\Controllers\BaseController;
use App\Exceptions\DonneesIncoherentesException;
use App\Libraries\Common\SessionUtilisateur;
use App\Services\Personnel\DemandeCongeService;
use CodeIgniter\HTTP\ResponseInterface;

class DemandeCongeController extends BaseController
{
    private DemandeCongeService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('demandeCongeService');
    }

    public function index()
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        try {
            return $this->response->setJSON(['donnees' => $this->service->listerPourUtilisateur($u)]);
        } catch (\Throwable $e) {
            return $this->reponseErreur($e);
        }
    }

    public function creer()
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        $donnees = $this->donneesRequete();

        try {
            $id = $this->service->creer($donnees, $u);

            return $this->response->setStatusCode(201)
                ->setJSON(['id' => $id, 'message' => 'Demande de congé créée avec succès']);
        } catch (DonneesIncoherentesException $e) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->reponseErreur($e);
        }
    }

    private function donneesRequete(): array
    {
        $post = $this->request->getPost();

        if (is_array($post) && $post !== []) {
            return $post;
        }

        $json = $this->request->getJSON(true);

        return is_array($json) ? $json : [];
    }

    private function reponseNonAuthentifie(): ResponseInterface
    {
        return $this->response->setStatusCode(401)->setJSON([
            'error' => 'NON_AUTHENTIFIE',
            'message' => 'Authentification requise',
        ]);
    }

    private function reponseErreur(\Throwable $e): ResponseInterface
    {
        return $this->response->setStatusCode(500)->setJSON([
            'error' => 'ERREUR_INTERNE',
            'message' => $e->getMessage(),
        ]);
    }
}
