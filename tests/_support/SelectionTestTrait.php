<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Common\WorkflowService;
use App\Models\Common\ActionModel;
use App\Models\Common\JournalAuditModel;
use App\Models\Personnel\GrilleModel;
use App\Models\Personnel\NatureRevendicationModel;
use App\Models\Personnel\PropositionEmbaucheModel;
use App\Models\Personnel\ReadModels\CandidatureReadModel;
use App\Models\Personnel\ReadModels\StatusPropositionReadModel;
use App\Models\Personnel\RevendicationModel;
use App\Models\Personnel\StatusRevendicationModel;
use App\Models\Selection\EntretienModel;
use App\Models\Selection\EvaluationCandidatModel;
use App\Models\Selection\HistoriqueEntretienModel;
use App\Models\Selection\StatusEntretienModel;
use App\Services\Personnel\GrilleService;
use App\Services\Personnel\PropositionEmbaucheService;
use App\Services\Personnel\RevendicationService;
use App\Services\Recrutement\CandidatureService;
use App\Services\Selection\EntretienService;
use App\Services\Selection\EvaluationService;
use App\Services\Selection\SelectionService;
use CodeIgniter\Database\BaseConnection;

trait SelectionTestTrait
{
    protected ?BaseConnection $selectionDb = null;
    protected SessionUtilisateur $selectionUser;

    protected function demarrerContexteSelection(): void
    {
        try {
            $db = \Config\Database::connect('default', false);
            $db->query('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('PostgreSQL indisponible : ' . $e->getMessage());
        }

        if ($db->DBDriver !== 'Postgre') {
            $this->markTestSkipped('Les tests de sélection nécessitent PostgreSQL.');
        }

        if ($db->table('candidature')->where('id_candidature', 1002)->countAllResults() === 0) {
            $this->markTestSkipped('Fixtures embauche non chargées (script/fixtures_embauche.sql).');
        }

        if ($db->table('utilisateur')->where('id_utilisateur', 1002)->countAllResults() === 0) {
            $this->markTestSkipped('Utilisateur de test 1002 absent.');
        }

        $db->transBegin();

        $this->selectionDb   = $db;
        $this->selectionUser = new SessionUtilisateur(1002, 'rh_test', 'rh', 1, '127.0.0.1', date('c'), time());
    }

    protected function arreterContexteSelection(): void
    {
        if ($this->selectionDb !== null) {
            $this->selectionDb->transRollback();
            $this->selectionDb->close();
            $this->selectionDb = null;
        }
    }

    protected function auditService(): AuditService
    {
        return new AuditService(
            new JournalAuditModel($this->selectionDb),
            new ActionModel($this->selectionDb),
        );
    }

    protected function candidatureService(): CandidatureService
    {
        return new CandidatureService($this->selectionDb);
    }

    protected function entretienService(): EntretienService
    {
        return new EntretienService(
            new EntretienModel($this->selectionDb),
            new StatusEntretienModel($this->selectionDb),
            new HistoriqueEntretienModel($this->selectionDb),
            $this->candidatureService(),
            $this->auditService(),
            new WorkflowService($this->selectionDb),
        );
    }

    protected function evaluationService(): EvaluationService
    {
        return new EvaluationService(
            new EvaluationCandidatModel($this->selectionDb),
            $this->candidatureService(),
            $this->auditService(),
        );
    }

    protected function selectionService(): SelectionService
    {
        return new SelectionService(
            $this->evaluationService(),
            $this->candidatureService(),
            new CandidatureReadModel($this->selectionDb),
            $this->auditService(),
        );
    }

    protected function propositionService(): PropositionEmbaucheService
    {
        return new PropositionEmbaucheService(
            new PropositionEmbaucheModel($this->selectionDb),
            new RevendicationModel($this->selectionDb),
            new StatusPropositionReadModel($this->selectionDb),
            new CandidatureReadModel($this->selectionDb),
            new GrilleService(new GrilleModel($this->selectionDb)),
            $this->auditService(),
            new WorkflowService($this->selectionDb),
        );
    }

    protected function revendicationService(): RevendicationService
    {
        return new RevendicationService(
            new RevendicationModel($this->selectionDb),
            new StatusRevendicationModel($this->selectionDb),
            new NatureRevendicationModel($this->selectionDb),
            new StatusPropositionReadModel($this->selectionDb),
            $this->propositionService(),
            $this->auditService(),
            new WorkflowService($this->selectionDb),
        );
    }

    protected function assurerGrillePoste(int $idPoste = 1): void
    {
        if ($this->selectionDb->table('grille_salariale')->where('id_poste', $idPoste)->countAllResults() === 0) {
            $this->selectionDb->table('grille_salariale')->insert([
                'id_poste'    => $idPoste,
                'rang'        => 7,
                'salaire_min' => 30000,
                'salaire_max' => 80000,
                'date_debut'  => '2025-01-01',
            ]);
        }
    }
}
