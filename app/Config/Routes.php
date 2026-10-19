<?php


// salaire
$routes->group('api/salaries', ['namespace' => 'App\Controllers\Personnel'], static function ($routes) {
    $routes->get('/', 'SalarieController::index');
    $routes->post('/', 'SalarieController::createDepuisCandidature');
    $routes->get('(:num)', 'SalarieController::show/$1');
    $routes->patch('(:num)', 'SalarieController::update/$1');
    $routes->post('(:num)/affectations', 'SalarieController::affecter/$1');
    $routes->get('(:num)/affectations', 'SalarieController::listerAffectations/$1');
    $routes->get('(:num)/historique', 'SalarieController::historique/$1');
    $routes->post('(:num)/statut', 'SalarieController::changerStatut/$1');
});

$routes->group('recrutement', ['namespace' => 'App\Controllers\Recrutement'], static function ($routes) {
    foreach (['demandes','demande-form','demande','demandes-a-traiter','offres','offre-form','offre','candidatures','candidature','vitrine-offres','candidature-form','suivi-candidature'] as $page)
        $routes->get($page, 'RecrutementController::page/' . $page);
});

// contrat
$routes->group('api/contrats', ['namespace' => 'App\Controllers\Personnel'], static function ($routes) {
    $routes->get('/', 'ContratController::index');
    $routes->post('/', 'ContratController::create');
    $routes->get('(:num)', 'ContratController::show/$1');
    $routes->patch('(:num)', 'ContratController::update/$1');
    $routes->post('(:num)/soumettre', 'ContratController::soumettre/$1');
    $routes->post('(:num)/signer', 'ContratController::signer/$1');
    $routes->post('(:num)/activer', 'ContratController::activer/$1');
    $routes->post('(:num)/renouveler', 'ContratController::renouveler/$1');
    $routes->post('(:num)/avenant', 'ContratController::avenant/$1');
    $routes->post('(:num)/mettre-fin', 'ContratController::mettreFin/$1');
    $routes->get('(:num)/historique', 'ContratController::historique/$1');
});

// grille salaire
$routes->group('api/referentiels', ['namespace' => 'App\Controllers\Personnel'], static function ($routes) {
    $routes->get('grille/(:num)', 'ContratController::grille/$1');
});

$routes->group('api', ['namespace' => 'App\Controllers\Recrutement'], static function ($routes) {
    $routes->get('demandes', 'RecrutementController::demandes');
    $routes->post('demandes', 'RecrutementController::creerDemande');
    $routes->get('demandes/(:num)', 'RecrutementController::demande/$1');
    $routes->patch('demandes/(:num)', 'RecrutementController::modifierDemande/$1');
    $routes->get('demandes/(:num)/coherence', 'RecrutementController::demandeCoherence/$1');
    $routes->get('demandes/(:num)/validations', 'RecrutementController::demandeValidations/$1');
    $routes->get('demandes/(:num)/historique', 'RecrutementController::demandeHistorique/$1');
    foreach (['soumettre', 'controle-rh', 'validation-budget', 'validation-hierarchie', 'consultation', 'cloture-consultation', 'feu-vert', 'annuler'] as $a)
        $routes->post('demandes/(:num)/' . $a, 'RecrutementController::actionDemande/$1/' . $a);
    $routes->get('offres', 'RecrutementController::offres');
    $routes->get('offres/(:num)', 'RecrutementController::offre/$1');
    $routes->get('offres/(:num)/candidatures', 'RecrutementController::offreCandidatures/$1');
    $routes->get('offres/(:num)/historique', 'RecrutementController::offreHistorique/$1');
    $routes->post('demandes/(:num)/offre', 'RecrutementController::offreDepuisDemande/$1');
    $routes->patch('offres/(:num)', 'RecrutementController::modifierOffre/$1');
    foreach (['publier', 'suspendre', 'reprendre', 'clore', 'annuler'] as $a)
        $routes->post('offres/(:num)/' . $a, 'RecrutementController::actionOffre/$1/' . $a);
    $routes->get('candidatures', 'RecrutementController::candidatures');
    $routes->get('candidatures/(:num)', 'RecrutementController::candidature/$1');
    $routes->get('candidatures/(:num)/historique', 'RecrutementController::candidatureHistorique/$1');
    $routes->post('offres/(:num)/candidatures', 'RecrutementController::postuler/$1');
    foreach (['preselection', 'selectionner', 'planifier-entretien', 'evaluer', 'retenir', 'refuser', 'retirer'] as $a)
        $routes->post('candidatures/(:num)/' . $a, 'RecrutementController::actionCandidature/$1/' . $a);
    $routes->get('salaries/(:num)/eligibilite', 'RecrutementController::eligibilite/$1');
});
$routes->group('api/public', ['namespace' => 'App\Controllers\Recrutement'], static function ($routes) {
    $routes->get('offres', 'RecrutementController::offresOuvertes');
    $routes->get('offres/(:num)', 'RecrutementController::offrePublique/$1'); });
