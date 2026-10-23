<?php

declare(strict_types=1);

namespace App\Services\Selection;

use App\Models\Selection\EvaluationCandidatModel;
use App\Services\Recrutement\CandidatureService;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;

/**
 * Service métier du module Évaluation du candidat (RH.md §II.B.1).
 *
 * L'évaluation intervient après l'entretien. Plusieurs évaluateurs sont
 * possibles ; la note de décision est la moyenne des évaluations.
 */
class EvaluationService
{
    private const ROLES = ['rh', 'recruteur', 'direction', 'admin'];
    private const NOTE_MIN = 0.0;
    private const NOTE_MAX = 5.0;

    public function __construct(
        private readonly EvaluationCandidatModel $evaluationModel,
        private readonly CandidatureService $candidatureService,
        private readonly AuditService $auditService,
    ) {}

    // evaluer
    public function evaluer(array $nouvelle, SessionUtilisateur $u): int
    {
        $this->exigerRole($u);

        $idCandidature = (int) ($nouvelle['idCandidature'] ?? 0);
        if ($idCandidature <= 0) {
            throw new DonneesIncoherentesException('idCandidature obligatoire');
        }

        if ($this->candidatureService->consulter($idCandidature) === null) {
            throw new ObjetIntrouvableException("Candidature #{$idCandidature} introuvable");
        }

        $statutCandidature = $this->designationCandidature($idCandidature);
        if ($statutCandidature !== 'En évaluation') {
            throw new StatutInvalideException(
                "La candidature doit être En évaluation (statut actuel : {$statutCandidature})"
            );
        }

        $idEvaluateur = (int) ($nouvelle['idEvaluateur'] ?? $u->idEmploye ?? 0);
        if ($idEvaluateur <= 0) {
            throw new DonneesIncoherentesException('idEvaluateur obligatoire');
        }
        if ($this->employeExiste($idEvaluateur) === false) {
            throw new ObjetIntrouvableException("Évaluateur #{$idEvaluateur} introuvable");
        }

        $criteres = $this->normaliserCriteres($nouvelle['criteres'] ?? null);

        $note = $nouvelle['note'] ?? null;
        $note = ($note === null || $note === '') ? $this->calculerNote($criteres) : (float) $note;

        if ($note < self::NOTE_MIN || $note > self::NOTE_MAX) {
            throw new DonneesIncoherentesException('La note doit être comprise entre 0 et 5');
        }

        $idEvaluation = $this->evaluationModel->insert([
            'id_candidature' => $idCandidature,
            'id_evaluateur'  => $idEvaluateur,
            'criteres'       => json_encode($criteres, JSON_UNESCAPED_UNICODE),
            'note'           => round($note, 2),
            'commentaire'    => $nouvelle['commentaire'] ?? null,
            'date_evaluation'=> date('Y-m-d H:i:s'),
        ], true);

        if ($idEvaluation === false) {
            throw new \RuntimeException(
                'Échec insertion évaluation : ' . json_encode($this->evaluationModel->errors())
            );
        }

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'CREATE',
            'evaluation_candidat',
            (int) $idEvaluation,
            null,
            $u->adresseIp
        );

        return (int) $idEvaluation;
    }

    // calculerNote
    public function calculerNote(array $criteres): float
    {
        $valeurs = array_filter(
            array_map(static fn ($v) => is_numeric($v) ? (float) $v : null, $criteres),
            static fn ($v) => $v !== null
        );

        if ($valeurs === []) {
            throw new DonneesIncoherentesException('Au moins un critère est obligatoire');
        }

        foreach ($valeurs as $valeur) {
            if ($valeur < self::NOTE_MIN || $valeur > self::NOTE_MAX) {
                throw new DonneesIncoherentesException('Chaque critère doit être compris entre 0 et 5');
            }
        }

        return round(array_sum($valeurs) / count($valeurs), 2);
    }

    // noteMoyenne
    public function noteMoyenne(int $idCandidature): ?float
    {
        return $this->evaluationModel->moyenneParCandidature($idCandidature);
    }

    // synthese
    public function synthese(int $idCandidature): array
    {
        $evaluations = $this->listerParCandidature($idCandidature);

        return [
            'idCandidature'      => $idCandidature,
            'evaluations'        => $evaluations,
            'noteMoyenne'        => $this->noteMoyenne($idCandidature),
            'nombreEvaluations'  => count($evaluations),
        ];
    }

    // listerParCandidature
    public function listerParCandidature(int $idCandidature): array
    {
        return $this->evaluationModel->listerParCandidature($idCandidature);
    }

    // listerParOffre
    public function listerParOffre(int $idOffre): array
    {
        $evaluations = $this->evaluationModel->db->table('evaluation_candidat ec')
            ->select('ec.*, c.id_offre, ca.nom, ca.prenom, emp.nom AS evaluateur')
            ->join('candidature c', 'c.id_candidature = ec.id_candidature', 'left')
            ->join('candidat ca', 'ca.id_candidat = c.id_candidat', 'left')
            ->join('employe emp', 'emp.id = ec.id_evaluateur', 'left')
            ->where('c.id_offre', $idOffre)
            ->orderBy('ec.date_evaluation', 'ASC')
            ->get()
            ->getResultArray();

        return $evaluations;
    }

    // classerPourOffre
    public function classerPourOffre(int $idOffre): array
    {
        $candidatures = $this->evaluationModel->db->table('candidature c')
            ->select('c.id_candidature, ca.nom, ca.prenom, ca.email')
            ->join('candidat ca', 'ca.id_candidat = c.id_candidat', 'left')
            ->join('status_candidature sc', 'sc.id = c.statut', 'left')
            ->where('c.id_offre', $idOffre)
            ->where('sc.designation', 'En évaluation')
            ->get()
            ->getResultArray();

        $classement = [];

        foreach ($candidatures as $candidature) {
            $synthese = $this->synthese((int) $candidature['id_candidature']);
            $classement[] = array_merge($synthese, [
                'nom'    => $candidature['nom'],
                'prenom' => $candidature['prenom'],
                'email'  => $candidature['email'],
            ]);
        }

        usort($classement, static function (array $a, array $b): int {
            return ($b['noteMoyenne'] ?? -1) <=> ($a['noteMoyenne'] ?? -1);
        });

        return $classement;
    }

    // consulter
    public function consulter(int $idEvaluation): ?object
    {
        return $this->evaluationModel->find($idEvaluation);
    }

    private function normaliserCriteres(mixed $criteres): array
    {
        if (is_string($criteres)) {
            $decoded = json_decode($criteres, true);
            if (!is_array($decoded)) {
                throw new DonneesIncoherentesException('Le format des critères est invalide');
            }
            $criteres = $decoded;
        }

        if (!is_array($criteres) || $criteres === []) {
            throw new DonneesIncoherentesException('Les critères sont obligatoires');
        }

        return $criteres;
    }

    private function designationCandidature(int $idCandidature): string
    {
        $ligne = $this->evaluationModel->db->table('candidature c')
            ->select('sc.designation')
            ->join('status_candidature sc', 'sc.id = c.statut', 'left')
            ->where('c.id_candidature', $idCandidature)
            ->get()
            ->getRowArray();

        if ($ligne === null) {
            throw new ObjetIntrouvableException("Candidature #{$idCandidature} introuvable");
        }

        return (string) ($ligne['designation'] ?? '');
    }

    private function employeExiste(int $idEmploye): bool
    {
        return $this->evaluationModel->db->table('employe')
            ->where('id', $idEmploye)
            ->countAllResults() > 0;
    }

    private function exigerRole(SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(self::ROLES)) {
            throw new AccesRefuseException('Rôle insuffisant pour évaluer une candidature');
        }
    }
}
