<?php

declare(strict_types=1);

namespace App\Services\Selection;

use App\Exceptions\AccesRefuseException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Common\WorkflowService;
use App\Models\Selection\EvaluationCandidatModel;
use CodeIgniter\Database\BaseConnection;

class EvaluationService
{
    public function __construct(
        private readonly ?BaseConnection $db = null,
        private readonly ?EvaluationCandidatModel $evaluationModel = null,
        private readonly ?WorkflowService $workflow = null,
    ) {
    }

    private function database(): BaseConnection
    {
        return $this->db ?? db_connect();
    }

    private function evaluations(): EvaluationCandidatModel
    {
        return $this->evaluationModel ?? new EvaluationCandidatModel($this->database());
    }

    private function workflowService(): WorkflowService
    {
        return $this->workflow ?? new WorkflowService($this->database());
    }

    public function calculerNote(array $criteres): float
    {
        $noms = [
            'competencesTechniques', 'experience', 'communication',
            'motivation', 'adequationPoste',
        ];
        $valeurs = [];
        foreach ($noms as $nom) {
            $valeur = $criteres[$nom] ?? $criteres[lcfirst($nom)] ?? null;
            if (!is_numeric($valeur) || (float) $valeur < 0 || (float) $valeur > 5) {
                throw new DonneesIncoherentesException("Le critère {$nom} doit être compris entre 0 et 5");
            }
            $valeurs[] = (float) $valeur;
        }

        return round(array_sum($valeurs) / count($valeurs), 2);
    }

    public function evaluer(array $nouvelle, SessionUtilisateur $utilisateur): int
    {
        $this->exigerRole($utilisateur);
        $idCandidature = (int) ($nouvelle['idCandidature'] ?? $nouvelle['id_candidature'] ?? 0);
        if ($idCandidature < 1) {
            throw new DonneesIncoherentesException('idCandidature est obligatoire');
        }

        $candidature = $this->candidature($idCandidature);
        $this->exigerStatut($candidature, 'En évaluation');
        $criteres = $nouvelle['criteres'] ?? [];
        if (is_string($criteres)) {
            $criteres = json_decode($criteres, true) ?: [];
        }
        if (!is_array($criteres)) {
            throw new DonneesIncoherentesException('criteres doit être un objet JSON');
        }

        $note = $nouvelle['note'] ?? null;
        $note = $note === null || $note === '' ? $this->calculerNote($criteres) : (float) $note;
        if ($note < 0 || $note > 5) {
            throw new DonneesIncoherentesException('La note doit être comprise entre 0 et 5');
        }

        $idEvaluateur = $utilisateur->idEmploye ?? (int) ($nouvelle['idEvaluateur'] ?? 0);
        if ($idEvaluateur < 1) {
            throw new DonneesIncoherentesException('L’utilisateur doit être rattaché à un employé');
        }

        $id = $this->evaluations()->insert([
            'id_candidature' => $idCandidature,
            'id_evaluateur' => $idEvaluateur,
            'criteres' => json_encode($criteres, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'note' => round($note, 2),
            'commentaire' => $nouvelle['commentaire'] ?? null,
            'date_evaluation' => date('Y-m-d H:i:s'),
        ], true);

        if ($id === false) {
            throw new \RuntimeException('Échec de l’enregistrement de l’évaluation');
        }
        return (int) $id;
    }

    public function listerParCandidature(int $idCandidature): array
    {
        $rows = $this->evaluations()->where('id_candidature', $idCandidature)->orderBy('date_evaluation', 'ASC')->findAll();
        return array_map([$this, 'normaliser'], $rows);
    }

    public function listerParOffre(int $idOffre): array
    {
        $rows = $this->database()->table('evaluation_candidat e')
            ->select('e.*, c.id_offre')
            ->join('candidature c', 'c.id_candidature = e.id_candidature')
            ->where('c.id_offre', $idOffre)
            ->orderBy('e.date_evaluation', 'ASC')->get()->getResultArray();
        return array_map([$this, 'normaliser'], $rows);
    }

    public function noteMoyenne(int $idCandidature): float
    {
        $row = $this->database()->table('evaluation_candidat')
            ->selectAvg('note', 'moyenne')->where('id_candidature', $idCandidature)->get()->getRowArray();
        return $row['moyenne'] === null ? 0.0 : round((float) $row['moyenne'], 2);
    }

    public function synthese(int $idCandidature): array
    {
        $evaluations = $this->listerParCandidature($idCandidature);
        return [
            'idCandidature' => $idCandidature,
            'evaluations' => $evaluations,
            'noteMoyenne' => $this->noteMoyenne($idCandidature),
            'nombreEvaluations' => count($evaluations),
        ];
    }

    public function classerPourOffre(int $idOffre): array
    {
        $ids = $this->database()->table('candidature c')
            ->select('c.id_candidature')->join('status_candidature s', 's.id = c.statut')
            ->where('c.id_offre', $idOffre)->where('s.designation', 'En évaluation')
            ->get()->getResultArray();
        $resultats = array_map(fn(array $row) => $this->synthese((int) $row['id_candidature']), $ids);
        usort($resultats, fn(array $a, array $b) => $b['noteMoyenne'] <=> $a['noteMoyenne']);
        return $resultats;
    }

    public function retenir(int $idCandidature, SessionUtilisateur $utilisateur): array
    {
        $this->exigerRole($utilisateur);
        $candidature = $this->candidature($idCandidature);
        $this->exigerStatut($candidature, 'En évaluation');
        $synthese = $this->synthese($idCandidature);
        if ($synthese['nombreEvaluations'] === 0 || $synthese['noteMoyenne'] < 3.0) {
            throw new DonneesIncoherentesException('La note moyenne est insuffisante pour retenir la candidature');
        }
        $this->workflowService()->changerStatut('candidature', $idCandidature, 'Retenue', $utilisateur, 'Décision de sélection favorable');
        return ['idCandidature' => $idCandidature, 'retenue' => true, 'noteMoyenne' => $synthese['noteMoyenne']];
    }

    public function refuser(int $idCandidature, ?string $motif, SessionUtilisateur $utilisateur): array
    {
        $this->exigerRole($utilisateur);
        $this->exigerStatut($this->candidature($idCandidature), 'En évaluation');
        $this->workflowService()->changerStatut('candidature', $idCandidature, 'Refusée', $utilisateur, $motif);
        return ['idCandidature' => $idCandidature, 'retenue' => false, 'motif' => $motif];
    }

    private function candidature(int $id): array
    {
        $row = $this->database()->table('candidature c')->select('c.*, s.designation AS statut_designation')
            ->join('status_candidature s', 's.id = c.statut')->where('c.id_candidature', $id)->get()->getRowArray();
        if ($row === null) throw new ObjetIntrouvableException("Candidature #{$id} introuvable");
        return $row;
    }

    private function exigerStatut(array $candidature, string $statut): void
    {
        if ($candidature['statut_designation'] !== $statut) throw new StatutInvalideException("La candidature doit être {$statut}");
    }

    private function exigerRole(SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'recruteur', 'direction', 'admin'])) throw new AccesRefuseException('Rôle insuffisant pour évaluer un candidat');
    }

    private function normaliser(array $row): array
    {
        $row['criteres'] = is_string($row['criteres']) ? (json_decode($row['criteres'], true) ?: []) : ($row['criteres'] ?? []);
        $row['note'] = $row['note'] === null ? null : (float) $row['note'];
        return $row;
    }
}
