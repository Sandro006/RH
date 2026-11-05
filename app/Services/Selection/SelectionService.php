<?php

declare(strict_types=1);

namespace App\Services\Selection;

use App\Models\Personnel\ReadModels\CandidatureReadModel;
use App\Services\Recrutement\CandidatureService;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;

/**
 * Décision de sélection : seuil de rétention, classement et décision finale.
 * S'appuie sur EvaluationService (synthèse) et CandidatureService (workflow).
 */
class SelectionService
{
    private const ROLES = ['rh', 'recruteur', 'direction', 'admin'];
    private const SEUIL_PAR_DEFAUT = 3.0;

    public function __construct(
        private readonly EvaluationService $evaluationService,
        private readonly CandidatureService $candidatureService,
        private readonly CandidatureReadModel $candidatureModel,
        private readonly AuditService $auditService,
    ) {}

    public function seuilRetention(): float
    {
        return (float) env('EVALUATION_SEUIL_RETENTION', self::SEUIL_PAR_DEFAUT);
    }

    // candidatsEligibles
    public function candidatsEligibles(int $idOffre): array
    {
        $seuil = $this->seuilRetention();

        return array_values(array_filter(
            $this->evaluationService->classerPourOffre($idOffre),
            static fn (array $synthese): bool => ($synthese['nombreEvaluations'] ?? 0) > 0
                && ($synthese['noteMoyenne'] ?? 0) >= $seuil
        ));
    }

    // retenir
    public function retenir(int $idCandidature, SessionUtilisateur $u): void
    {
        $this->exigerRole($u);

        $candidature = $this->candidatureService->consulter($idCandidature);
        if ($candidature === null) {
            throw new ObjetIntrouvableException("Candidature #{$idCandidature} introuvable");
        }

        $statut = $this->designationStatut((int) $candidature['statut']);
        if ($statut !== 'En évaluation') {
            throw new StatutInvalideException(
                "La candidature doit être En évaluation (statut actuel : {$statut})"
            );
        }

        $synthese = $this->evaluationService->synthese($idCandidature);
        if ($synthese['nombreEvaluations'] === 0) {
            throw new DonneesIncoherentesException('Aucune évaluation disponible pour cette candidature');
        }

        if (($synthese['noteMoyenne'] ?? 0) < $this->seuilRetention()) {
            throw new DonneesIncoherentesException(
                "Note moyenne {$synthese['noteMoyenne']} inférieure au seuil de rétention {$this->seuilRetention()}"
            );
        }

        $this->candidatureService->retenir($idCandidature, $u->toArray());

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'VALIDATION',
            'candidature',
            $idCandidature,
            ['noteMoyenne' => $synthese['noteMoyenne']],
            $u->adresseIp
        );
    }

    // refuser
    public function refuser(int $idCandidature, ?string $motif, SessionUtilisateur $u): void
    {
        $this->exigerRole($u);

        $candidature = $this->candidatureService->consulter($idCandidature);
        if ($candidature === null) {
            throw new ObjetIntrouvableException("Candidature #{$idCandidature} introuvable");
        }

        $statut = $this->designationStatut((int) $candidature['statut']);
        if ($statut !== 'En évaluation') {
            throw new StatutInvalideException(
                "La candidature doit être En évaluation (statut actuel : {$statut})"
            );
        }

        $this->candidatureService->refuser($idCandidature, $motif, $u->toArray());

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'REFUS',
            'candidature',
            $idCandidature,
            $motif,
            $u->adresseIp
        );
    }

    // decider
    public function decider(int $idCandidature, bool $retenue, SessionUtilisateur $u, ?string $motif = null): array
    {
        $noteMoyenne = $this->evaluationService->noteMoyenne($idCandidature);

        if ($retenue) {
            $this->retenir($idCandidature, $u);
        } else {
            $this->refuser($idCandidature, $motif, $u);
        }

        return [
            'idCandidature' => $idCandidature,
            'retenue'       => $retenue,
            'noteMoyenne'   => $noteMoyenne,
            'motif'         => $motif,
        ];
    }

    public function classerPourOffre(int $idOffre): array
    {
        return $this->evaluationService->classerPourOffre($idOffre);
    }

    private function designationStatut(int $idStatut): string
    {
        $ligne = $this->candidatureModel->db->table('status_candidature')
            ->select('designation')
            ->where('id', $idStatut)
            ->get()
            ->getRowArray();

        return (string) ($ligne['designation'] ?? '');
    }

    private function exigerRole(SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(self::ROLES)) {
            throw new AccesRefuseException('Rôle insuffisant pour décider de la sélection');
        }
    }
}
