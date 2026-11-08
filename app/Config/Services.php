<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
	public static function sessionManager(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('sessionManager');
		return new \App\Libraries\Auth\SessionManager();
	}

	public static function authService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('authService');
		return new \App\Services\Auth\AuthService(
			model(\App\Models\Admin\UtilisateurModel::class),
			new \App\Libraries\Auth\PasswordHasher(),
			service('sessionManager'),
			service('auditService'),
		);
	}

	public static function dashboardService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('dashboardService');
		return new \App\Services\Common\DashboardService(db_connect());
	}

	public static function utilisateurService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('utilisateurService');
		return new \App\Services\Admin\UtilisateurService(
			model(\App\Models\Admin\UtilisateurModel::class),
			model(\App\Models\Admin\EmployeModel::class),
			model(\App\Models\Personnel\SalarieModel::class),
			new \App\Libraries\Auth\PasswordHasher(),
			service('auditService'),
		);
	}

	public static function auditService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('auditService');
		return new \App\Libraries\Common\AuditService(
			model(\App\Models\Common\JournalAuditModel::class),
			model(\App\Models\Common\ActionModel::class),
		);
	}

	public static function workflowService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('workflowService');
		return new \App\Libraries\Common\WorkflowService(db_connect());
	}

	public static function candidatureService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('candidatureService');
		return new \App\Services\Recrutement\CandidatureService(db_connect());
	}

	public static function entretienService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('entretienService');
		return new \App\Services\Selection\EntretienService(
			model(\App\Models\Selection\EntretienModel::class),
			model(\App\Models\Selection\StatusEntretienModel::class),
			model(\App\Models\Selection\HistoriqueEntretienModel::class),
			service('candidatureService'),
			service('auditService'),
			service('workflowService'),
		);
	}

	public static function evaluationService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('evaluationService');
		return new \App\Services\Selection\EvaluationService(
			model(\App\Models\Selection\EvaluationCandidatModel::class),
			service('candidatureService'),
			service('auditService'),
		);
	}

	public static function selectionService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('selectionService');
		return new \App\Services\Selection\SelectionService(
			service('evaluationService'),
			service('candidatureService'),
			model(\App\Models\Personnel\ReadModels\CandidatureReadModel::class),
			service('auditService'),
		);
	}

	public static function propositionEmbaucheService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('propositionEmbaucheService');
		return new \App\Services\Personnel\PropositionEmbaucheService(
			model(\App\Models\Personnel\PropositionEmbaucheModel::class),
			model(\App\Models\Personnel\RevendicationModel::class),
			model(\App\Models\Personnel\ReadModels\StatusPropositionReadModel::class),
			model(\App\Models\Personnel\ReadModels\CandidatureReadModel::class),
			service('grilleService'),
			service('auditService'),
			service('workflowService'),
		);
	}

	public static function revendicationService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('revendicationService');
		return new \App\Services\Personnel\RevendicationService(
			model(\App\Models\Personnel\RevendicationModel::class),
			model(\App\Models\Personnel\StatusRevendicationModel::class),
			model(\App\Models\Personnel\NatureRevendicationModel::class),
			model(\App\Models\Personnel\ReadModels\StatusPropositionReadModel::class),
			service('propositionEmbaucheService'),
			service('auditService'),
			service('workflowService'),
		);
	}

	public static function referentielService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('referentielService');
		return new \App\Services\Common\ReferentielService(
			model(\App\Models\Common\ReferentielModel::class),
		);
	}

    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */


	// fichier partager PR dediee
	public static function salarieService(bool $getShared = true)
	{
		if ($getShared) {
			return static::getSharedInstance('salarieService');
		}

		return new \App\Services\Personnel\SalarieService(         
			model(\App\Models\Personnel\SalarieModel::class),
			model(\App\Models\Personnel\StatusSalarieModel::class),
			model(\App\Models\Personnel\SalarieHistoriqueModel::class),
			model(\App\Models\Personnel\AffectationModel::class),
			new \App\Services\Personnel\MatriculeService(
				model(\App\Models\Personnel\SalarieModel::class)
			),
			model(\App\Models\Personnel\ReadModels\PropositionEmbaucheReadModel::class),
			model(\App\Models\Personnel\ReadModels\StatusPropositionReadModel::class),
			model(\App\Models\Personnel\ReadModels\CandidatureReadModel::class),
			model(\App\Models\Personnel\ReadModels\CandidatReadModel::class),
			service('auditService'),
			service('workflowService'),
		);
	}
	
	public static function grilleService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('grilleService');
		return new \App\Services\Personnel\GrilleService(
			model(\App\Models\Personnel\GrilleModel::class),
		);
	}

	public static function periodeEssaiService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('periodeEssaiService');
		return new \App\Services\Personnel\PeriodeEssaiService(
			model(\App\Models\Personnel\PeriodeEssaiModel::class),
		);
	}

	public static function contratService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('contratService');

		return new \App\Services\Personnel\ContratService(
			model(\App\Models\Personnel\ContratModel::class),
			model(\App\Models\Personnel\StatusContratModel::class),
			model(\App\Models\Personnel\ContratHistoriqueModel::class),
			model(\App\Models\Personnel\SalarieModel::class),
			model(\App\Models\Personnel\StatusSalarieModel::class),
			model(\App\Models\Personnel\AffectationModel::class),
			service('periodeEssaiService'),
			service('grilleService'),
			service('auditService'),
			service('workflowService'),
		);
	}
	
	public static function onboardingService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('onboardingService');

		return new \App\Services\Personnel\OnboardingService(
			model(\App\Models\Personnel\OnboardingModel::class),
			model(\App\Models\Personnel\StatusOnboardingModel::class),
			model(\App\Models\Personnel\OnboardingHistoriqueModel::class),
			model(\App\Models\Personnel\TacheModel::class),
			model(\App\Models\Personnel\StatusTacheModel::class),
			model(\App\Models\Personnel\TacheHistoriqueModel::class),
			model(\App\Models\Personnel\SalarieModel::class),
			service('auditService'),
			service('workflowService'),
		);
	}
	
	public static function stockageService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('stockageService');

		return new \App\Libraries\Personnel\StockageService();
	}

	public static function documentPolicy(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('documentPolicy');

		return new \App\Libraries\Personnel\DocumentPolicy(
			model(\App\Models\Admin\EmployeModel::class),
		);
	}

	public static function documentService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('documentService');

		return new \App\Services\Personnel\DocumentService(
			model(\App\Models\Personnel\DocumentModel::class),
			model(\App\Models\Personnel\TypeDocumentModel::class),
			model(\App\Models\Personnel\SalarieModel::class),
			service('auditService'),
			service('stockageService'),
			service('documentPolicy'),
		);
	}

	public static function dossierService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('dossierService');

		return new \App\Services\Personnel\DossierService(
			model(\App\Models\Personnel\SalarieModel::class),
			model(\App\Models\Personnel\AffectationModel::class),
			model(\App\Models\Personnel\ContratModel::class),
			model(\App\Models\Personnel\SalarieHistoriqueModel::class),
			model(\App\Models\Personnel\OnboardingModel::class),
			service('documentService'),
			service('documentPolicy'),
			service('auditService'),
		);
	}

	public static function grilleSalarialeService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('grilleSalarialeService');

		return new \App\Services\Finance\GrilleSalarialeService(
			model(\App\Models\Finance\GrilleSalarialeModel::class),
			model(\App\Models\Personnel\PosteModel::class),
			model(\App\Models\Personnel\RangModel::class),
			service('auditService'),
		);
	}

}
