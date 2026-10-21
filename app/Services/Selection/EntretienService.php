<?php
namespace App\Services\Selection;

use App\Repositories\EntretienRepository;
use App\Repositories\HistoriqueRepository;

class EntretienService
{
    protected $db;
    protected EntretienRepository $repo;
    protected HistoriqueRepository $historique;
    protected $workflow;
    protected $candidatures;   // CandidatureLectureRepository (lecture/écriture directe)

    public function __construct()
    {
        $this->db           = \Config\Database::connect();
        $this->repo         = new EntretienRepository($this->db);
        $this->historique   = new HistoriqueRepository($this->db);
        $this->workflow     = new WorkflowService();
        $this->candidatures = new \App\Repositories\CandidatureLectureRepository($this->db);
    }

    public function planifier(array $n, int $idUtilisateur): int
    {
        $cand = $this->candidatures->trouverParId($n['idCandidature'])
            ?? throw new \RuntimeException('OBJET_INTROUVABLE');

        if ($cand['statut_libelle'] !== 'Présélectionnée') {
            throw new \DomainException('CANDIDATURE_NON_PRESELECTIONNEE'); // → 400
        }
        if (strtotime($n['dateEntretien']) < time()) {
            throw new \DomainException('DATE_PASSEE');
        }

        $this->db->transStart();

        $id = $this->repo->inserer([
            'id_candidature' => $n['idCandidature'],
            'id_recruteur'   => $n['idRecruteur'],
            'date_entretien' => $n['dateEntretien'],
            'type_entretien' => $n['typeEntretien'],
            'statut'         => $this->workflow->idStatut('PLANIFIE'),
        ]);

        // 1re ligne d'historique (aucun → Planifié)
        $this->historique->inserer([
            'id_entretien' => $id, 'statut_ancien' => null,
            'statut_nouveau' => $this->workflow->idStatut('PLANIFIE'),
            'date_changement' => date('Y-m-d H:i:s'),
            'id_utilisateur' => $idUtilisateur, 'commentaire' => 'Entretien planifié',
        ]);

        $this->candidatures->changerStatut($n['idCandidature'], 'Entretien prévu');

        $this->db->transComplete();
        if (!$this->db->transStatus()) throw new \RuntimeException('Échec de la planification');

        // notification : hors transaction (un mail raté ne doit pas annuler l'entretien)
        return $id;
    }

    public function realiser(int $idEntretien, string $compteRendu, int $idUtilisateur): void
    {
        if ($idEntretien <= 0) {
            throw new \DomainException('IDENTIFIANT_ENTRETIEN_INVALIDE');
        }
        if ($idUtilisateur <= 0) {
            throw new \RuntimeException('UTILISATEUR_NON_AUTHENTIFIE');
        }

        $compteRendu = trim($compteRendu);
        if ($compteRendu === '' || mb_strlen($compteRendu) < 10 || mb_strlen($compteRendu) > 5000) {
            throw new \DomainException('COMPTE_RENDU_INVALIDE');
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $compteRendu)) {
            throw new \DomainException('COMPTE_RENDU_INVALIDE');
        }

        $utilisateur = $this->db->table('utilisateur')
            ->select('role, actif')
            ->where('id_utilisateur', $idUtilisateur)
            ->get()
            ->getRowArray();
        if ($utilisateur === null || !(bool) $utilisateur['actif']) {
            throw new \RuntimeException('UTILISATEUR_NON_AUTORISE');
        }
        if (!in_array(strtolower((string) $utilisateur['role']), ['admin', 'rh', 'recruteur'], true)) {
            throw new \RuntimeException('ROLE_INSUFFISANT');
        }

        $e = $this->repo->trouverParId($idEntretien)
            ?? throw new \RuntimeException('OBJET_INTROUVABLE');

        $this->db->transStart();
        $this->repo->mettreAJourCompteRendu($idEntretien, $compteRendu);
        $this->workflow->changerStatut($idEntretien, 'REALISE', $idUtilisateur, 'Entretien réalisé');
        $this->candidatures->changerStatut((int) $e['id_candidature'], 'En évaluation');
        $this->db->transComplete();

        if (!$this->db->transStatus()) throw new \RuntimeException('Échec');
    }

    public function reporter(int $id, string $nouvelleDate, ?string $motif, int $idUtilisateur): void
    {
        if ($id <= 0) {
            throw new \DomainException('IDENTIFIANT_ENTRETIEN_INVALIDE');
        }
        if ($idUtilisateur <= 0) {
            throw new \RuntimeException('UTILISATEUR_NON_AUTHENTIFIE');
        }
        if ($this->repo->trouverParId($id) === null) {
            throw new \RuntimeException('OBJET_INTROUVABLE');
        }

        $timestamp = strtotime($nouvelleDate);
        if ($timestamp === false || $timestamp <= time()) {
            throw new \DomainException('DATE_INVALIDE');
        }

        $this->db->transStart();
        $this->workflow->changerStatut($id, 'REPORTE', $idUtilisateur, $motif);
        $this->repo->mettreAJourDate($id, $nouvelleDate);
        $this->workflow->changerStatut($id, 'PLANIFIE', $idUtilisateur, 'Re-planifié');
        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('Échec du report');
        }
    }

    public function confirmer(int $id, int $idUtilisateur): void
    {
        $this->workflow->changerStatut($id, 'CONFIRME', $idUtilisateur, 'Entretien confirmé');
    }

    public function annuler(int $id, ?string $motif, int $idUtilisateur): void
    {
        $this->workflow->changerStatut($id, 'ANNULE', $idUtilisateur, $motif ?? 'Entretien annulé');
    }

    public function lister(array $filtres): array
    {
        return $this->repo->lister($filtres);
    }

    public function candidaturesPreselectionnees(?int $idOffre = null): array
    {
        return $this->candidatures->listerPreselectionnees($idOffre);
    }

    public function recruteurs(): array
    {
        return $this->repo->listerRecruteurs();
    }

    public function types(): array
    {
        return $this->repo->listerTypes();
    }

    public function offresCloturees(): array
    {
        return $this->repo->listerOffresCloturees();
    }

    public function offres(): array
    {
        return $this->repo->listerOffres();
    }

    public function fiche(int $id): ?array
    {
        return $this->repo->trouverFiche($id);
    }

    public function historique(int $id): array
    {
        if ($this->repo->trouverParId($id) === null) {
            throw new \RuntimeException('OBJET_INTROUVABLE');
        }

        return $this->historique->listerParEntretien($id);
    }

    public function planifierPourOffre(int $idOffre, array $donnees, int $idUtilisateur): array
    {
        foreach (['idRecruteur', 'dateEntretien', 'typeEntretien'] as $champ) {
            if (!isset($donnees[$champ]) || $donnees[$champ] === '') {
                throw new \DomainException("{$champ} obligatoire");
            }
        }

        $offre = $this->db->table('offre_emploi o')
            ->select('o.id_offre')
            ->join('status_offre_emploi so', 'so.id = o.statut')
            ->where('o.id_offre', $idOffre)
            ->where('so.designation', 'Clôturée')
            ->get()
            ->getRowArray();
        if ($offre === null) {
            throw new \DomainException('OFFRE_NON_CLOTUREE');
        }

        $candidatures = $this->db->table('candidature c')
            ->select('c.id_candidature')
            ->join('status_candidature sc', 'sc.id = c.statut')
            ->where('c.id_offre', $idOffre)
            ->where('sc.designation', 'Présélectionnée')
            ->get()
            ->getResultArray();

        $ids = [];
        foreach ($candidatures as $candidature) {
            $ids[] = $this->planifier([
                'idCandidature' => (int) $candidature['id_candidature'],
                'idRecruteur' => (int) $donnees['idRecruteur'],
                'dateEntretien' => $donnees['dateEntretien'],
                'typeEntretien' => (int) $donnees['typeEntretien'],
            ], $idUtilisateur);
        }

        return $ids;
    }
}