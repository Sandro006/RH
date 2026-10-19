<?php

namespace App\Controllers\Selection;

use App\Controllers\BaseController;

class EntretienController extends BaseController
{
    public function page()
    {
        return view('selection/entretiens');
    }

    public function index()
    {
        $filtres = [
            'statut' => $this->request->getGet('statut'),
            'idRecruteur' => $this->request->getGet('idRecruteur'),
            'idOffre' => $this->request->getGet('idOffre'),
            'dateDebut' => $this->request->getGet('dateDebut'),
            'dateFin' => $this->request->getGet('dateFin'),
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
            ->join('candidature c', 'c.id_candidature = e.id_candidature')
            ->join('candidat ca', 'ca.id_candidat = c.id_candidat')
            ->join('offre_emploi oe', 'oe.id_offre = c.id_offre')
            ->join('employe emp', 'emp.id = e.id_recruteur')
            ->join('status_entretien se', 'se.id = e.statut', 'left')
            ->join('type_entrentien te', 'te.id = e.type_entretien', 'left');

        if (! empty($filtres['statut'])) {
            $query->where('se.designation', $filtres['statut']);
        }
        if (! empty($filtres['idRecruteur'])) {
            $query->where('e.id_recruteur', (int) $filtres['idRecruteur']);
        }
        if (! empty($filtres['idOffre'])) {
            $query->where('oe.id_offre', (int) $filtres['idOffre']);
        }
        if (! empty($filtres['dateDebut'])) {
            $query->where('e.date_entretien >=', $filtres['dateDebut'] . ' 00:00:00');
        }
        if (! empty($filtres['dateFin'])) {
            $query->where('e.date_entretien <=', $filtres['dateFin'] . ' 23:59:59');
        }

        $entretiens = $query->orderBy('e.date_entretien', 'ASC')->get()->getResultArray();

        return $this->response->setJSON($entretiens);
    }

    public function confirmer(int $id)
    {
        $db = db_connect();
        $statut = $db->table('status_entretien')
            ->where('designation', 'Confirmé')
            ->get()
            ->getRowArray();

        if ($statut === null) {
            return $this->response->setStatusCode(500)
                ->setJSON(['error' => 'STATUT_CONFIRMATION_ABSENT']);
        }

        $entretien = $db->table('entretien')
            ->where('id_entretien', $id)
            ->get()
            ->getRowArray();

        if ($entretien === null) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'NOT_FOUND']);
        }

        $statutActuel = $db->table('status_entretien')
            ->where('id', $entretien['statut'])
            ->get()
            ->getRowArray();

        if ($statutActuel === null || $statutActuel['designation'] !== 'Planifié') {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'STATUT_INVALIDE']);
        }

        $db->table('entretien')
            ->where('id_entretien', $id)
            ->update(['statut' => $statut['id']]);

        return $this->response->setJSON(['ok' => true, 'statut' => 'Confirmé']);
    }
}