<?php

declare(strict_types=1);

namespace App\Models\Conges;

use CodeIgniter\Model;

/**
 * Table : jour_ferie (id_jour_ferie, date_ferie, libelle, recurrent).
 * recurrent = true  : la date fixe revient chaque année (l'app compare mois/jour).
 * recurrent = false : date mobile (Pâques, Ascension, Pentecôte...).
 */
class JourFerieModel extends Model
{
    protected $table         = 'jour_ferie';
    protected $primaryKey    = 'id_jour_ferie';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'date_ferie',
        'libelle',
        'recurrent',
    ];
    protected $useTimestamps = false;

    public function listerPourAnnee(int $annee): array
    {
        $debut = sprintf('%04d-01-01', $annee);
        $fin   = sprintf('%04d-12-31', $annee);

        return $this->where('date_ferie >=', $debut)
            ->where('date_ferie <=', $fin)
            ->orderBy('date_ferie', 'ASC')
            ->findAll();
    }

    /**
     * Récupère les fériés couvrant $debut..$fin (pour CalendrierService).
     * Les lignes recurrent couvrent chaque année : ce Model retourne les
     * occurrences stockées dans la plage ; CalendrierService compare
     * mois/jour pour les libellés ricorrents d'autres années.
     */
    public function listerEntre(string $debut, string $fin): array
    {
        return $this->where('date_ferie >=', $debut)
            ->where('date_ferie <=', $fin)
            ->orderBy('date_ferie', 'ASC')
            ->findAll();
    }

    public function trouverParDate(string $date): ?object
    {
        return $this->where('date_ferie', $date)->first();
    }

    /**
     * Fériés d'une plage $debut..$fin (Y-m-d), occurrences réelles incluses.
     * Les lignes `recurrent` (date fixe) sont projetées sur chaque année de
     * la plage, comme `estJourFerie()` compare mois/jour. `listerEntre()`
     * seul raterait un récurrent stocké une autre année (ex. Noël 2026
     * affiché en décembre 2027).
     *
     * @return list<object> objets {date_ferie: Y-m-d, libelle, recurrent}, triés par date
     */
    public function listerEntreAvecRecurrents(string $debut, string $fin): array
    {
        $lignes = $this->groupStart()
            ->where('date_ferie >=', $debut)
            ->where('date_ferie <=', $fin)
            ->groupEnd()
            ->orWhere('recurrent', true)
            ->orderBy('date_ferie', 'ASC')
            ->findAll();

        $anneeDebut = (int) substr($debut, 0, 4);
        $anneeFin   = (int) substr($fin, 0, 4);
        $occurrences = [];

        foreach ($lignes as $ligne) {
            $dateStockee = substr((string) $ligne->date_ferie, 0, 10);

            if (empty($ligne->recurrent)) {
                if ($dateStockee >= $debut && $dateStockee <= $fin) {
                    $occurrences[$dateStockee . '|' . $ligne->libelle] = (object) [
                        'date_ferie' => $dateStockee,
                        'libelle'    => $ligne->libelle,
                        'recurrent'  => false,
                    ];
                }
                continue;
            }

            $moisJour = substr($dateStockee, 5, 5);
            for ($annee = $anneeDebut; $annee <= $anneeFin; $annee++) {
                $candidate = sprintf('%04d-%s', $annee, $moisJour);
                // Ignore les dates inexistantes (29 février hors année bissextile).
                if (date('Y-m-d', strtotime($candidate)) !== $candidate) {
                    continue;
                }
                if ($candidate >= $debut && $candidate <= $fin) {
                    $occurrences[$candidate . '|' . $ligne->libelle] = (object) [
                        'date_ferie' => $candidate,
                        'libelle'    => $ligne->libelle,
                        'recurrent'  => true,
                    ];
                }
            }
        }

        $resultat = array_values($occurrences);
        usort($resultat, static fn (object $a, object $b): int => strcmp((string) $a->date_ferie, (string) $b->date_ferie));

        return $resultat;
    }

    /**
     * Vrai si $date (Y-m-d) tombe sur un jour férié.
     * Gère recurrent = true en comparant (mois, jour).
     */
    public function estJourFerie(string $date): bool
    {
        $row = $this->db->table('jour_ferie')
            ->where('date_ferie', $date)
            ->get()
            ->getRow();

        if ($row !== null) {
            return true;
        }

        $mois = substr($date, 5, 5);

        return $this->db->table('jour_ferie')
            ->where('recurrent', true)
            ->where("SUBSTRING(CAST(date_ferie AS VARCHAR), 6, 5)", $mois)
            ->countAllResults() > 0;
    }
}
