<?php

declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Libraries\Common\SessionUtilisateur;
use App\Services\Finance\GrilleSalarialeService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Contrôleur CRUD pour la grille salariale
 * Accès réservé au rôle finance (et admin)
 */
class GrilleSalarialeController extends BaseController
{
    private GrilleSalarialeService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('grilleSalarialeService');
    }

    /**
     * GET /api/finance/grilles-salariales - Liste toutes les grilles
     */
    public function index()
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        $activesSeulement = filter_var($this->request->getGet('actives'), FILTER_VALIDATE_BOOLEAN);

        try {
            $donnees = $activesSeulement
                ? $this->service->listerActives()
                : $this->service->lister();

            return $this->response->setJSON(['donnees' => $donnees]);
        } catch (\Throwable $e) {
            return $this->reponseErreur($e);
        }
    }

    /**
     * GET /api/finance/grilles-salariales/(:num) - Détail d'une grille
     */
    public function show(int $id)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        try {
            $grille = $this->service->trouver($id);

            return $this->response->setJSON(['donnees' => $grille]);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->reponseErreur($e);
        }
    }

    /**
     * POST /api/finance/grilles-salariales - Créer une grille
     */
    public function create()
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        $donnees = $this->donneesRequete();

        try {
            $this->validerCreation($donnees);

            $id = $this->service->creer($donnees, $u);

            return $this->response->setStatusCode(201)
                ->setJSON(['id' => $id, 'message' => 'Grille salariale créée avec succès']);
        } catch (DonneesIncoherentesException $e) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->reponseErreur($e);
        }
    }

    /**
     * PATCH /api/finance/grilles-salariales/(:num) - Modifier une grille
     */
    public function update(int $id)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        $donnees = $this->donneesRequete();

        try {
            $this->service->modifier($id, $donnees, $u);

            return $this->response->setJSON(['message' => 'Grille salariale modifiée avec succès']);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        } catch (DonneesIncoherentesException $e) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->reponseErreur($e);
        }
    }

    /**
     * DELETE /api/finance/grilles-salariales/(:num) - Supprimer une grille
     */
    public function delete(int $id)
    {
        $u = SessionUtilisateur::courante();

        if ($u === null) {
            return $this->reponseNonAuthentifie();
        }

        try {
            $this->service->supprimer($id, $u);

            return $this->response->setJSON(['message' => 'Grille salariale supprimée avec succès']);
        } catch (ObjetIntrouvableException $e) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'NOT_FOUND', 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->reponseErreur($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesRequete(): array
    {
        $post = $this->request->getPost();

        if (is_array($post) && $post !== []) {
            return $post;
        }

        $json = $this->request->getJSON(true);

        return is_array($json) ? $json : [];
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private function validerCreation(array $donnees): void
    {
        $champsRequis = ['id_poste', 'rang', 'salaire_min', 'salaire_max', 'date_debut'];

        foreach ($champsRequis as $champ) {
            if (!isset($donnees[$champ]) || $donnees[$champ] === '') {
                throw new DonneesIncoherentesException("Le champ '{$champ}' est requis");
            }
        }

        if (!is_numeric($donnees['id_poste'])) {
            throw new DonneesIncoherentesException('id_poste doit être un entier');
        }

        if (!is_numeric($donnees['rang'])) {
            throw new DonneesIncoherentesException('rang doit être un entier');
        }

        if (!is_numeric($donnees['salaire_min'])) {
            throw new DonneesIncoherentesException('salaire_min doit être un nombre');
        }

        if (!is_numeric($donnees['salaire_max'])) {
            throw new DonneesIncoherentesException('salaire_max doit être un nombre');
        }
    }

    private function reponseNonAuthentifie(): ResponseInterface
    {
        return $this->response->setStatusCode(401)->setJSON([
            'error'   => 'NON_AUTHENTIFIE',
            'message' => 'Authentification requise',
        ]);
    }

    private function reponseErreur(\Throwable $e): ResponseInterface
    {
        return $this->response->setStatusCode(500)->setJSON([
            'error'   => 'ERREUR_INTERNE',
            'message' => $e->getMessage(),
        ]);
    }
}
