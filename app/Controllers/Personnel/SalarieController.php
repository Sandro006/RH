<?php

declare(strict_types=1);

namespace App\Controllers\Personnel;

use App\Controllers\BaseController;
use App\Services\Personnel\SalarieService;
use App\Libraries\Common\SessionUtilisateur;
use App\Exceptions\StatutInvalideException;
use App\Exceptions\ConflitException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\AccesRefuseException;
use CodeIgniter\HTTP\ResponseInterface;

class SalarieController extends BaseController
{
    private SalarieService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('salarieService');
    }

    // GET /api/salaries — lister (filtres)
    public function index()
    {
        $filtre = [
            'statut'   => $this->request->getGet('statut'),
            'id_poste' => $this->request->getGet('idPoste') ?? $this->request->getGet('id_poste'),
            'id_departement' => $this->request->getGet('idDepartement') ?? $this->request->getGet('id_departement'),
            'tri'            => $this->request->getGet('tri'),
            'ordre'          => $this->request->getGet('ordre'),
        ];
        return $this->response->setJSON($this->service->lister($filtre));
    }

    // GET /api/salaries/{id} — fiche
    public function show(int $id)
    {
        $s = $this->service->consulter($id);
        if ($s === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND']);
        }
        return $this->response->setJSON($s);
    }

    // POST /api/salaries — créer depuis une candidature
    public function createDepuisCandidature()
    {
        $body = $this->request->getJSON(true) ?? [];
        $idCandidature = (int) ($body['idCandidature'] ?? 0);

        if ($idCandidature <= 0) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => 'idCandidature requis']);
        }

        try {
            $id = $this->service->creerDepuisCandidature(
                $idCandidature,
                SessionUtilisateur::courante()
            );
            return $this->response->setStatusCode(201)->setJSON(['idSalarie' => $id]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)
                ->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (StatutInvalideException $e) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'STATUT_INVALIDE', 'message' => $e->getMessage()]);
        } catch (ConflitException $e) {
            return $this->response->setStatusCode(409)
                ->setJSON(['error' => 'CONFLIT', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // PATCH /api/salaries/{id} — modifier
    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        try {
            $this->service->modifier($id, $body, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // POST /api/salaries/{id}/affectations — affecter à un poste
    public function affecter(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        if (empty($body['idPoste']) || empty($body['dateDebut'])) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => 'idPoste et dateDebut requis']);
        }

        try {
            $idAffectation = $this->service->affecter(
                $id,
                (int) $body['idPoste'],
                (string) $body['dateDebut'],
                $body['motif'] ?? null,
                SessionUtilisateur::courante()
            );
            return $this->response->setStatusCode(201)->setJSON(['idAffectation' => $idAffectation]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // GET /api/salaries/{id}/affectations — historique des affectations
    public function listerAffectations(int $id)
    {
        return $this->response->setJSON($this->service->listerAffectations($id));
    }

    // GET /api/salaries/{id}/historique — journal des statuts
    public function historique(int $id)
    {
        return $this->response->setJSON($this->service->historique($id));
    }

    // POST /api/salaries/{id}/statut — changer le statut
    public function changerStatut(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];
        $statutCible = $body['statutCible'] ?? null;

        if ($statutCible === null) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => 'statutCible requis']);
        }

        try {
            $this->service->changerStatut(
                $id,
                (string) $statutCible,
                SessionUtilisateur::courante(),
                $body['commentaire'] ?? null
            );
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (StatutInvalideException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'STATUT_INVALIDE', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }
}
