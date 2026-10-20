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

// Piste A - authentification
$routes->group('api/auth', ['namespace' => 'App\Controllers\Auth'], static function ($routes) {
    $routes->post('login', 'AuthController::login');
    $routes->post('logout', 'AuthController::logout', ['filter' => 'auth']);
    $routes->get('session', 'AuthController::session', ['filter' => 'auth']);
    $routes->patch('mot-de-passe', 'AuthController::changerMotDePasse', ['filter' => 'auth']);
});

// Piste A - administration des utilisateurs
$routes->group('api/utilisateurs', ['namespace' => 'App\Controllers\Admin', 'filter' => ['auth', 'role:admin']], static function ($routes) {
    $routes->get('/', 'UtilisateurController::index');
    $routes->post('/', 'UtilisateurController::create');
    $routes->patch('(:num)', 'UtilisateurController::update/$1');
    $routes->post('(:num)/activer', 'UtilisateurController::activer/$1');
    $routes->post('(:num)/desactiver', 'UtilisateurController::desactiver/$1');
    $routes->post('(:num)/reinitialiser-mot-de-passe', 'UtilisateurController::reinitialiser/$1');
});

// Piste A - referentiels en lecture
$routes->group('api/referentiels', ['namespace' => 'App\Controllers\Admin', 'filter' => 'auth'], static function ($routes) {
    $routes->get('departements', 'ReferentielController::departements');
    $routes->get('services', 'ReferentielController::services');
    $routes->get('postes', 'ReferentielController::postes');
    $routes->get('postes/(:num)/grilles', 'ReferentielController::grillesPoste/$1');
    $routes->get('grilles/(:num)', 'ReferentielController::grilles/$1');
    $routes->get('rangs', 'ReferentielController::rangs');
    $routes->get('types-contrat', 'ReferentielController::typesContrat');
    $routes->get('types-entretien', 'ReferentielController::typesEntretien');
    $routes->get('types-document', 'ReferentielController::typesDocument');
    $routes->get('actions', 'ReferentielController::actions');
    $routes->get('natures-revendication', 'ReferentielController::naturesRevendication');
    $routes->get('statuts/(:segment)', 'ReferentielController::statuts/$1');
});

// Piste A - tableau de bord
$routes->get('api/dashboard', 'DashboardController::index', ['filter' => 'auth']);
