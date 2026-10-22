<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Common\SessionUtilisateur;
use App\Services\Common\DashboardService;
use CodeIgniter\HTTP\ResponseInterface;

class DashboardController extends ApiController
{
    private DashboardService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('dashboardService');
    }

    public function index(): ResponseInterface
    {
        $session = SessionUtilisateur::courante();

        if ($session === null) {
            return $this->erreur('NON_AUTHENTIFIE', 'Aucune session active', 401);
        }

        return $this->executer(fn (): array => [
            'role'             => $session->role,
            'indicateurs'      => $this->service->indicateurs($session),
            'actionsEnAttente' => $this->service->actionsEnAttente($session),
        ]);
    }
}
