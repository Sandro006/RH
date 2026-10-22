<?php


// salaire
$routes->group('api/salaries', ['namespace' => 'App\Controllers\Personnel'], static function ($routes) {
    $routes->get('/',           'SalarieController::index');
    $routes->post('/',          'SalarieController::createDepuisCandidature');
    $routes->get('(:num)',      'SalarieController::show/$1');
    $routes->patch('(:num)',    'SalarieController::update/$1');
    $routes->post('(:num)/affectations', 'SalarieController::affecter/$1');
    $routes->get('(:num)/affectations',  'SalarieController::listerAffectations/$1');
    $routes->get('(:num)/historique',    'SalarieController::historique/$1');
    $routes->post('(:num)/statut',       'SalarieController::changerStatut/$1');
});

// contrat
$routes->group('api/contrats', ['namespace' => 'App\Controllers\Personnel'], static function ($routes) {
    $routes->get('/',           'ContratController::index');
    $routes->post('/',          'ContratController::create');
    $routes->get('(:num)',      'ContratController::show/$1');
    $routes->patch('(:num)',    'ContratController::update/$1');
    $routes->post('(:num)/soumettre',   'ContratController::soumettre/$1');
    $routes->post('(:num)/signer',      'ContratController::signer/$1');
    $routes->post('(:num)/activer',     'ContratController::activer/$1');
    $routes->post('(:num)/renouveler',  'ContratController::renouveler/$1');
    $routes->post('(:num)/avenant',     'ContratController::avenant/$1');
    $routes->post('(:num)/mettre-fin',  'ContratController::mettreFin/$1');
    $routes->get('(:num)/historique',   'ContratController::historique/$1');
});

// grille salaire
$routes->group('api/referentiels', ['namespace' => 'App\Controllers\Personnel'], static function ($routes) {
    $routes->get('grille/(:num)', 'ContratController::grille/$1');
});

// onboardings
$routes->group('api/onboardings', ['namespace' => 'App\Controllers\Personnel'], static function ($routes) {
    $routes->get('/',           'OnboardingController::index');
    $routes->post('/',          'OnboardingController::demarrer');
    $routes->get('(:num)',      'OnboardingController::show/$1');
    $routes->post('(:num)/taches', 'OnboardingController::creerTache/$1');
    $routes->post('taches/(:num)/demarrer', 'OnboardingController::demarrerTache/$1');
    $routes->post('taches/(:num)/realiser', 'OnboardingController::realiserTache/$1');
    $routes->post('taches/(:num)/bloquer',  'OnboardingController::bloquerTache/$1');
    $routes->post('taches/(:num)/annuler',  'OnboardingController::annulerTache/$1');
    $routes->post('(:num)/cloturer',  'OnboardingController::cloturer/$1');
    $routes->post('(:num)/annuler',   'OnboardingController::annuler/$1');
    $routes->get('(:num)/historique', 'OnboardingController::historique/$1');
    $routes->get('(:num)/taches',     'OnboardingController::listerTaches/$1');
	$routes->get('(:num)/progression', 'OnboardingController::progression/$1');
});

// document salaire
$routes->group('api/salaries/(:num)/documents', ['namespace' => 'App\Controllers\Personnel'], static function ($routes) {
    $routes->get('/',       'DocumentController::index/$1');
    $routes->post('/',      'DocumentController::ajouter/$1');
});

// referentiels
$routes->group('api/referentiels', ['namespace' => 'App\Controllers\Personnel'], static function ($routes) {
    $routes->get('types-document', 'DocumentController::listerTypes');
});

// supprimer
$routes->group('api/documents', ['namespace' => 'App\Controllers\Personnel'], static function ($routes) {
    $routes->delete('(:num)', 'DocumentController::supprimer/$1');
});