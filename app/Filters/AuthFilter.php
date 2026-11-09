<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Common\SessionUtilisateur;
use App\Models\Admin\UtilisateurModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    private const ROUTES_CHANGEMENT_MOT_DE_PASSE = [
        'api/auth/mot-de-passe',
        'api/auth/logout',
        'api/auth/session',
<<<<<<< HEAD
=======
        'profil/mot-de-passe',
>>>>>>> 57f1b34f20901561d84f17b8b34950d5e25ea61e
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $session = SessionUtilisateur::courante();

        if ($session === null) {
            return $this->nonAuthentifie($request);
        }

        $compte = model(UtilisateurModel::class)->find($session->idUtilisateur);

        if ($compte === null || !$compte->actif) {
            service('sessionManager')->detruire();

            return $this->nonAuthentifie($request);
        }

        $chemin = rtrim(ltrim($request->getUri()->getPath(), '/'), '/');

        $routeAutorisee = false;

        foreach (self::ROUTES_CHANGEMENT_MOT_DE_PASSE as $route) {
            if ($chemin === $route || str_ends_with($chemin, '/' . $route)) {
                $routeAutorisee = true;
                break;
            }
        }

        if ($compte->doit_changer_mot_de_passe && !$routeAutorisee) {
<<<<<<< HEAD
            return service('response')->setStatusCode(403)->setJSON([
                'error'   => 'CHANGEMENT_MOT_DE_PASSE_REQUIS',
                'message' => 'Vous devez changer votre mot de passe avant de continuer',
            ]);
=======
            if ($this->veutJson($request)) {
                return service('response')->setStatusCode(403)->setJSON([
                    'error'   => 'CHANGEMENT_MOT_DE_PASSE_REQUIS',
                    'message' => 'Vous devez changer votre mot de passe avant de continuer',
                ]);
            }

            return service('response')->redirect('/profil/mot-de-passe');
>>>>>>> 57f1b34f20901561d84f17b8b34950d5e25ea61e
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function nonAuthentifie(RequestInterface $request): ResponseInterface
    {
        if ($this->veutJson($request)) {
            return service('response')->setStatusCode(401)->setJSON([
                'error'   => 'NON_AUTHENTIFIE',
                'message' => 'Authentification requise',
            ]);
        }

<<<<<<< HEAD
        return service('response')->redirect('/login.html');
=======
        return service('response')->redirect('/login');
>>>>>>> 57f1b34f20901561d84f17b8b34950d5e25ea61e
    }

    private function veutJson(RequestInterface $request): bool
    {
        $chemin = ltrim($request->getUri()->getPath(), '/');

        if (str_starts_with($chemin, 'api/') || str_contains($chemin, '/api/')) {
            return true;
        }

        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return true;
        }

        return method_exists($request, 'isAJAX') && $request->isAJAX();
    }
}
