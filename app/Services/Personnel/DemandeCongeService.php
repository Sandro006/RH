<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Exceptions\DonneesIncoherentesException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Models\Personnel\DemandeCongeModel;

class DemandeCongeService
{
    private const TYPES_AUTORISES = [
        'Congé payé',
        'Congé sans solde',
        'Congé maladie',
        'Congé exceptionnel',
        'Autre',
    ];

    public function __construct(
        private readonly DemandeCongeModel $model,
        private readonly AuditService $auditService,
    ) {
    }

    public function listerPourUtilisateur(SessionUtilisateur $u): array
    {
        if ($u->idEmploye !== null) {
            $parEmploye = $this->model->listerParEmploye($u->idEmploye);
            if ($parEmploye !== []) {
                return $parEmploye;
            }
        }

        return $this->model->listerParUtilisateur($u->idUtilisateur);
    }

    public function creer(array $donnees, SessionUtilisateur $u): int
    {
        $typeConge = trim((string) ($donnees['type_conge'] ?? $donnees['typeConge'] ?? ''));
        $dateDebut = trim((string) ($donnees['date_debut'] ?? $donnees['dateDebut'] ?? ''));
        $dateFin = trim((string) ($donnees['date_fin'] ?? $donnees['dateFin'] ?? ''));
        $motif = trim((string) ($donnees['motif'] ?? ''));
        $justification = isset($donnees['justification'])
            ? trim((string) $donnees['justification'])
            : null;

        if ($typeConge === '') {
            throw new DonneesIncoherentesException('Le type de congé est requis');
        }
        if (!in_array($typeConge, self::TYPES_AUTORISES, true)) {
            throw new DonneesIncoherentesException('Type de congé invalide');
        }
        if (!$this->estDateValide($dateDebut)) {
            throw new DonneesIncoherentesException('Date de début invalide (attendu YYYY-MM-DD)');
        }
        if (!$this->estDateValide($dateFin)) {
            throw new DonneesIncoherentesException('Date de fin invalide (attendu YYYY-MM-DD)');
        }
        if ($dateFin < $dateDebut) {
            throw new DonneesIncoherentesException('La date de fin ne peut pas précéder la date de début');
        }
        if ($motif === '') {
            throw new DonneesIncoherentesException('Le motif est requis');
        }
        if (mb_strlen($motif) > 255) {
            throw new DonneesIncoherentesException('Le motif ne doit pas dépasser 255 caractères');
        }

        $id = $this->model->insert([
            'id_utilisateur' => $u->idUtilisateur,
            'id_employe' => $u->idEmploye,
            'type_conge' => $typeConge,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'motif' => $motif,
            'justification' => ($justification === '' ? null : $justification),
            'statut' => 'En attente',
        ], true);

        if ($id === false) {
            throw new \RuntimeException('Échec de la création de la demande de congé');
        }

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'CREATE',
            'demande_conge',
            (int) $id,
            [
                'type_conge' => $typeConge,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'motif' => $motif,
            ],
            $u->adresseIp
        );

        return (int) $id;
    }

    private function estDateValide(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
}
