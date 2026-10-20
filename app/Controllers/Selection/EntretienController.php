<?php
namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\EntretienService;

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
        $cr = $this->request->getJsonVar('compteRendu');
        if (!$cr) return $this->failValidationErrors('compteRendu obligatoire');
        try {
            (new EntretienService())->realiser((int) $id, $cr, $this->idUtilisateur());
            return $this->respond(['ok' => true]);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 409);
        } catch (\RuntimeException $e) {
            return $this->failNotFound($e->getMessage());
        }
    }

    private function idUtilisateur(): int
    {
        return (int) session('id_utilisateur'); // adapte à ton auth
    }
    // confirmer, reporter, annuler, lister, fiche, historique : même schéma
}