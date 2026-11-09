<?php

declare(strict_types=1);

namespace App\Models\Finance;

use CodeIgniter\Model;

/**
 * Model CRUD pour la grille salariale
 * Accès réservé au rôle finance (et admin)
 */
class GrilleSalarialeModel extends Model
{
    protected $table            = 'grille_salariale';
    protected $primaryKey       = 'id_grille';
    protected $returnType       = 'object';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id_poste',
        'rang',
        'salaire_min',
        'salaire_max',
        'date_debut',
        'date_fin',
    ];

    /**
     * Liste toutes les grilles avec jointures poste et rang
     *
     * @return list<object>
     */
    public function listerToutes(): array
    {
        return $this->select('
                g.id_grille,
                g.id_poste,
                p.intitule AS poste,
                g.rang,
                r.designation AS rang_designation,
                g.salaire_min,
                g.salaire_max,
                g.date_debut,
                g.date_fin,
                g.date_creation
            ')
            ->from('grille_salariale g')
            ->join('poste p', 'p.id_poste = g.id_poste')
            ->join('rang r', 'r.id = g.rang')
            ->orderBy('g.date_debut', 'DESC')
            ->orderBy('p.intitule', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * Liste les grilles actives (date_fin NULL ou future)
     *
     * @return list<object>
     */
    public function listerActives(): array
    {
        return $this->select('
                g.id_grille,
                g.id_poste,
                p.intitule AS poste,
                g.rang,
                r.designation AS rang_designation,
                g.salaire_min,
                g.salaire_max,
                g.date_debut,
                g.date_fin,
                g.date_creation
            ')
            ->from('grille_salariale g')
            ->join('poste p', 'p.id_poste = g.id_poste')
            ->join('rang r', 'r.id = g.rang')
            ->groupStart()
                ->where('g.date_fin', null)
                ->orWhere('g.date_fin >=', date('Y-m-d'))
            ->groupEnd()
            ->orderBy('p.intitule', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * Liste les grilles pour un poste donné
     *
     * @return list<object>
     */
    public function listerParPoste(int $idPoste): array
    {
        return $this->select('
                g.id_grille,
                g.id_poste,
                p.intitule AS poste,
                g.rang,
                r.designation AS rang_designation,
                g.salaire_min,
                g.salaire_max,
                g.date_debut,
                g.date_fin,
                g.date_creation
            ')
            ->from('grille_salariale g')
            ->join('poste p', 'p.id_poste = g.id_poste')
            ->join('rang r', 'r.id = g.rang')
            ->where('g.id_poste', $idPoste)
            ->orderBy('g.date_debut', 'DESC')
            ->get()
            ->getResult();
    }

    /**
     * Trouve une grille par ID avec jointures
     */
    public function trouverAvecDetails(int $id): ?object
    {
        return $this->select('
                g.id_grille,
                g.id_poste,
                p.intitule AS poste,
                g.rang,
                r.designation AS rang_designation,
                g.salaire_min,
                g.salaire_max,
                g.date_debut,
                g.date_fin,
                g.date_creation
            ')
            ->from('grille_salariale g')
            ->join('poste p', 'p.id_poste = g.id_poste')
            ->join('rang r', 'r.id = g.rang')
            ->where('g.id_grille', $id)
            ->first();
    }

    /**
     * Vérifie si une grille est active à une date donnée
     */
    public function estActive(int $idGrille, ?string $date = null): bool
    {
        $date = $date ?? date('Y-m-d');
        
        $grille = $this->find($idGrille);
        
        if ($grille === null) {
            return false;
        }

        if ($grille->date_debut > $date) {
            return false;
        }

        if ($grille->date_fin !== null && $grille->date_fin < $date) {
            return false;
        }

        return true;
    }
}
