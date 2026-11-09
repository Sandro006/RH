<?php

declare(strict_types=1);

namespace App\Services\Conges;

use App\Exceptions\CongeException;
use App\Models\Conges\JustificatifAbsenceModel;

/**
 * JustificatifService (RG08) : dépôt + validation/refus RH.
 * Le fichier lui-même est déposé par l'app (upload vers chemin) ; ce service
 * persiste la métadonnée et journalise.
 */
class JustificatifService
{
    public function __construct(
        private readonly JustificatifAbsenceModel $model,
        private readonly AuditService $audit,
    ) {
    }

    public function deposer(array $donnees, ?int $idUtilisateur = null): int
    {
        $nomFichier = trim((string) ($donnees['nom_fichier'] ?? ''));
        $chemin = trim((string) ($donnees['chemin'] ?? ''));
        $idAbsence = isset($donnees['id_absence']) ? (int) $donnees['id_absence'] : null;
        $idDemande = isset($donnees['id_demande_conge']) ? (int) $donnees['id_demande_conge'] : null;

        if ($nomFichier === '' || $chemin === '' || ($idAbsence === null && $idDemande === null)) {
            throw new CongeException('Fichier, chemin et cible (absence ou demande) sont requis', CongeException::DATES_INVALIDES);
        }

        $id = (int) $this->model->insert([
            'id_absence'       => $idAbsence,
            'id_demande_conge' => $idDemande,
            'nom_fichier'      => $nomFichier,
            'chemin'           => $chemin,
            'date_depot'       => date('Y-m-d H:i:s'),
            'valide'           => null,
            'id_validateur'    => null,
        ], true);

        $this->audit->journaliser($idUtilisateur, 'UPLOAD', 'justificatif_absence', $id, [
            'nom_fichier' => $nomFichier,
        ]);

        return $id;
    }

    public function valider(int $idJustificatif, ?int $idUtilisateur, ?string $commentaire = null): void
    {
        $this->majorerValidation($idJustificatif, true, $idUtilisateur, $commentaire);
    }

    public function refuser(int $idJustificatif, ?int $idUtilisateur, ?string $commentaire = null): void
    {
        $this->majorerValidation($idJustificatif, false, $idUtilisateur, $commentaire);
    }

    private function majoyerValidation(int $idJustificatif, bool $valide, ?int $idUtilisateur, ?string $commentaire): void
    {
        $justificatif = $this->model->find($idJustificatif);
        if ($justificatif === null) {
            throw new CongeException("Justificatif #{$idJustificatif} introuvable", CongeException::OBJET_INTROUVABLE);
        }

        $this->model->update($idJustificatif, [
            'valide'        => $valide,
            'id_validateur' => $idUtilisateur,
        ]);

        $this->audit->journaliser($idUtilisateur, $valide ? 'VALIDATION' : 'REFUS', 'justificatif_absence', $idJustificatif, [
            'valide' => $valide,
            'commentaire' => $commentaire,
        ]);
    }
}
