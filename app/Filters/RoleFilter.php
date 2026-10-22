<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Common\SessionUtilisateur;
use App\Models\Admin\UtilisateurModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = SessionUtilisateur::courante();

        if ($session === null) {
            return $this->refus('NON_AUTHENTIFIE', 'Authentification requise', 401);
        }

        $compte = model(UtilisateurModel::class)->find($session->idUtilisateur);

        if ($compte === null || !$compte->actif) {
            service('sessionManager')->detruire();

            return $this->refus('NON_AUTHENTIFIE', 'Authentification requise', 401);
        }

        $roles = $this->normaliserRoles($arguments);

        if ($roles === [] || !$session->aLeRole($roles)) {
            return $this->refus('FORBIDDEN', 'Rôle insuffisant pour cette action', 403);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function normaliserRoles(mixed $arguments): array
    {
        if ($arguments === null) {
            return [];
        }

        if (is_array($arguments)) {
            return array_values(array_filter(array_map('trim', $arguments)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $arguments))));
    }

    private function refus(string $code, string $message, int $statut): ResponseInterface
    {
        return service('response')->setStatusCode($statut)->setJSON([
            'error'   => $code,
            'message' => $message,
        ]);
    }
}
