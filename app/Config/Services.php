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

	public static function demandeCongeService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('demandeCongeService');

		return new \App\Services\Personnel\DemandeCongeService(
			model(\App\Models\Personnel\DemandeCongeModel::class),
			service('auditService'),
		);
	}

	// -- Bloc 7 : Congés et absences (Services/Conges) -----------------------

	public static function auditCongeService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('auditCongeService');

		return new \App\Services\Conges\AuditService(
			service('auditService'),
		);
	}

	public static function calendrierService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('calendrierService');

		return new \App\Services\Conges\CalendrierService(
			model(\App\Models\Conges\JourFerieModel::class),
		);
	}

	public static function controleDemandeService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('controleDemandeService');

		return new \App\Services\Conges\ControleDemandeService(
			model(\App\Models\Personnel\ContratModel::class),
			model(\App\Models\Personnel\SalarieModel::class),
			model(\App\Models\Conges\TypeCongeModel::class),
			model(\App\Models\Conges\DroitCongeModel::class),
			model(\App\Models\Conges\DemandeCongeModel::class),
			model(\App\Models\Conges\AbsenceModel::class),
			service('calendrierService'),
		);
	}

	public static function soldeCongeService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('soldeCongeService');

		return new \App\Services\Conges\SoldeService(
			model(\App\Models\Conges\SoldeCongeModel::class),
			model(\App\Models\Conges\MouvementSoldeModel::class),
			model(\App\Models\Conges\TypeCongeModel::class),
			model(\App\Models\Conges\DroitCongeModel::class),
			model(\App\Models\Conges\DemandeCongeModel::class),
			model(\App\Models\Personnel\SalarieModel::class),
			service('calendrierService'),
			service('auditCongeService'),
		);
	}

	public static function absenceCongeService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('absenceCongeService');

		return new \App\Services\Conges\AbsenceService(
			model(\App\Models\Conges\AbsenceModel::class),
			model(\App\Models\Conges\TypeAbsenceModel::class),
			model(\App\Models\Conges\TypeCongeModel::class),
			model(\App\Models\Conges\DemandeCongeModel::class),
			service('calendrierService'),
		);
	}

	public static function notificationCongeService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('notificationCongeService');

		return new \App\Services\Conges\NotificationService();
	}

	public static function congeWorkflowService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('congeWorkflowService');

		return new \App\Services\Conges\CongeService(
			model(\App\Models\Conges\DemandeCongeModel::class),
			model(\App\Models\Conges\TypeCongeModel::class),
			model(\App\Models\Conges\StatutDemandeCongeModel::class),
			model(\App\Models\Personnel\SalarieModel::class),
			model(\App\Models\Personnel\ContratModel::class),
			model(\App\Models\Conges\DelegationValidationModel::class),
			service('calendrierService'),
			service('controleDemandeService'),
			service('soldeCongeService'),
			service('absenceCongeService'),
			service('auditCongeService'),
			service('notificationCongeService'),
		);
	}

	public static function paieCongeService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('paieCongeService');

		return new \App\Services\Conges\PaieCongeService(
			service('calendrierService'),
		);
	}

	public static function justificatifCongeService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('justificatifCongeService');

		return new \App\Services\Conges\JustificatifService(
			model(\App\Models\Conges\JustificatifAbsenceModel::class),
			service('auditCongeService'),
		);
	}

	// -- Bloc Paie : bulletins de paie --------------------------------------

	public static function cotisationService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('cotisationService');

		return new \App\Services\Paie\CotisationService(
			model(\App\Models\Paie\TauxCotisationModel::class),
		);
	}

	public static function elementPaieService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('elementPaieService');

		return new \App\Services\Paie\ElementPaieService(
			model(\App\Models\Paie\ElementPaieModel::class),
			model(\App\Models\Personnel\SalarieModel::class),
			service('auditService'),
		);
	}

	public static function bulletinService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('bulletinService');

		return new \App\Services\Paie\BulletinService(
			model(\App\Models\Paie\BulletinPaieModel::class),
			model(\App\Models\Paie\ElementPaieModel::class),
			model(\App\Models\Personnel\ContratModel::class),
			service('cotisationService'),
			service('calendrierService'),
		);
	}

	public static function passwordHasher(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('passwordHasher');

		return new \App\Libraries\Auth\PasswordHasher();
	}

	public static function sessionManager(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('sessionManager');

		return new \App\Libraries\Auth\SessionManager();
	}

	public static function auditService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('auditService');

		return new \App\Libraries\Common\AuditService(
			model(\App\Models\Common\JournalAuditModel::class),
			model(\App\Models\Common\ActionModel::class),
		);
	}

	public static function authService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('authService');

		return new \App\Services\Auth\AuthService(
			model(\App\Models\Admin\UtilisateurModel::class),
			service('passwordHasher'),
			service('sessionManager'),
			service('auditService'),
		);
	}

	public static function utilisateurService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('utilisateurService');

		return new \App\Services\Admin\UtilisateurService(
			model(\App\Models\Admin\UtilisateurModel::class),
			model(\App\Models\Admin\EmployeModel::class),
			service('passwordHasher'),
			service('auditService'),
		);
	}

	public static function referentielService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('referentielService');

		return new \App\Services\Common\ReferentielService(
			model(\App\Models\Common\ReferentielModel::class),
		);
	}

	public static function dashboardService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('dashboardService');

		return new \App\Services\Common\DashboardService(
			\Config\Database::connect(),
		);
	}

}
