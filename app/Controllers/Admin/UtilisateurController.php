<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\ApiController;
use App\Libraries\Common\SessionUtilisateur;
use App\Services\Admin\UtilisateurService;
use CodeIgniter\HTTP\ResponseInterface;

class UtilisateurController extends ApiController
{
    private UtilisateurService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('utilisateurService');
    }

    public function index(): ResponseInterface
    {
        $session = SessionUtilisateur::courante();

        if ($session === null) {
            return $this->erreur('NON_AUTHENTIFIE', 'Aucune session active', 401);
        }

        $filtres = [
            'role'    => $this->request->getGet('role'),
            'actif'   => $this->request->getGet('actif'),
            'q'       => $this->request->getGet('q'),
            'page'    => $this->request->getGet('page') ?? 1,
            'parPage' => $this->request->getGet('parPage') ?? 20,
        ];

        return $this->executer(fn (): array => $this->service->lister($filtres, $session));
    }

    public function create(): ResponseInterface
    {
        $session = SessionUtilisateur::courante();

        if ($session === null) {
            return $this->erreur('NON_AUTHENTIFIE', 'Aucune session active', 401);
        }

        $donnees = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        return $this->executer(function () use ($donnees, $session): array {
            $id = $this->service->creer($donnees, $session);

            return ['idUtilisateur' => $id];
        }, 201);
    }

    public function update(int $id): ResponseInterface
    {
        $session = SessionUtilisateur::courante();

        if ($session === null) {
            return $this->erreur('NON_AUTHENTIFIE', 'Aucune session active', 401);
        }

        $donnees = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        return $this->executer(function () use ($id, $donnees, $session): array {
            $this->service->modifier($id, $donnees, $session);

            return ['message' => 'Utilisateur modifié'];
        });
    }

    public function activer(int $id): ResponseInterface
    {
        $session = SessionUtilisateur::courante();

        if ($session === null) {
            return $this->erreur('NON_AUTHENTIFIE', 'Aucune session active', 401);
        }

        return $this->executer(function () use ($id, $session): array {
            $this->service->activer($id, $session);

            return ['message' => 'Utilisateur activé'];
        });
    }

    public function desactiver(int $id): ResponseInterface
    {
        $session = SessionUtilisateur::courante();

        if ($session === null) {
            return $this->erreur('NON_AUTHENTIFIE', 'Aucune session active', 401);
        }

        return $this->executer(function () use ($id, $session): array {
            $this->service->desactiver($id, $session);

            return ['message' => 'Utilisateur désactivé'];
        });
    }

    public function reinitialiser(int $id): ResponseInterface
    {
        $session = SessionUtilisateur::courante();

        if ($session === null) {
            return $this->erreur('NON_AUTHENTIFIE', 'Aucune session active', 401);
        }

        return $this->executer(function () use ($id, $session): array {
            $motDePasseTemporaire = $this->service->reinitialiserMotDePasse($id, $session);

            return [
                'message'               => 'Mot de passe réinitialisé',
                'motDePasseTemporaire'  => $motDePasseTemporaire,
            ];
        });
    }
}
