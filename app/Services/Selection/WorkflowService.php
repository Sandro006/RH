<?php
namespace App\Services\Selection;

use App\Repositories\EntretienRepository;
use App\Repositories\HistoriqueRepository;
use CodeIgniter\Database\BaseConnection;

class WorkflowService
{
    // table §8 du doc de conception
    private const TRANSITIONS = [
        'PLANIFIE' => ['CONFIRME', 'REPORTE', 'REALISE', 'ANNULE'],
        'CONFIRME' => ['REPORTE', 'REALISE', 'ANNULE'],
        'REPORTE'  => ['PLANIFIE', 'ANNULE'],
        'REALISE'  => [],
        'ANNULE'   => [],
    ];

    private const LIBELLES = [
        'PLANIFIE' => 'Planifié', 'CONFIRME' => 'Confirmé', 'REALISE' => 'Réalisé',
        'REPORTE'  => 'Reporté',  'ANNULE'   => 'Annulé',
    ];

    protected BaseConnection $db;
    protected EntretienRepository $repo;
    protected HistoriqueRepository $historique;

    public function __construct()
    {
        $this->db   = \Config\Database::connect();
        $this->repo = new EntretienRepository($this->db);
        $this->historique = new HistoriqueRepository($this->db);
    }

    public function idStatut(string $code): int
    {
        $row = $this->db->table('status_entretien')
            ->where('designation', self::LIBELLES[$code])->get()->getRowArray();
        if (!$row) throw new \RuntimeException("Statut inconnu : $code");
        return (int) $row['id'];
    }

    public function designationStatut(int $id): string
    {
        $row = $this->db->table('status_entretien')->where('id', $id)->get()->getRowArray();
        return array_search($row['designation'], self::LIBELLES, true);
    }

    public function transitionAutorisee(string $actuel, string $cible): bool
    {
        return in_array($cible, self::TRANSITIONS[$actuel] ?? [], true);
    }

    public function changerStatut(int $idEntretien, string $cible, int $idUtilisateur, ?string $commentaire = null): void
    {
        $e = $this->repo->trouverParId($idEntretien)
            ?? throw new \RuntimeException('OBJET_INTROUVABLE');

        $actuel = $this->designationStatut((int) $e['statut']);
        if (!$this->transitionAutorisee($actuel, $cible)) {
            throw new \DomainException('STATUT_INVALIDE');
        }

        $nouvelId = $this->idStatut($cible);
        $this->repo->mettreAJourStatut($idEntretien, $nouvelId);

        $this->historique->inserer([
            'id_entretien'    => $idEntretien,
            'statut_ancien'   => $e['statut'],
            'statut_nouveau'  => $nouvelId,
            'date_changement' => date('Y-m-d H:i:s'),
            'id_utilisateur'  => $idUtilisateur,
            'commentaire'     => $commentaire,
        ]);
    }
}