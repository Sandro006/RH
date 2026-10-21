<?php
namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\Selection\EntretienService;

class EntretienController extends ResourceController
{
    protected $format = 'json';

    public function planifier()
    {
        $rules = [
            'idCandidature' => 'required|is_natural_no_zero',
            'idRecruteur'   => 'required|is_natural_no_zero',
            'dateEntretien' => 'required|valid_date',
            'typeEntretien' => 'required|is_natural_no_zero',
        ];
        if (!$this->validate($rules)) return $this->failValidationErrors($this->validator->getErrors());

        try {
            $id = (new EntretienService())->planifier($this->request->getJSON(true), $this->idUtilisateur());
            return $this->respondCreated(['idEntretien' => $id]);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }

    public function realiser($id)
    {
        if (!is_numeric($id) || (int) $id <= 0) {
            return $this->failValidationErrors('Identifiant entretien invalide');
        }

        $body = $this->request->getJSON(true) ?? [];
        $cr = is_string($body['compteRendu'] ?? null)
            ? trim($body['compteRendu'])
            : '';
        if ($cr === '') {
            return $this->failValidationErrors('compteRendu obligatoire');
        }
        if (mb_strlen($cr) < 10 || mb_strlen($cr) > 5000) {
            return $this->failValidationErrors('compteRendu doit contenir entre 10 et 5000 caractères');
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $cr)) {
            return $this->failValidationErrors('compteRendu contient des caractères invalides');
        }

        try {
            (new EntretienService())->realiser((int) $id, $cr, $this->idUtilisateur());
            return $this->respond(['ok' => true]);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 409);
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'OBJET_INTROUVABLE' ? 404 : 403;
            return $this->fail($e->getMessage(), $status);
        }
    }

    public function confirmer($id)
    {
        try {
            (new EntretienService())->confirmer((int) $id, $this->idUtilisateur());
            return $this->respond(['ok' => true]);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 409);
        } catch (\RuntimeException $e) {
            return $this->failNotFound($e->getMessage());
        }
    }

    public function reporter($id)
    {
        if (!is_numeric($id) || (int) $id <= 0) {
            return $this->failValidationErrors('Identifiant entretien invalide');
        }

        $body = $this->request->getJSON(true) ?? [];
        if (!is_string($body['dateEntretien'] ?? null) || trim($body['dateEntretien']) === '') {
            return $this->failValidationErrors('dateEntretien obligatoire');
        }

        try {
            (new EntretienService())->reporter(
                (int) $id,
                trim($body['dateEntretien']),
                isset($body['motif']) ? (string) $body['motif'] : null,
                $this->idUtilisateur()
            );
            return $this->respond(['ok' => true]);
        } catch (\DomainException $e) {
            return $this->failValidationErrors($e->getMessage());
        } catch (\RuntimeException $e) {
            $status = $e->getMessage() === 'OBJET_INTROUVABLE' ? 404 : 403;
            return $this->fail($e->getMessage(), $status);
        }
    }

    public function annuler($id)
    {
        $body = $this->request->getJSON(true) ?? [];

        try {
            (new EntretienService())->annuler(
                (int) $id,
                isset($body['motif']) ? (string) $body['motif'] : null,
                $this->idUtilisateur()
            );
            return $this->respond(['ok' => true]);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 409);
        } catch (\RuntimeException $e) {
            return $this->failNotFound($e->getMessage());
        }
    }

    public function lister()
    {
        return $this->respond((new EntretienService())->lister([
            'idOffre' => $this->request->getGet('idOffre'),
            'idRecruteur' => $this->request->getGet('idRecruteur'),
            'statut' => $this->request->getGet('statut'),
            'dateDebut' => $this->request->getGet('dateDebut'),
            'dateFin' => $this->request->getGet('dateFin'),
        ]));
    }

    public function fiche($id)
    {
        $fiche = (new EntretienService())->fiche((int) $id);
        return $fiche === null ? $this->failNotFound('Entretien introuvable') : $this->respond($fiche);
    }

    public function historique($id)
    {
        try {
            return $this->respond((new EntretienService())->historique((int) $id));
        } catch (\RuntimeException $e) {
            return $this->failNotFound($e->getMessage());
        }
    }

    public function planifierPourOffre($idOffre)
    {
        try {
            $ids = (new EntretienService())->planifierPourOffre(
                (int) $idOffre,
                $this->request->getJSON(true) ?? [],
                $this->idUtilisateur()
            );
            return $this->respondCreated(['idsEntretien' => $ids]);
        } catch (\DomainException $e) {
            return $this->failValidationErrors($e->getMessage());
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }

    private function idUtilisateur(): int
    {
        return (int) session('id_utilisateur'); // adapte à ton auth
    }
    // confirmer, reporter, annuler, lister, fiche, historique : même schéma
}