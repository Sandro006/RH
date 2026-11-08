<?php

declare(strict_types=1);

namespace App\Controllers\Personnel;

use App\Controllers\BaseController;
use App\Services\Personnel\OnboardingService;
use App\Libraries\Common\SessionUtilisateur;
use App\Exceptions\StatutInvalideException;
use App\Exceptions\ConflitException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\AccesRefuseException;
use CodeIgniter\HTTP\ResponseInterface;

class OnboardingController extends BaseController
{
    private OnboardingService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('onboardingService');
    }

    // index
    public function index()
    {
        $filtre = [
            'statut'     => $this->request->getGet('statut'),
            'idSalarie'  => $this->request->getGet('idSalarie'),
            'tri'        => $this->request->getGet('tri'),
            'ordre'      => $this->request->getGet('ordre'),
        ];
        return $this->response->setJSON($this->service->lister($filtre));
    }

    // show
    public function show(int $id)
    {
        $o = $this->service->consulter($id);
        if ($o === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND']);
        }
        return $this->response->setJSON($o);
    }

    // demarrer
    public function demarrer()
    {
        $body = $this->request->getJSON(true) ?? [];
        $idSalarie = (int) ($body['idSalarie'] ?? 0);
        $dateDebut = $body['dateDebut'] ?? date('Y-m-d');

        if ($idSalarie <= 0) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => 'idSalarie requis']);
        }

        try {
            $id = $this->service->demarrer($idSalarie, $dateDebut, SessionUtilisateur::courante());
            return $this->response->setStatusCode(201)->setJSON(['idOnboarding' => $id]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        } catch (ConflitException $e) {
            return $this->response->setStatusCode(409)->setJSON(['error' => 'CONFLIT', 'message' => $e->getMessage()]);
        }
    }

    // creerTache
    public function creerTache(int $idOnboarding)
    {
        $body = $this->request->getJSON(true) ?? [];
        $body['idOnboarding'] = $idOnboarding;

        try {
            $id = $this->service->creerTache($body, SessionUtilisateur::courante());
            return $this->response->setStatusCode(201)->setJSON(['idTache' => $id]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // demarrerTache
    public function demarrerTache(int $idTache)
    {
        try {
            $this->service->demarrerTache($idTache, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (StatutInvalideException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'STATUT_INVALIDE', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // realiserTache
    public function realiserTache(int $idTache)
    {
        try {
            $this->service->realiserTache($idTache, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (StatutInvalideException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'STATUT_INVALIDE', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // bloquerTache
    public function bloquerTache(int $idTache)
    {
        $body  = $this->request->getJSON(true) ?? [];
        $motif = $body['motif'] ?? 'Bloquée';

        try {
            $this->service->bloquerTache($idTache, (string) $motif, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // annulerTache
    public function annulerTache(int $idTache)
    {
        try {
            $this->service->annulerTache($idTache, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // showTache
    public function showTache(int $idTache)
    {
        $tache = $this->service->consulterTache($idTache);
        if ($tache === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND']);
        }
        return $this->response->setJSON($tache);
    }

    // updateTache
    public function updateTache(int $idTache)
    {
        $body = $this->request->getJSON(true) ?? [];

        try {
            $this->service->modifierTache($idTache, $body, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // progression
    public function progression(int $idOnboarding)
    {
        return $this->response->setJSON(['progression' => $this->service->progression($idOnboarding)]);
    }

    // cloturer
    public function cloturer(int $idOnboarding)
    {
        try {
            $this->service->cloturer($idOnboarding, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (StatutInvalideException $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'STATUT_INVALIDE', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // annuler
    public function annuler(int $idOnboarding)
    {
        $body  = $this->request->getJSON(true) ?? [];
        $motif = $body['motif'] ?? 'Annulation';

        try {
            $this->service->annuler($idOnboarding, (string) $motif, SessionUtilisateur::courante());
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        } catch (AccesRefuseException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'FORBIDDEN', 'message' => $e->getMessage()]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        }
    }

    // historique
    public function historique(int $idOnboarding)
    {
        return $this->response->setJSON($this->service->historique($idOnboarding));
    }

    // listerTaches
    public function listerTaches(int $idOnboarding)
    {
        return $this->response->setJSON($this->service->listerTaches($idOnboarding));
    }

    // Mes tâches onboarding (salarié connecté)
    public function mesTaches()
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        if ($u->idEmploye === null) {
            return $this->response->setJSON([]);
        }

        $employe = \Config\Database::connect()->table('employe')
            ->select('id_salarie')
            ->where('id', $u->idEmploye)
            ->get()
            ->getRow();

        if ($employe === null || $employe->id_salarie === null) {
            return $this->response->setJSON([]);
        }

        $onboarding = $this->service->onboardingActif((int) $employe->id_salarie);

        if ($onboarding === null) {
            return $this->response->setJSON([]);
        }

        $taches = $this->service->listerTaches($onboarding->id_onboarding);

        return $this->response->setJSON([
            'idOnboarding' => $onboarding->id_onboarding,
            'statut'       => $onboarding->statut,
            'taches'       => $taches,
        ]);
    }

    // update
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
}