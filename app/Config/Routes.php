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