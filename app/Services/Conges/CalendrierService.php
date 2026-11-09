<?php

declare(strict_types=1);

namespace App\Services\Conges;

use App\Models\Conges\JourFerieModel;

/**
 * Calendrier des jours ouvrés (RG03, RG13).
 * Règle par défaut : lundi-vendredi (N ∈ 1..5). Pour basculer en
 * lundi-samedi, étendre JOURS_OUVRES à [1,2,3,4,5,6].
 * Les demi-journées n'apportent que 0,5 jour (jamais négatif).
 * Les jours fériés sont lus via JourFerieModel (recurrent géré en mois/jour).
 */
class CalendrierService
{
    /** @var list<int> */
    private const JOURS_OUVRES = [1, 2, 3, 4, 5];

    public function __construct(
        private readonly JourFerieModel $jourFerieModel,
    ) {
    }

    public function estJourOuvre(\DateTimeInterface $date): bool
    {
        return in_array((int) $date->format('N'), self::JOURS_OUVRES, true)
            && ! $this->estJourFerie($date);
    }

    public function estJourFerie(\DateTimeInterface $date): bool
    {
        return $this->jourFerieModel->estJourFerie($date->format('Y-m-d'));
    }

    /**
     * Nombre de jours ouvrés entre $debut et $fin (inclus), demi-journées
     * incluses. `2026-11-02..2026-11-06` → 5.
     */
    public function calculerNbJours(string $debut, string $fin, bool $demiDebut = false, bool $demiFin = false): float
    {
        $d = new \DateTimeImmutable($debut);
        $f = new \DateTimeImmutable($fin);

        if ($f < $d) {
            return 0.0;
        }

        if ($d->format('Y-m-d') === $f->format('Y-m-d')) {
            if (! $this->estJourOuvre($d)) {
                return 0.0;
            }
            return ($demiDebut xor $demiFin) ? 0.5 : 1.0;
        }

        $nb = 0.0;
        $courant = $d;

        while ($courant < $f) {
            if ($this->estJourOuvre($courant)) {
                $nb += 1.0;
            }
            $courant = $courant->modify('+1 day');
        }

        if ($this->estJourOuvre($f)) {
            $nb += 1.0;
        }

        if ($demiDebut) {
            $nb -= 0.5;
        }
        if ($demiFin) {
            $nb -= 0.5;
        }

        return max(0.0, $nb);
    }

    /**
     * Répartition en jours ouvrés par année (RG13).
     * @return array<int, float> ex [2026 => 2.0, 2027 => 3.5]
     */
    public function repartirParAnnee(string $debut, string $fin, bool $demiDebut = false, bool $demiFin = false): array
    {
        $jours = $this->joursOuvresDetaille($debut, $fin, $demiDebut, $demiFin);
        $parAnnee = [];

        foreach ($jours as $jour => $valeur) {
            $annee = (int) substr($jour, 0, 4);
            $parAnnee[$annee] = ($parAnnee[$annee] ?? 0.0) + $valeur;
        }

        return $parAnnee;
    }

    /**
     * Répartition en jours ouvrés par mois (paie).
     * @return array<string, float> ex ['2026-12' => 1.0, '2027-01' => 2.0]
     */
    public function repartirParMois(string $debut, string $fin, bool $demiDebut = false, bool $demiFin = false): array
    {
        $jours = $this->joursOuvresDetaille($debut, $fin, $demiDebut, $demiFin);
        $parMois = [];

        foreach ($jours as $jour => $valeur) {
            $mois = substr($jour, 0, 7);
            $parMois[$mois] = ($parMois[$mois] ?? 0.0) + $valeur;
        }

        return $parMois;
    }

    /**
     * Détail jour par jour (Y-m-d => valeur), week-ends/fériés exclus.
     * @return array<string, float>
     */
    public function joursOuvresDetaille(string $debut, string $fin, bool $demiDebut = false, bool $demiFin = false): array
    {
        $d = new \DateTimeImmutable($debut);
        $f = new \DateTimeImmutable($fin);
        $jours = [];

        while ($d <= $f) {
            if ($this->estJourOuvre($d)) {
                $valeur = 1.0;
                if ($d->format('Y-m-d') === $debut && $demiDebut) {
                    $valeur = 0.5;
                }
                if ($d->format('Y-m-d') === $fin && $demiFin && $debut !== $fin) {
                    $valeur = 0.5;
                }
                $jours[$d->format('Y-m-d')] = $valeur;
            }
            $d = $d->modify('+1 day');
        }

        return $jours;
    }
}
