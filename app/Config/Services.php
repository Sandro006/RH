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
		);
	}
	
	public static function grilleService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('grilleService');
		return new \App\Services\Personnel\GrilleService(
			model(\App\Models\Personnel\ContratModel::class),
		);
	}

	public static function periodeEssaiService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('periodeEssaiService');
		return new \App\Services\Personnel\PeriodeEssaiService(
			model(\App\Models\Personnel\ContratModel::class),
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
	
	public static function documentService(bool $getShared = true)
	{
		if ($getShared) return static::getSharedInstance('documentService');

		return new \App\Services\Personnel\DocumentService(
			model(\App\Models\Personnel\DocumentModel::class),
			model(\App\Models\Personnel\TypeDocumentModel::class),
			model(\App\Models\Personnel\SalarieModel::class),
			service('auditService'),
		);
	}

}
