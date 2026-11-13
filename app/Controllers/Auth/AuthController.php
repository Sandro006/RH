<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\ApiController;
use App\Libraries\Auth\ResultatAuthentification;
use App\Libraries\Common\SessionUtilisateur;
use App\Services\Auth\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends ApiController
{
    private AuthService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('authService');
    }

    public function login(): ResponseInterface
    {
        $donnees = $this->request->getJSON(true) ?? $this->request->getPost();
        $resultat = $this->service->authentifier($donnees ?? [], $this->request->getIPAddress());

        if (!$resultat->succes) {
            return match ($resultat->codeErreur) {
                ResultatAuthentification::CHAMPS_VIDES => $this->erreur(
                    ResultatAuthentification::CHAMPS_VIDES,
                    'Identifiant et mot de passe obligatoires',
                    400
                ),
                ResultatAuthentification::COMPTE_DESACTIVE => $this->erreur(
                    ResultatAuthentification::COMPTE_DESACTIVE,
                    'Ce compte est désactivé',
                    403
                ),
                ResultatAuthentification::COMPTE_VERROUILLE => $this->erreur(
                    ResultatAuthentification::COMPTE_VERROUILLE,
                    'Ce compte est temporairement verrouillé',
                    423
                ),
                default => $this->erreur(
                    ResultatAuthentification::IDENTIFIANTS_INVALIDES,
                    'Identifiants invalides',
                    401
                ),
            };
        }

        $avertissement = null;
        try {
            $session = SessionUtilisateur::courante();
            if ($session !== null) (new \App\Libraries\Temps\PointageAutomatique())->enregistrer($session, 'entree');
        } catch (\Throwable $e) {
            log_message('error', 'Pointage automatique à la connexion : ' . $e->getMessage());
            $avertissement = 'Connexion réussie, mais le pointage d’entrée n’a pas pu être enregistré. Contactez les RH.';
        }
        return $this->ok([
            'utilisateur' => $resultat->utilisateur?->toPublicArray(),
            'avertissement_pointage' => $avertissement,
        ]);
    }

    public function logout(): ResponseInterface
    {
        $session = SessionUtilisateur::courante();

        if ($session !== null) {
            try {
                (new \App\Libraries\Temps\PointageAutomatique())->enregistrer($session, 'sortie');
            } catch (\Throwable $e) {
                log_message('error', 'Pointage automatique à la déconnexion : ' . $e->getMessage());
                return $this->erreur('POINTAGE_ECHEC', 'La sortie n’a pas pu être enregistrée. Réessayez ou contactez les RH.', 409);
            }
            $this->service->deconnecter($session, $this->request->getIPAddress());
        }

        return $this->response->setStatusCode(204);
    }

    public function session(): ResponseInterface
    {
        $session = SessionUtilisateur::courante();

        if ($session === null) {
            return $this->erreur('NON_AUTHENTIFIE', 'Aucune session active', 401);
        }

        return $this->ok([
            'utilisateur' => [
                'idUtilisateur'         => $session->idUtilisateur,
                'identifiant'           => $session->identifiant,
                'role'                  => $session->role,
                'idEmploye'             => $session->idEmploye,
                'doitChangerMotDePasse' => $session->doitChangerMotDePasse,
            ],
        ]);
    }

    public function changerMotDePasse(): ResponseInterface
    {
        $session = SessionUtilisateur::courante();

        if ($session === null) {
            return $this->erreur('NON_AUTHENTIFIE', 'Aucune session active', 401);
        }

        $donnees = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $ancien  = (string) ($donnees['ancienMotDePasse'] ?? '');
        $nouveau = (string) ($donnees['nouveauMotDePasse'] ?? '');

        if ($ancien === '' || $nouveau === '') {
            return $this->erreur(
                ResultatAuthentification::CHAMPS_VIDES,
                'Ancien et nouveau mot de passe obligatoires',
                400
            );
        }

        return $this->executer(function () use ($session, $ancien, $nouveau): array {
            $this->service->changerMotDePasse($session->idUtilisateur, $ancien, $nouveau);

            return ['message' => 'Mot de passe modifié'];
        });
    }

 }
