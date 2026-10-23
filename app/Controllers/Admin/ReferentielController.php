<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\ApiController;
use App\Services\Common\ReferentielService;
use CodeIgniter\HTTP\ResponseInterface;

class ReferentielController extends ApiController
{
    private ReferentielService $service;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->service = service('referentielService');
    }

    public function departements(): ResponseInterface
    {
        return $this->executer(fn (): array => [
            'donnees' => $this->service->lister('departements'),
        ]);
    }

    public function services(): ResponseInterface
    {
        return $this->executer(fn (): array => [
            'donnees' => $this->service->lister('services', [
                'idDepartement' => (int) $this->request->getGet('idDepartement'),
            ]),
        ]);
    }

    public function postes(): ResponseInterface
    {
        return $this->executer(fn (): array => [
            'donnees' => $this->service->lister('postes', [
                'idService' => (int) $this->request->getGet('idService'),
            ]),
        ]);
    }

    public function grilles(): ResponseInterface
    {
        return $this->executer(fn (): array => [
            'donnees' => $this->service->lister('grilles', [
                'idPoste' => (int) $this->request->getGet('idPoste'),
            ]),
        ]);
    }

    public function grillesPoste(int $idPoste): ResponseInterface
    {
        return $this->executer(fn (): array => [
            'donnees' => $this->service->lister('grilles', ['idPoste' => $idPoste]),
        ]);
    }

    public function rangs(): ResponseInterface
    {
        return $this->executer(fn (): array => [
            'donnees' => $this->service->lister('rangs'),
        ]);
    }

    public function typesContrat(): ResponseInterface
    {
        return $this->executer(fn (): array => [
            'donnees' => $this->service->lister('types_contrat'),
        ]);
    }

    public function typesEntretien(): ResponseInterface
    {
        return $this->executer(fn (): array => [
            'donnees' => $this->service->lister('types_entretien'),
        ]);
    }

    public function typesDocument(): ResponseInterface
    {
        return $this->executer(fn (): array => [
            'donnees' => $this->service->lister('types_document'),
        ]);
    }

    public function actions(): ResponseInterface
    {
        return $this->executer(fn (): array => [
            'donnees' => $this->service->lister('actions'),
        ]);
    }

    public function naturesRevendication(): ResponseInterface
    {
        return $this->executer(fn (): array => [
            'donnees' => $this->service->lister('natures_revendication'),
        ]);
    }

    public function statuts(string $domaine): ResponseInterface
    {
        return $this->executer(fn (): array => [
            'domaine' => $domaine,
            'donnees' => $this->service->statuts($domaine),
        ]);
    }
}
