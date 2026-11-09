<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Models\Finance\GrilleSalarialeModel;
use App\Models\Personnel\PosteModel;
use App\Models\Personnel\RangModel;

/**
 * Service CRUD pour la grille salariale
 * Gestion complète : création, lecture, mise à jour, suppression
 */
class GrilleSalarialeService
{
    public function __construct(
        private readonly GrilleSalarialeModel $grilleModel,
        private readonly PosteModel $posteModel,
        private readonly RangModel $rangModel,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * Liste toutes les grilles
     *
     * @return list<object>
     */
    public function lister(): array
    {
        return $this->grilleModel->listerToutes();
    }

    /**
     * Liste les grilles actives uniquement
     *
     * @return list<object>
     */
    public function listerActives(): array
    {
        return $this->grilleModel->listerActives();
    }

    /**
     * Trouve une grille par ID
     */
    public function trouver(int $id): object
    {
        $grille = $this->grilleModel->trouverAvecDetails($id);

        if ($grille === null) {
            throw new ObjetIntrouvableException("Grille salariale #{$id} introuvable");
        }

        return $grille;
    }

    /**
     * Crée une nouvelle grille salariale
     *
     * @param array{
     *     id_poste: int,
     *     rang: int,
     *     salaire_min: float,
     *     salaire_max: float,
     *     date_debut: string,
     *     date_fin?: string|null
     * } $donnees
     */
    public function creer(array $donnees, SessionUtilisateur $u): int
    {
        $this->validerDonnees($donnees);

        // Vérifier que le poste existe
        if ($this->posteModel->find($donnees['id_poste']) === null) {
            throw new DonneesIncoherentesException("Poste #{$donnees['id_poste']} introuvable");
        }

        // Vérifier que le rang existe
        if ($this->rangModel->find($donnees['rang']) === null) {
            throw new DonneesIncoherentesException("Rang #{$donnees['rang']} introuvable");
        }

        // Vérifier qu'il n'y a pas de chevauchement de dates pour le même poste
        $this->verifierChevauchement($donnees['id_poste'], $donnees['date_debut'], $donnees['date_fin'] ?? null);

        $id = $this->grilleModel->insert([
            'id_poste'    => $donnees['id_poste'],
            'rang'        => $donnees['rang'],
            'salaire_min' => $donnees['salaire_min'],
            'salaire_max' => $donnees['salaire_max'],
            'date_debut'  => $donnees['date_debut'],
            'date_fin'    => $donnees['date_fin'] ?? null,
        ], true);

        if ($id === false) {
            throw new \RuntimeException('Échec de la création de la grille salariale');
        }

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'CREATE',
            'grille_salariale',
            (int) $id,
            [
                'idPoste'    => $donnees['id_poste'],
                'rang'       => $donnees['rang'],
                'salaireMin' => $donnees['salaire_min'],
                'salaireMax' => $donnees['salaire_max'],
                'dateDebut'  => $donnees['date_debut'],
                'dateFin'    => $donnees['date_fin'] ?? null,
            ],
            $u->adresseIp
        );

        return (int) $id;
    }

    /**
     * Met à jour une grille salariale
     *
     * @param array{
     *     id_poste?: int,
     *     rang?: int,
     *     salaire_min?: float,
     *     salaire_max?: float,
     *     date_debut?: string,
     *     date_fin?: string|null
     * } $donnees
     */
    public function modifier(int $id, array $donnees, SessionUtilisateur $u): void
    {
        $grille = $this->grilleModel->find($id);

        if ($grille === null) {
            throw new ObjetIntrouvableException("Grille salariale #{$id} introuvable");
        }

        // Fusionner avec les données existantes
        $donneesMaj = array_merge([
            'id_poste'    => (int) $grille->id_poste,
            'rang'        => (int) $grille->rang,
            'salaire_min' => (float) $grille->salaire_min,
            'salaire_max' => (float) $grille->salaire_max,
            'date_debut'  => $grille->date_debut,
            'date_fin'    => $grille->date_fin,
        ], $donnees);

        $this->validerDonnees($donneesMaj);

        // Vérifier chevauchement (en excluant la grille courante)
        $this->verifierChevauchement(
            $donneesMaj['id_poste'],
            $donneesMaj['date_debut'],
            $donneesMaj['date_fin'] ?? null,
            $id
        );

        if ($this->grilleModel->update($id, $donneesMaj) === false) {
            throw new \RuntimeException('Échec de la mise à jour de la grille salariale');
        }

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'UPDATE',
            'grille_salariale',
            $id,
            $donneesMaj,
            $u->adresseIp
        );
    }

    /**
     * Supprime une grille salariale
     */
    public function supprimer(int $id, SessionUtilisateur $u): void
    {
        $grille = $this->grilleModel->find($id);

        if ($grille === null) {
            throw new ObjetIntrouvableException("Grille salariale #{$id} introuvable");
        }

        if ($this->grilleModel->delete($id) === false) {
            throw new \RuntimeException('Échec de la suppression de la grille salariale');
        }

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'DELETE',
            'grille_salariale',
            $id,
            null,
            $u->adresseIp
        );
    }

    /**
     * Valide les données d'une grille
     *
     * @param array<string, mixed> $donnees
     */
    private function validerDonnees(array $donnees): void
    {
        if ($donnees['salaire_min'] <= 0) {
            throw new DonneesIncoherentesException('Le salaire minimum doit être positif');
        }

        if ($donnees['salaire_max'] <= 0) {
            throw new DonneesIncoherentesException('Le salaire maximum doit être positif');
        }

        if ($donnees['salaire_max'] < $donnees['salaire_min']) {
            throw new DonneesIncoherentesException('Le salaire maximum doit être supérieur ou égal au salaire minimum');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $donnees['date_debut'])) {
            throw new DonneesIncoherentesException('Format de date de début invalide (attendu YYYY-MM-DD)');
        }

        if (isset($donnees['date_fin']) && $donnees['date_fin'] !== null) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $donnees['date_fin'])) {
                throw new DonneesIncoherentesException('Format de date de fin invalide (attendu YYYY-MM-DD)');
            }

            if ($donnees['date_fin'] < $donnees['date_debut']) {
                throw new DonneesIncoherentesException('La date de fin ne peut pas précéder la date de début');
            }
        }
    }

    /**
     * Vérifie qu'il n'y a pas de chevauchement de dates pour le même poste
     *
     * @param int $idGrilleExclue ID de la grille à exclure (pour modification)
     */
    private function verifierChevauchement(int $idPoste, string $dateDebut, ?string $dateFin, ?int $idGrilleExclue = null): void
    {
        $builder = $this->grilleModel->where('id_poste', $idPoste);

        if ($idGrilleExclue !== null) {
            $builder->where('id_grille !=', $idGrilleExclue);
        }

        $grillesExistantes = $builder->findAll();

        foreach ($grillesExistantes as $grille) {
            if ($this->periodesChevauchent(
                $dateDebut,
                $dateFin,
                $grille->date_debut,
                $grille->date_fin
            )) {
                throw new DonneesIncoherentesException(
                    'Chevauchement de dates avec une grille existante pour ce poste'
                );
            }
        }
    }

    /**
     * Vérifie si deux périodes se chevauchent
     */
    private function periodesChevauchent(
        string $debut1,
        ?string $fin1,
        string $debut2,
        ?string $fin2
    ): bool {
        // Si l'une des périodes n'a pas de fin, elle est considérée comme active
        $fin1Effective = $fin1 ?? '9999-12-31';
        $fin2Effective = $fin2 ?? '9999-12-31';

        // Pas de chevauchement si l'une fin avant le début de l'autre
        if ($fin1Effective < $debut2 || $fin2Effective < $debut1) {
            return false;
        }

        return true;
    }
}
