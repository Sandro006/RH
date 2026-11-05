<?php

declare(strict_types=1);

namespace App\Services\Recrutement;

use App\Models\Recrutement\DemandeRecrutementModel;
use App\Libraries\Common\Tri;

class DemandeRecrutementService extends RecruitmentService
{
    private DemandeRecrutementModel $model;
    private CoherenceService $coherence;
    private OffreEmploiService $offres;
    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        parent::__construct($db);
        $this->model = new DemandeRecrutementModel($this->db);
        $this->coherence = new CoherenceService($this->db);
        $this->offres = new OffreEmploiService($this->db);
    }
    public function creer(array $data, ?array $user = null): int
    {
        $data['statut'] = $this->id('status', 'En cours');
        $data['date_creation'] = $data['date_creation'] ?? date('Y-m-d H:i:s');
        $id = (int)$this->model->insert($data, true);
        $this->history($id, null, (int)$data['statut'], $user, 'Création');
        return $id;
    }
    public function modifier(int $id, array $data, ?array $user = null): void
    {
        $d = $this->row('demande_recrutement', 'id_demande', $id);
        $editable = [$this->id('status', 'En cours'), $this->id('status', 'À corriger')];
        if (!in_array((int)$d['statut'], $editable, true)) throw new \DomainException('Demande non modifiable dans cet état.');
        $this->model->update($id, array_intersect_key($data, array_flip(['id_poste', 'motif', 'id_profil', 'nom_personne_remplacee', 'type_contrat', 'duree_contrat', 'missions', 'date_souhaitee', 'remuneration_min', 'remuneration_max'])));
    }
    private function history(int $id, ?int $old, int $new, ?array $u, ?string $comment): void
    {
        $this->db->table('demande_recrutement_status_historique')->insert(['id_demande' => $id, 'statut_ancien' => $old ?? $new, 'statut_nouveau' => $new, 'date_changement' => date('Y-m-d H:i:s'), 'id_utilisateur' => $this->actor($u), 'commentaire' => $comment]);
    }
    private function change(int $id, string $to, ?array $u, ?string $comment = null): void
    {
        $d = $this->row('demande_recrutement', 'id_demande', $id);
        $new = $this->id('status', $to);
        if ((int)$d['statut'] === $new) return;
        $allowed = ['En cours' => ['En contrôle RH', 'Annulée'], 'À corriger' => ['En contrôle RH', 'Annulée'], 'En contrôle RH' => ['En validation budgétaire', 'À corriger', 'Refusée'], 'En validation budgétaire' => ['En validation hiérarchique', 'À corriger', 'Refusée'], 'En validation hiérarchique' => ['Validée', 'En consultation interne', 'À corriger', 'Refusée'], 'En consultation interne' => ['Validée', 'Refusée'], 'Validée' => ['Annulée']];
        $current = $this->designation((int)$d['statut']);
        if (!in_array($to, $allowed[$current] ?? [], true)) throw new \DomainException("Transition {$current} -> {$to} interdite.");
        $this->model->update($id, ['statut' => $new]);
        $this->history($id, (int)$d['statut'], $new, $u, $comment);
    }
    private function designation(int $id): string
    {
        return (string)$this->db->table('status')->where('id', $id)->get()->getRowArray()['designation'];
    }
    public function soumettre(int $id, ?array $u = null): void
    {
        $this->change($id, 'En contrôle RH', $u, 'Soumission');
    }
    public function controlerRh(int $id, string $decision, string $comment = '', ?int $validateur = null, ?array $u = null): void
    {
        $target = ['VALIDER' => 'En validation budgétaire', 'CORRIGER' => 'À corriger', 'REFUSER' => 'Refusée'][strtoupper($decision)] ?? throw new \InvalidArgumentException('Décision RH invalide');
        $this->decision($id, $target, $comment, $validateur, $u);
    }
    public function validerBudget(int $id, $montant = null, string $decision = 'VALIDER', string $comment = '', ?int $validateur = null, ?array $u = null): void
    {
        $target = ['VALIDER' => 'En validation hiérarchique', 'CORRIGER' => 'À corriger', 'REFUSER' => 'Refusée'][strtoupper($decision)] ?? throw new \InvalidArgumentException('Décision budget invalide');
        $this->decision($id, $target, $comment, $validateur, $u);
    }
    public function validerHierarchie(int $id, string $decision = 'FEU_VERT', bool $consultation = false, string $comment = '', ?int $validateur = null, ?array $u = null): void
    {
        $target = $consultation ? 'En consultation interne' : (['FEU_VERT' => 'Validée', 'VALIDER' => 'Validée', 'CORRIGER' => 'À corriger', 'REFUSER' => 'Refusée'][strtoupper($decision)] ?? throw new \InvalidArgumentException('Décision hiérarchique invalide'));
        $this->decision($id, $target, $comment, $validateur, $u);
    }
    private function decision(int $id, string $target, string $comment, ?int $validateur, ?array $u): void
    {
        $this->db->transBegin();
        try {
            $idValidateur = $validateur ?? $this->actorEmploye($u);
            if ($idValidateur === null) throw new \DomainException("Aucun employé n'est associé à votre compte : validation impossible.");
            $statutId = $this->id('status', $target);
            $this->change($id, $target, $u, $comment);
            $this->db->table('validation')->insert(['id_demande' => $id, 'id_validateur' => $idValidateur, 'statut_id' => $statutId, 'date_validation' => date('Y-m-d H:i:s'), 'commentaire' => $comment]);
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
    public function demanderConsultation(int $id, ?string $delai = null, ?array $u = null): void
    {
        $this->change($id, 'En consultation interne', $u, $delai);
    }
    public function cloturerConsultation(int $id, string $decision = 'VALIDER', string $comment = '', ?int $validateur = null, ?array $u = null): void
    {
        $this->decision($id, strtoupper($decision) === 'VALIDER' ? 'Validée' : 'Refusée', $comment, $validateur, $u);
    }
    public function donnerFeuVert(int $id, ?string $canal = null, ?array $u = null): int
    {
        $this->db->transStart();
        try {
            $d = $this->row('demande_recrutement', 'id_demande', $id);
            if ((int)$d['statut'] !== $this->id('status', 'Validée')) throw new \DomainException('La demande doit être validée avant le feu vert.');
            $offer = $this->offres->creerDepuisDemande($id, $u);
            if ($canal) $this->offres->modifier($offer, ['canal_diffusion' => $canal], $u);
            $this->db->transComplete();
            if ($this->db->transStatus() === false) throw new \RuntimeException('Transaction annulée.');
            return $offer;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
    public function annuler(int $id, ?string $motif = null, ?array $u = null): void
    {
        $this->change($id, 'Annulée', $u, $motif);
    }
    public function consulter(int $id): ?array
    {
        $row = $this->db->table('demande_recrutement d')
            ->select('d.*, st.designation AS designation_statut, p.intitule AS poste, se.nom AS service, dep.nom AS departement, tc.designation AS type_contrat_designation, e.nom AS demandeur_nom')
            ->join('status st', 'st.id=d.statut', 'left')
            ->join('poste p', 'p.id_poste=d.id_poste', 'left')
            ->join('service se', 'se.id_service=p.id_service', 'left')
            ->join('departement dep', 'dep.id_departement=se.id_departement', 'left')
            ->join('type_contrat tc', 'tc.id=d.type_contrat', 'left')
            ->join('employe e', 'e.id=d.id_demandeur', 'left')
            ->where('d.id_demande', $id)
            ->get()->getRowArray();

        return $row ?: null;
    }
    public function lister(array $f = []): array
    {
        $q = $this->db->table('demande_recrutement d')
            ->select('d.*, st.designation AS designation_statut, p.intitule')
            ->join('status st', 'st.id=d.statut', 'left')
            ->join('poste p', 'p.id_poste=d.id_poste', 'left');

        if (isset($f['statut']) && $f['statut'] !== '') {
            if (is_numeric($f['statut'])) {
                $statut = (int) $f['statut'];
            } else {
                $ligneStatut = $this->db->table('status')->select('id')->where('designation', $f['statut'])->get()->getRowArray();
                $statut = $ligneStatut ? (int) $ligneStatut['id'] : -1;
            }
            $q->where('d.statut', $statut);
        }

        foreach (['id_poste', 'id_demandeur'] as $x) {
            if (isset($f[$x]) && $f[$x] !== '') {
                $q->where('d.' . $x, $f[$x]);
            }
        }

        [$colonne, $ordre] = Tri::resoudre($f, ['id_demande' => 'd.id_demande', 'poste' => 'p.intitule', 'motif' => 'd.motif', 'remuneration' => 'd.remuneration_min', 'statut' => 'd.statut', 'date_creation' => 'd.date_creation'], 'd.id_demande', 'DESC');

        return $q->orderBy($colonne, $ordre)->get()->getResultArray();
    }
    public function historique(int $id): array
    {
        return $this->db->table('demande_recrutement_status_historique h')
            ->select('h.*, sa.designation AS statut_ancien_designation, sn.designation AS statut_nouveau_designation, u.identifiant AS utilisateur_identifiant')
            ->join('status sa', 'sa.id=h.statut_ancien', 'left')
            ->join('status sn', 'sn.id=h.statut_nouveau', 'left')
            ->join('utilisateur u', 'u.id_utilisateur=h.id_utilisateur', 'left')
            ->where('h.id_demande', $id)
            ->orderBy('h.date_changement')
            ->get()->getResultArray();
    }
    public function validations(int $id): array
    {
        return $this->db->table('validation v')
            ->select('v.*, st.designation AS statut_designation, e.nom AS validateur_nom, u.identifiant AS validateur_identifiant')
            ->join('status st', 'st.id=v.statut_id', 'left')
            ->join('employe e', 'e.id=v.id_validateur', 'left')
            ->join('utilisateur u', 'u.id_employe=e.id', 'left')
            ->where('v.id_demande', $id)
            ->orderBy('v.date_validation')
            ->get()->getResultArray();
    }
    public function verifierCoherence(int $id): array
    {
        return $this->coherence->verifierCoherence($id);
    }
    public function demandesEnAttente(string $role): array
    {
        $status = ['rh' => 'En contrôle RH', 'controle_gestion' => 'En validation budgétaire', 'direction' => 'En validation hiérarchique'][$role] ?? null;
        return $status ? $this->lister(['statut' => $this->id('status', $status)]) : [];
    }
}
