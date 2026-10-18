<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use CodeIgniter\Model;
use App\Entities\Personnel\Contrat;

class ContratModel extends Model
{
    protected $table         = 'contrat';
    protected $primaryKey    = 'id_contrat';
    protected $returnType    = Contrat::class;
    protected $allowedFields = [
        'id_salarie', 'id_poste', 'type_contrat_id',
        'date_debut', 'date_fin', 'salaire_base',
        'periode_essai_debut', 'periode_essai_fin',
        'date_signature', 'statut',
    ];
    protected $useTimestamps = false;

    public function listerParSalarie(int $idSalarie): array
    {
        return $this->where('id_salarie', $idSalarie)
                    ->orderBy('date_debut', 'DESC')
                    ->findAll();
    }

    /**
     * Contrat actuellement En cours (statut = 4).
     */
    public function contratEnCours(int $idSalarie): ?Contrat
    {
        return $this->where('id_salarie', $idSalarie)
                    ->where('statut', 4)
                    ->first();
    }

    /**
     * Compte les renouvellements pour un salarié ET un type de contrat.
     */
    public function compterRenouvellements(int $idSalarie, int $typeContratId): int
    {
        $count = $this->where('id_salarie', $idSalarie)
                      ->where('type_contrat_id', $typeContratId)
                      ->countAllResults();
        return max(0, $count - 1);
    }

    /**
     * Lit le rang d'un poste (référentiel Iavo) via jointure.
     */
    public function rangDuPoste(int $idPoste): ?string
    {
        $row = $this->db->table('poste')
                        ->select('rang.designation AS designation')
                        ->join('rang', 'rang.id = poste.rang_id', 'left')
                        ->where('poste.id_poste', $idPoste)
                        ->get()
                        ->getRow();
        return $row?->designation;
    }

    /**
     * Lit la fourchette salariale active d'un poste (référentiel Iavo).
     * @return array{min: float, max: float}|null
     */
    public function fourchetteSalariale(int $idPoste): ?array
    {
        $row = $this->db->table('grille_salariale')
                        ->where('id_poste', $idPoste)
                        ->where('date_debut <=', date('Y-m-d'))
                        ->groupStart()
                            ->where('date_fin', null)
                            ->orWhere('date_fin >=', date('Y-m-d'))
                        ->groupEnd()
                        ->orderBy('date_debut', 'DESC')
                        ->get()
                        ->getRow();

        if ($row === null) {
            return null;
        }

        return [
            'min' => (float) $row->salaire_min,
            'max' => (float) $row->salaire_max,
        ];
    }

    public function posteExiste(int $idPoste): bool
    {
        return $this->db->table('poste')->where('id_poste', $idPoste)->countAllResults() > 0;
    }
}