<?php
namespace App\Services;

class EntretienService
{
    protected $db;
    protected $repo;
    protected $workflow;
    protected $candidatures;   // CandidatureLectureRepository (lecture/écriture directe)

    public function __construct()
    {
        $this->db           = \Config\Database::connect();
        $this->repo         = new \App\Repositories\EntretienRepository($this->db);
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
        $this->db->table('entretien_status_historique')->insert([
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
        $this->db->transStart();
        $this->workflow->changerStatut($id, 'REPORTE', $idUtilisateur, $motif);
        $this->repo->mettreAJourDate($id, $nouvelleDate);
        $this->workflow->changerStatut($id, 'PLANIFIE', $idUtilisateur, 'Re-planifié');
        $this->db->transComplete();
    }
}