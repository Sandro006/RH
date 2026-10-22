<?php

$routes->get('/', 'Home::index');
$routes->get('web/(.*)', 'WebController::asset/$1');
$routes->get('web/(:any)', 'WebController::asset/$1');
$routes->get('api/dashboard', 'DashboardController::index', ['filter' => 'auth']);
$routes->group('api/auth', ['namespace' => 'App\\Controllers\\Auth'], static function ($routes) {
    $routes->post('login', 'AuthController::login');
    $routes->post('logout', 'AuthController::logout', ['filter' => 'auth']);
    $routes->get('session', 'AuthController::session', ['filter' => 'auth']);
    $routes->patch('mot-de-passe', 'AuthController::changerMotDePasse', ['filter' => 'auth']);
});
$routes->group('api/utilisateurs', ['namespace' => 'App\\Controllers\\Admin', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'UtilisateurController::index');
    $routes->post('/', 'UtilisateurController::create');
    $routes->patch('(:num)', 'UtilisateurController::update/$1');
    $routes->post('(:num)/activer', 'UtilisateurController::activer/$1');
    $routes->post('(:num)/desactiver', 'UtilisateurController::desactiver/$1');
    $routes->post('(:num)/reinitialiser-mot-de-passe', 'UtilisateurController::reinitialiser/$1');
});

$routes->group('api', ['namespace' => 'App\\Controllers\\Recrutement', 'filter' => 'auth'], static function ($routes) {
    $routes->get('demandes', 'RecrutementController::demandes');
    $routes->post('demandes', 'RecrutementController::creerDemande');
    $routes->get('demandes/(:num)', 'RecrutementController::demande/$1');
    $routes->patch('demandes/(:num)', 'RecrutementController::modifierDemande/$1');
    $routes->get('demandes/(:num)/coherence', 'RecrutementController::demandeCoherence/$1');
    $routes->get('demandes/(:num)/validations', 'RecrutementController::demandeValidations/$1');
    $routes->get('demandes/(:num)/historique', 'RecrutementController::demandeHistorique/$1');
    $routes->post('demandes/(:num)/(:segment)', 'RecrutementController::actionDemande/$1/$2');
    $routes->get('offres', 'RecrutementController::offres');
    $routes->get('offres/(:num)', 'RecrutementController::offre/$1');
    $routes->patch('offres/(:num)', 'RecrutementController::modifierOffre/$1');
    $routes->get('offres/(:num)/candidatures', 'RecrutementController::offreCandidatures/$1');
    $routes->get('offres/(:num)/historique', 'RecrutementController::offreHistorique/$1');
    $routes->post('offres/(:num)/(:segment)', 'RecrutementController::actionOffre/$1/$2');
    $routes->post('demandes/(:num)/offre', 'RecrutementController::offreDepuisDemande/$1');
    $routes->get('candidatures', 'RecrutementController::candidatures');
    $routes->get('candidatures/(:num)', 'RecrutementController::candidature/$1');
    $routes->get('candidatures/(:num)/historique', 'RecrutementController::candidatureHistorique/$1');
    $routes->post('offres/(:num)/candidatures', 'RecrutementController::postuler/$1');
    $routes->post('candidatures/(:num)/(:segment)', 'RecrutementController::actionCandidature/$1/$2');
});
$routes->get('api/public/offres', 'Recrutement\\RecrutementController::offresOuvertes');
$routes->get('api/public/offres/(:num)', 'Recrutement\\RecrutementController::offrePublique/$1');

// salaire
$routes->group('api/salaries', ['namespace' => 'App\Controllers\Personnel', 'filter' => 'auth'], static function ($routes) {
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
$routes->group('api/contrats', ['namespace' => 'App\Controllers\Personnel', 'filter' => 'auth'], static function ($routes) {
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
$routes->group('api/onboardings', ['namespace' => 'App\Controllers\Personnel', 'filter' => 'auth'], static function ($routes) {
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

//entretiens
$routes->get('entretiens', 'Selection\EntretienController::page');

$routes->group('api', static function ($routes) {
    // Liste et filtres
    $routes->get('entretiens', 'Selection\EntretienController::index');
    $routes->post('entretiens', 'Selection\EntretienController::planifier');
    // Consulter les détails
    $routes->get('entretiens/(:num)', 'Selection\EntretienController::show/$1');
    $routes->post('entretiens/(:num)/confirmer', 'Selection\EntretienController::confirmer/$1');
    $routes->post('entretiens/(:num)/reporter', 'Selection\EntretienController::reporter/$1');
    $routes->post('entretiens/(:num)/realiser', 'Selection\EntretienController::realiser/$1');
    $routes->post('entretiens/(:num)/annuler', 'Selection\EntretienController::annuler/$1');
    $routes->get('entretiens/(:num)/historique', 'Selection\EntretienController::historique/$1');
});
