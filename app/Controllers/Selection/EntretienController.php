<?php

declare(strict_types=1);

namespace App\Controllers\Selection;

use App\Controllers\BaseController;
use App\Services\Selection\EntretienService;
use App\Libraries\Common\SessionUtilisateur;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\ConflitException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use CodeIgniter\HTTP\ResponseInterface;

class EntretienController extends BaseController
{
    private EntretienService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('entretienService');
    }

    public function page()
    {
        return redirect()->to('/selection/entretiens');
    }

    // GET /api/entretiens — lister (filtres)
    public function index()
    {
        $filtre = [
            'statut'        => $this->request->getGet('statut'),
            'idRecruteur'   => $this->request->getGet('idRecruteur'),
            'idOffre'       => $this->request->getGet('idOffre'),
            'idCandidature' => $this->request->getGet('idCandidature'),
            'dateDebut'     => $this->request->getGet('dateDebut'),
            'dateFin'       => $this->request->getGet('dateFin'),
            'tri'           => $this->request->getGet('tri'),
            'ordre'         => $this->request->getGet('ordre'),
        ];

        return $this->response->setJSON($this->service->lister($filtre));
    }

    // GET /api/entretiens/{id} — fiche
    public function show(int $id)
    {
        $entretien = $this->service->consulter($id);

        if ($entretien === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND']);
        }

        return $this->response->setJSON($entretien);
    }

    // POST /api/entretiens — planifier
    public function planifier()
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();

        return $this->executer(function (SessionUtilisateur $u) use ($body) {
            $id = $this->service->planifier($body, $u);

            return $this->response->setStatusCode(201)->setJSON(['idEntretien' => $id]);
        });
    }

    // POST /api/entretiens/{id}/confirmer
    public function confirmer(int $id)
    {
        return $this->executer(function (SessionUtilisateur $u) use ($id) {
            $this->service->confirmer($id, $u);

            return $this->response->setJSON(['ok' => true, 'statut' => 'Confirmé']);
        });
    }

    // POST /api/entretiens/{id}/reporter
    public function reporter(int $id)
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();
        $date = $body['dateEntretien'] ?? $body['nouvelleDate'] ?? null;

        if ($date === null || $date === '') {
            return $this->response->setStatusCode(400)->setJSON([
                'error'   => 'DONNEES_INCOHERENTES',
                'message' => 'dateEntretien requis',
            ]);
        }

        return $this->executer(function (SessionUtilisateur $u) use ($id, $date, $body) {
            $this->service->reporter($id, (string) $date, $body['motif'] ?? null, $u);

            return $this->response->setJSON(['ok' => true]);
        });
    }

    // POST /api/entretiens/{id}/realiser
    public function realiser(int $id)
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();

        return $this->executer(function (SessionUtilisateur $u) use ($id, $body) {
            $this->service->realiser($id, (string) ($body['compteRendu'] ?? ''), $u);

            return $this->response->setJSON(['ok' => true, 'statut' => 'Réalisé']);
        });
    }

    // POST /api/entretiens/{id}/annuler
    public function annuler(int $id)
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();

        return $this->executer(function (SessionUtilisateur $u) use ($id, $body) {
            $this->service->annuler($id, $body['motif'] ?? null, $u);

            return $this->response->setJSON(['ok' => true, 'statut' => 'Annulé']);
        });
    }

    // GET /api/entretiens/{id}/historique
    public function historique(int $id)
    {
        return $this->response->setJSON($this->service->historique($id));
    }

    // GET /api/candidatures/{id}/entretiens
    public function listerParCandidature(int $id)
    {
        return $this->response->setJSON($this->service->listerParCandidature($id));
    }

    // POST /api/offres/{id}/entretiens — planifier pour les candidatures présélectionnées
    public function planifierPourOffre(int $id)
    {
        return $this->executer(function (SessionUtilisateur $u) use ($id) {
            $ids = $this->service->planifierPourOffre($id, $u);

            return $this->response->setStatusCode(201)->setJSON(['idEntretiens' => $ids]);
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
        } catch (ConflitException $e) {
            return $this->response->setStatusCode(409)->setJSON(['error' => 'CONFLIT', 'message' => $e->getMessage()]);
        } catch (DonneesIncoherentesException | StatutInvalideException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => $e->getMessage()]);
        }
    }
}
