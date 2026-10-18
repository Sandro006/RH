<?php

declare(strict_types=1);

namespace App\Controllers\Personnel;

use App\Controllers\BaseController;
use App\Services\Personnel\ContratService;
use App\Services\Personnel\GrilleService;
use App\Libraries\Common\SessionUtilisateur;
use App\Exceptions\StatutInvalideException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ConflitException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\AccesRefuseException;
use CodeIgniter\HTTP\ResponseInterface;

class ContratController extends BaseController
{
    private ContratService $service;
    private GrilleService $grilleService;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service       = service('contratService');
        $this->grilleService = service('grilleService');
    }

    // GET /api/contrats — lister (filtres)
    public function index()
    {
        $filtre = [
            'statut'        => $this->request->getGet('statut'),
            'idSalarie'     => $this->request->getGet('idSalarie'),
            'typeContratId' => $this->request->getGet('typeContratId'),
        ];
        return $this->response->setJSON($this->service->lister($filtre));
    }

    // POST /api/contrats — créer
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        try {
            $id = $this->service->creerContrat($body, SessionUtilisateur::courante());
            return $this->response->setStatusCode(201)->setJSON(['idContrat' => $id]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        } catch (DonneesIncoherentesException | StatutInvalideException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => $e->getMessage()]);
        } catch (ConflitException $e) {
            return $this->response->setStatusCode(409)->setJSON(['error' => 'CONFLIT', 'message' => $e->getMessage()]);
        }
    }

    // GET /api/contrats/{id} — fiche
    public function show(int $id)
    {
        $c = $this->service->consulter($id);
        if ($c === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND']);
        }
        return $this->response->setJSON($c);
    }

    // PATCH /api/contrats/{id} — modifier (En préparation)
    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        try {
            $this->service->modifier($id, $body, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (StatutInvalideException | DonneesIncoherentesException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'STATUT_INVALIDE', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // POST /api/contrats/{id}/soumettre — En attente de signature
    public function soumettre(int $id)
    {
        try {
            $this->service->soumettrePourSignature($id, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (StatutInvalideException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'STATUT_INVALIDE', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // POST /api/contrats/{id}/signer — Signé
    public function signer(int $id)
    {
        $body          = $this->request->getJSON(true) ?? [];
        $dateSignature = $body['dateSignature'] ?? date('Y-m-d');

        try {
            $this->service->signer($id, (string) $dateSignature, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (StatutInvalideException | DonneesIncoherentesException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'STATUT_INVALIDE', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // POST /api/contrats/{id}/activer — En cours (+ statut salarié)
    public function activer(int $id)
    {
        try {
            $this->service->activer($id, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (StatutInvalideException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'STATUT_INVALIDE', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // POST /api/contrats/{id}/renouveler — nouveau contrat (max 2)
    public function renouveler(int $id)
    {
        $body            = $this->request->getJSON(true) ?? [];
        $nouvelleDateFin = $body['nouvelleDateFin'] ?? null;

        if ($nouvelleDateFin === null) {
            return $this->response->setStatusCode(400)->setJSON([
                'error'   => 'DONNEES_INCOHERENTES',
                'message' => 'nouvelleDateFin requis',
            ]);
        }

        try {
            $newId = $this->service->renouveler($id, (string) $nouvelleDateFin, SessionUtilisateur::courante());
            return $this->response->setStatusCode(201)->setJSON(['idContrat' => $newId]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (StatutInvalideException | DonneesIncoherentesException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'STATUT_INVALIDE', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // POST /api/contrats/{id}/avenant — nouveau contrat lié
    public function avenant(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        try {
            $newId = $this->service->creerAvenant($id, $body, SessionUtilisateur::courante());
            return $this->response->setStatusCode(201)->setJSON(['idContrat' => $newId]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (StatutInvalideException | DonneesIncoherentesException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'STATUT_INVALIDE', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // POST /api/contrats/{id}/mettre-fin — Terminé / Résilié
    public function mettreFin(int $id)
    {
        $body  = $this->request->getJSON(true) ?? [];
        $motif = $body['motif'] ?? null;

        if ($motif === null) {
            return $this->response->setStatusCode(400)->setJSON([
                'error'   => 'DONNEES_INCOHERENTES',
                'message' => 'motif requis',
            ]);
        }

        try {
            $this->service->mettreFin($id, (string) $motif, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (StatutInvalideException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'STATUT_INVALIDE', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // GET /api/contrats/{id}/historique — journal des statuts
    public function historique(int $id)
    {
        return $this->response->setJSON($this->service->historique($id));
    }

    // GET /api/referentiels/grille/{idPoste} — fourchette salariale
    public function grille(int $idPoste)
    {
        try {
            return $this->response->setJSON($this->grilleService->fourchettePoste($idPoste));
        } catch (DonneesIncoherentesException $e) {
            return $this->response->setStatusCode(404)->setJSON([
                'error'   => 'NOT_FOUND',
                'message' => $e->getMessage(),
            ]);
        }
    }
}