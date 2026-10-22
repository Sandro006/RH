<?php

namespace App\Controllers\Selection;

use App\Controllers\BaseController;
use App\Models\Selection\EntretienModel;
use App\Models\Selection\HistoriqueEntretienModel;

class EntretienController extends BaseController
{
    public function page()
    {
        return redirect()->to('/web/pages/selection/entretiens.html');
    }

    /**
     * Liste des entretiens avec filtres.
     */
    public function index()
    {
        $filtres = [
            'statut'     => $this->request->getGet('statut'),
            'idRecruteur'=> $this->request->getGet('idRecruteur'),
            'idOffre'    => $this->request->getGet('idOffre'),
            'dateDebut'  => $this->request->getGet('dateDebut'),
            'dateFin'    => $this->request->getGet('dateFin'),
        ];

        $query = db_connect()
            ->table('entretien e')
            ->select([
                'e.id_entretien',
                'e.date_entretien',
                'e.id_recruteur',
                'e.statut AS statut_id',
                'se.designation AS statut',
                'te.designation AS type_entretien',
                'c.id_candidature',
                'ca.nom AS nom_candidat',
                'ca.prenom AS prenom_candidat',
                'oe.id_offre',
                'oe.titre AS offre',
                'emp.nom AS recruteur',
            ])
            ->join(
                'candidature c',
                'c.id_candidature = e.id_candidature'
            )
            ->join(
                'candidat ca',
                'ca.id_candidat = c.id_candidat'
            )
            ->join(
                'offre_emploi oe',
                'oe.id_offre = c.id_offre'
            )
            ->join(
                'employe emp',
                'emp.id = e.id_recruteur'
            )
            ->join(
                'status_entretien se',
                'se.id = e.statut',
                'left'
            )
            ->join(
                'type_entrentien te',
                'te.id = e.type_entretien',
                'left'
            );

        if (! empty($filtres['statut'])) {
            $query->where('se.designation', $filtres['statut']);
        }

        if (! empty($filtres['idRecruteur'])) {
            $query->where(
                'e.id_recruteur',
                (int) $filtres['idRecruteur']
            );
        }

        if (! empty($filtres['idOffre'])) {
            $query->where(
                'oe.id_offre',
                (int) $filtres['idOffre']
            );
        }

        if (! empty($filtres['dateDebut'])) {
            $query->where(
                'e.date_entretien >=',
                $filtres['dateDebut'] . ' 00:00:00'
            );
        }

        if (! empty($filtres['dateFin'])) {
            $query->where(
                'e.date_entretien <=',
                $filtres['dateFin'] . ' 23:59:59'
            );
        }

        $entretiens = $query
            ->orderBy('e.date_entretien', 'ASC')
            ->get()
            ->getResultArray();

        return $this->response->setJSON($entretiens);
    }

    public function planifier()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        foreach (['idCandidature', 'idRecruteur', 'dateEntretien', 'typeEntretien'] as $field) {
            if (empty($data[$field])) {
                return $this->response->setStatusCode(400)->setJSON(['error' => 'DONNEES_INCOHERENTES', 'message' => "{$field} est requis"]);
            }
        }
        $status = $this->statusId('Planifié');
        if ($status === null) return $this->response->setStatusCode(500)->setJSON(['error' => 'STATUT_ABSENT']);
        $model = new EntretienModel();
        $id = $model->insert([
            'id_candidature' => (int) $data['idCandidature'], 'id_recruteur' => (int) $data['idRecruteur'],
            'date_entretien' => $data['dateEntretien'], 'type_entretien' => (int) $data['typeEntretien'], 'statut' => $status,
        ], true);
        return $this->response->setStatusCode(201)->setJSON(['idEntretien' => (int) $id]);
    }

    public function show(int $id)
    {
        $row = (new EntretienModel())->find($id);
        return $row === null ? $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND']) : $this->response->setJSON($row);
    }

    public function reporter(int $id)
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        if (empty($data['dateEntretien'])) return $this->response->setStatusCode(400)->setJSON(['error' => 'DATE_REQUISE']);
        return $this->mettreAJour($id, ['date_entretien' => $data['dateEntretien']]);
    }

    public function realiser(int $id)
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $status = $this->statusId('Réalisé');
        return $status === null ? $this->response->setStatusCode(500)->setJSON(['error' => 'STATUT_ABSENT']) : $this->mettreAJour($id, ['statut' => $status, 'compte_rendu' => $data['compteRendu'] ?? null]);
    }

    public function annuler(int $id)
    {
        $status = $this->statusId('Annulé');
        return $status === null ? $this->response->setStatusCode(500)->setJSON(['error' => 'STATUT_ABSENT']) : $this->mettreAJour($id, ['statut' => $status]);
    }

    public function historique(int $id)
    {
        return $this->response->setJSON((new HistoriqueEntretienModel())->where('id_entretien', $id)->orderBy('date_changement', 'ASC')->findAll());
    }

    private function statusId(string $designation): ?int
    {
        $row = db_connect()->table('status_entretien')->where('designation', $designation)->get()->getRowArray();
        return $row === null ? null : (int) $row['id'];
    }

    private function mettreAJour(int $id, array $data)
    {
        $model = new EntretienModel();
        if ($model->find($id) === null) return $this->response->setStatusCode(404)->setJSON(['error' => 'NOT_FOUND']);
        $model->update($id, $data);
        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Confirme un entretien planifié.
     */
    public function confirmer(int $id)
    {
        $db = db_connect();

        $entretienModel = new EntretienModel();

        $entretien = $entretienModel->find($id);

        if ($entretien === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'NOT_FOUND']);
        }

        $statutConfirme = $db->table('status_entretien')
            ->where('designation', 'Confirmé')
            ->get()
            ->getRowArray();

        if ($statutConfirme === null) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'error' => 'STATUT_CONFIRMATION_ABSENT'
                ]);
        }

        $statutActuel = $db->table('status_entretien')
            ->where('id', $entretien['statut'])
            ->get()
            ->getRowArray();

        if (
            $statutActuel === null
            || $statutActuel['designation'] !== 'Planifié'
        ) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'STATUT_INVALIDE']);
        }

        $entretienModel->update($id, [
            'statut' => $statutConfirme['id']
        ]);

        return $this->response->setJSON([
            'ok' => true,
            'statut' => 'Confirmé'
        ]);
    }
}
