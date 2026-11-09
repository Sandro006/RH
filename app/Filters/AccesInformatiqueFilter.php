<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Common\SessionUtilisateur;
use App\Models\Admin\UtilisateurModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Accès réservé à la place informatique : un compte autorisé si rôle
 * admin / rh, OU si son employé est dans le département « Informatique ».
 * Utilisé pour « Créer un utilisateur » (page + API) : les informaticiens
 * (même avec un rôle « salarié ») doivent pouvoir créer des comptes.
 */
class AccesInformatiqueFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = SessionUtilisateur::courante();

        if ($session === null) {
            return $this->refus('NON_AUTHENTIFIE', 'Authentification requise', 401);
        }

        $compte = model(UtilisateurModel::class)->find($session->idUtilisateur);

        if ($compte === null || ! $compte->actif) {
            service('sessionManager')->detruire();

            return $this->refus('NON_AUTHENTIFIE', 'Authentification requise', 401);
        }

        if ($session->aLeRole(['admin', 'rh'])) {
            return null;
        }

        if ($this->estInformatique($session)) {
            return null;
        }

        return $this->refus('FORBIDDEN', 'Accès réservé aux comptes avec le rôle admin/rh ou rattachés au département Informatique', 403);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function estInformatique(SessionUtilisateur $session): bool
    {
        if ($session->idEmploye === null) {
            return false;
        }

        $ligne = model(\App\Models\Admin\EmployeModel::class)->db->table('employe e')
            ->select('d.nom AS departement')
            ->join('poste p', 'p.id_poste = e.poste_id')
            ->join('service s', 's.id_service = p.id_service')
            ->join('departement d', 'd.id_departement = s.id_departement')
            ->where('e.id', $session->idEmploye)
            ->get()
            ->getRow();

        return $ligne !== null && (string) $ligne->departement === 'Informatique';
    }

    private function refus(string $code, string $message, int $statut): ResponseInterface
    {
        return service('response')->setStatusCode($statut)->setJSON([
            'error'   => $code,
            'message' => $message,
        ]);
    }
}
