<?php

$routes->get('/', 'Home::index');

// pages rendues par CodeIgniter (layout + partials)
$routes->get('login', 'Pages::login');
$routes->get('candidatures', 'Recrutement\\CandidaturePubliqueController::offres');
$routes->get('candidatures/postuler/(:num)', 'Recrutement\\CandidaturePubliqueController::formulaire/$1');
$routes->post('api/public/offres/(:num)/candidatures', 'Recrutement\\CandidaturePubliqueController::deposer/$1');
$routes->get('dashboard', 'Pages::dashboard', ['filter' => 'auth']);

$routes->group('recrutement', ['filter' => 'auth'], static function ($routes) {
    $routes->get('demandes', 'Pages::afficher/recrutement/demandes');
    $routes->get('demande-form', 'Pages::afficher/recrutement/demande-form');
    $routes->get('demande', 'Pages::afficher/recrutement/demande');
    $routes->get('demande-edit', 'Pages::afficher/recrutement/demande-edit');
    $routes->get('offres', 'Pages::afficher/recrutement/offres');
    $routes->get('offre', 'Pages::afficher/recrutement/offre');
    $routes->get('offre-form', 'Pages::afficher/recrutement/offre-form');
    $routes->get('candidatures', 'Pages::afficher/recrutement/candidatures');
});

$routes->get('selection/entretiens', 'Pages::afficher/selection/entretiens', ['filter' => 'auth']);
$routes->get('selection/entretien-form', 'Pages::afficher/selection/entretien-form', ['filter' => 'auth']);
$routes->get('selection/evaluation', 'Pages::afficher/selection/evaluation', ['filter' => 'auth']);
$routes->get('selection/classement', 'Pages::afficher/selection/classement', ['filter' => 'auth']);
$routes->get('selection/propositions', 'Pages::afficher/selection/propositions', ['filter' => 'auth']);
$routes->get('selection/proposition-form', 'Pages::afficher/selection/proposition-form', ['filter' => 'auth']);
$routes->get('selection/proposition', 'Pages::afficher/selection/proposition', ['filter' => 'auth']);

$routes->group('personnel', ['filter' => 'auth'], static function ($routes) {
    $routes->get('salaries', 'Pages::afficher/personnel/salaries', ['filter' => 'role:rh,admin']);
    $routes->get('salarie', 'Pages::afficher/personnel/salarie', ['filter' => 'role:rh,admin']);
    $routes->get('salarie-form', 'Pages::afficher/personnel/salarie-form', ['filter' => 'role:rh,admin']);
    $routes->get('grille', 'Pages::afficher/personnel/grille', ['filter' => 'role:rh,admin,controle_gestion']);
    $routes->get('grille-form', 'Pages::afficher/personnel/grille-form', ['filter' => 'role:rh,admin,controle_gestion']);
    $routes->get('contrats', 'Pages::afficher/personnel/contrats', ['filter' => 'role:rh,admin']);
    $routes->get('contrat', 'Pages::afficher/personnel/contrat', ['filter' => 'role:rh,admin']);
    $routes->get('contrat-preview', 'Pages::afficher/personnel/contrat-preview', ['filter' => 'role:rh,admin']);
    $routes->get('contrat-form', 'Pages::afficher/personnel/contrat-form', ['filter' => 'role:rh,admin']);
    $routes->get('contrat-avenant-form', 'Pages::afficher/personnel/contrat-avenant-form', ['filter' => 'role:rh,admin']);
    $routes->get('affectation-form', 'Pages::afficher/personnel/affectation-form', ['filter' => 'role:rh,admin']);
    $routes->get('onboardings', 'Pages::afficher/personnel/onboardings', ['filter' => 'role:rh,admin']);
    $routes->get('onboarding', 'Pages::afficher/personnel/onboarding', ['filter' => 'role:rh,admin']);
    $routes->get('onboarding-form', 'Pages::afficher/personnel/onboarding-form', ['filter' => 'role:rh,admin']);
    $routes->get('onboarding-tache-form', 'Pages::afficher/personnel/onboarding-tache-form', ['filter' => 'role:rh,admin']);
    $routes->get('document-form', 'Pages::afficher/personnel/document-form', ['filter' => 'role:rh,admin']);
    $routes->get('mes-conges', 'Pages::afficher/personnel/mes-conges');
    $routes->get('conge-form', 'Pages::afficher/personnel/conge-form');
    $routes->get('conges-a-valider', 'Pages::afficher/personnel/conges-a-valider', ['filter' => 'role:manager,rh,admin,direction']);
    $routes->get('conges-planning', 'Pages::afficher/personnel/conges-planning');
    $routes->get('conges-soldes', 'Pages::afficher/personnel/conges-soldes', ['filter' => 'role:rh,admin']);
    $routes->get('conges-absences', 'Pages::afficher/personnel/conges-absences', ['filter' => 'role:rh,admin']);
    $routes->get('mes-documents', 'Pages::afficher/personnel/mes-documents');
    $routes->get('mes-taches', 'Pages::afficher/personnel/mes-taches');
});

$routes->get('profil/mot-de-passe', 'Pages::afficher/profil/mot-de-passe', ['filter' => 'auth']);

// Département Informatique
$routes->get('departement/informatique', 'Pages::afficher/departement/informatique', ['filter' => 'auth']);
$routes->get('departement/informatique/creer-utilisateur', 'Pages::creerUtilisateur', ['filter' => 'accesInformatique']);
$routes->get('departement/informatique/reinitialiser-mdp', 'Pages::reinitialiserMdp', ['filter' => 'accesInformatique']);

$routes->get('securite/audit', 'Pages::afficher/securite/audit', ['filter' => 'auth']);
$routes->get('securite/acces', 'Pages::afficher/securite/acces', ['filter' => 'auth']);

// ressources statiques servies depuis public/assets
$routes->get('api/dashboard', 'DashboardController::index', ['filter' => 'auth']);
$routes->group('api/auth', ['namespace' => 'App\\Controllers\\Auth'], static function ($routes) {
    $routes->post('login', 'AuthController::login');
    $routes->post('logout', 'AuthController::logout', ['filter' => 'auth']);
    $routes->get('session', 'AuthController::session', ['filter' => 'auth']);
    $routes->patch('mot-de-passe', 'AuthController::changerMotDePasse', ['filter' => 'auth']);
});
$routes->group('api/utilisateurs', ['namespace' => 'App\\Controllers\\Admin', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'UtilisateurController::index');
    $routes->post('/', 'UtilisateurController::create', ['filter' => 'accesInformatique']);
    $routes->patch('(:num)', 'UtilisateurController::update/$1');
    $routes->post('(:num)/activer', 'UtilisateurController::activer/$1');
    $routes->post('(:num)/desactiver', 'UtilisateurController::desactiver/$1');
    $routes->post('(:num)/reinitialiser-mot-de-passe', 'UtilisateurController::reinitialiser/$1');
});

// sélection : entretiens, évaluations, décision finale
$routes->group('api', ['namespace' => 'App\\Controllers\\Selection', 'filter' => 'auth'], static function ($routes) {
    $routes->get('entretiens', 'EntretienController::index');
    $routes->post('entretiens', 'EntretienController::planifier');
    $routes->get('entretiens/(:num)', 'EntretienController::show/$1');
    $routes->post('entretiens/(:num)/confirmer', 'EntretienController::confirmer/$1');
    $routes->post('entretiens/(:num)/reporter', 'EntretienController::reporter/$1');
    $routes->post('entretiens/(:num)/realiser', 'EntretienController::realiser/$1');
    $routes->post('entretiens/(:num)/annuler', 'EntretienController::annuler/$1');
    $routes->get('entretiens/(:num)/historique', 'EntretienController::historique/$1');
    $routes->get('candidatures/(:num)/entretiens', 'EntretienController::listerParCandidature/$1');
    $routes->post('offres/(:num)/entretiens', 'EntretienController::planifierPourOffre/$1');

    $routes->get('evaluations/(:num)', 'EvaluationController::show/$1');
    $routes->get('candidatures/(:num)/evaluations', 'EvaluationController::listerParCandidature/$1');
    $routes->post('candidatures/(:num)/evaluations', 'EvaluationController::evaluer/$1');
    $routes->get('candidatures/(:num)/evaluations/synthese', 'EvaluationController::synthese/$1');
    $routes->get('offres/(:num)/evaluations', 'EvaluationController::listerParOffre/$1');

    $routes->get('offres/(:num)/classement', 'SelectionController::classement/$1');
    $routes->get('offres/(:num)/candidats-eligibles', 'SelectionController::eligibles/$1');
    $routes->post('candidatures/(:num)/retenir', 'SelectionController::retenir/$1');
    $routes->post('candidatures/(:num)/refuser', 'SelectionController::refuser/$1');
    $routes->post('candidatures/(:num)/decision', 'SelectionController::decider/$1');
});

// propositions d'embauche et négociation
$routes->group('api', ['namespace' => 'App\\Controllers\\Personnel', 'filter' => 'auth'], static function ($routes) {
    $routes->get('propositions', 'PropositionEmbaucheController::index');
    $routes->post('propositions', 'PropositionEmbaucheController::create');
    $routes->get('propositions/(:num)', 'PropositionEmbaucheController::show/$1');
    $routes->post('propositions/(:num)/envoyer', 'PropositionEmbaucheController::envoyer/$1');
    $routes->post('propositions/(:num)/repondre', 'PropositionEmbaucheController::repondre/$1');
    $routes->post('propositions/(:num)/contre-proposer', 'PropositionEmbaucheController::contreProposer/$1');
    $routes->post('propositions/(:num)/expirer', 'PropositionEmbaucheController::expirer/$1');
    $routes->get('propositions/(:num)/historique', 'PropositionEmbaucheController::historique/$1');
    $routes->get('propositions/(:num)/revendications', 'PropositionEmbaucheController::listerRevendications/$1');
    $routes->post('propositions/(:num)/revendications', 'PropositionEmbaucheController::soumettreRevendication/$1');
    $routes->get('candidatures/(:num)/proposition', 'PropositionEmbaucheController::parCandidature/$1');
    $routes->post('revendications/(:num)/repondre', 'PropositionEmbaucheController::repondreRevendication/$1');
    $routes->post('revendications/(:num)/retirer', 'PropositionEmbaucheController::retirerRevendication/$1');
    $routes->get('revendications/(:num)/historique', 'PropositionEmbaucheController::historiqueRevendication/$1');
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
    $routes->get('candidatures/(:num)/transitions', 'RecrutementController::candidatureTransitions/$1');
    $routes->post('offres/(:num)/candidatures', 'RecrutementController::postuler/$1');
    $routes->post('candidatures/(:num)/(:segment)', 'RecrutementController::actionCandidature/$1/$2');
});
$routes->get('api/public/offres', 'Recrutement\\RecrutementController::offresOuvertes');
$routes->get('api/public/offres/(:num)', 'Recrutement\\RecrutementController::offrePublique/$1');

// salaire
$routes->group('api/salaries', ['namespace' => 'App\Controllers\Personnel', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/',           'SalarieController::index', ['filter' => 'accesInformatique']);
    $routes->post('/',          'SalarieController::createDepuisCandidature', ['filter' => 'role:rh,admin']);
    $routes->get('(:num)',      'SalarieController::show/$1', ['filter' => 'role:rh,admin']);
    $routes->patch('(:num)',    'SalarieController::update/$1', ['filter' => 'role:rh,admin']);
    $routes->post('(:num)/affectations', 'SalarieController::affecter/$1', ['filter' => 'role:rh,admin']);
    $routes->get('(:num)/affectations',  'SalarieController::listerAffectations/$1', ['filter' => 'role:rh,admin']);
    $routes->get('(:num)/historique',    'SalarieController::historique/$1', ['filter' => 'role:rh,admin']);
    $routes->post('(:num)/statut',       'SalarieController::changerStatut/$1', ['filter' => 'role:rh,admin']);
    $routes->get('(:num)/dossier/alertes', 'DossierController::alertes/$1', ['filter' => 'role:rh,admin']);
    $routes->get('(:num)/dossier',         'DossierController::show/$1', ['filter' => 'role:rh,admin']);
    $routes->get('(:num)/carriere',        'DossierController::carriere/$1', ['filter' => 'role:rh,admin']);
});

// contrat
$routes->group('api/contrats', ['namespace' => 'App\Controllers\Personnel', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/',           'ContratController::index', ['filter' => 'role:rh,admin']);
    $routes->post('/',          'ContratController::create', ['filter' => 'role:rh,admin']);
    $routes->get('(:num)',      'ContratController::show/$1', ['filter' => 'role:rh,admin']);
    $routes->patch('(:num)',    'ContratController::update/$1', ['filter' => 'role:rh,admin']);
    $routes->post('(:num)/soumettre',   'ContratController::soumettre/$1', ['filter' => 'role:rh,admin']);
    $routes->post('(:num)/signer',      'ContratController::signer/$1', ['filter' => 'role:rh,admin']);
    $routes->post('(:num)/activer',     'ContratController::activer/$1', ['filter' => 'role:rh,admin']);
    $routes->post('(:num)/renouveler',  'ContratController::renouveler/$1', ['filter' => 'role:rh,admin']);
    $routes->post('(:num)/avenant',     'ContratController::avenant/$1', ['filter' => 'role:rh,admin']);
    $routes->post('(:num)/mettre-fin',  'ContratController::mettreFin/$1', ['filter' => 'role:rh,admin']);
    $routes->get('(:num)/historique',   'ContratController::historique/$1', ['filter' => 'role:rh,admin']);
});

// grille salaire
$routes->group('api/referentiels', ['namespace' => 'App\Controllers\Personnel'], static function ($routes) {
    $routes->get('grille/(:num)', 'ContratController::grille/$1');
});

// onboardings
$routes->group('api/onboardings', ['namespace' => 'App\Controllers\Personnel', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/',           'OnboardingController::index', ['filter' => 'role:rh,admin']);
    $routes->post('/',          'OnboardingController::demarrer', ['filter' => 'role:rh,admin']);
    $routes->get('(:num)',      'OnboardingController::show/$1', ['filter' => 'role:rh,admin']);
    $routes->patch('(:num)',    'OnboardingController::update/$1', ['filter' => 'role:rh,admin']);
    $routes->post('(:num)/taches', 'OnboardingController::creerTache/$1', ['filter' => 'role:rh,admin']);
    $routes->post('taches/(:num)/demarrer', 'OnboardingController::demarrerTache/$1', ['filter' => 'role:rh,admin']);
    $routes->post('taches/(:num)/realiser', 'OnboardingController::realiserTache/$1', ['filter' => 'role:rh,admin']);
    $routes->post('taches/(:num)/bloquer',  'OnboardingController::bloquerTache/$1', ['filter' => 'role:rh,admin']);
    $routes->post('taches/(:num)/annuler',  'OnboardingController::annulerTache/$1', ['filter' => 'role:rh,admin']);
    $routes->get('taches/(:num)',  'OnboardingController::showTache/$1', ['filter' => 'role:rh,admin']);
    $routes->patch('taches/(:num)',  'OnboardingController::updateTache/$1', ['filter' => 'role:rh,admin']);
    $routes->post('(:num)/cloturer',  'OnboardingController::cloturer/$1', ['filter' => 'role:rh,admin']);
    $routes->post('(:num)/annuler',   'OnboardingController::annuler/$1', ['filter' => 'role:rh,admin']);
    $routes->get('(:num)/historique', 'OnboardingController::historique/$1', ['filter' => 'role:rh,admin']);
    $routes->get('(:num)/taches',     'OnboardingController::listerTaches/$1', ['filter' => 'role:rh,admin']);
	$routes->get('(:num)/progression', 'OnboardingController::progression/$1', ['filter' => 'role:rh,admin']);
});

$routes->get('api/personnel/mes-taches', 'Personnel\OnboardingController::mesTaches', ['filter' => 'auth']);

// demandes de congé (ancien schéma 07_ : dashboard, conservé pour rétrocompatibilité)
$routes->group('api/conges', ['namespace' => 'App\Controllers\Personnel', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'DemandeCongeController::index');
    $routes->post('/', 'DemandeCongeController::creer');

    // Bloc 7 — Congés et absences (nouveau schéma, CongeController)
    $routes->get('types', 'CongeController::types');
    $routes->get('soldes', 'CongeController::soldes');
    $routes->get('soldes/tous', 'CongeController::soldesTous', ['filter' => 'role:rh,admin']);
    $routes->get('alertes', 'CongeController::alertes', ['filter' => 'role:rh,admin']);
    $routes->post('soldes/ajuster', 'CongeController::ajuster', ['filter' => 'role:rh,admin']);
    $routes->post('cloturer-annee', 'CongeController::cloturerAnnee', ['filter' => 'role:rh,admin']);
    $routes->get('demandes', 'CongeController::historique');
    $routes->post('demandes', 'CongeController::creer');
    $routes->post('demandes/(:num)/annuler', 'CongeController::annuler/$1');
    $routes->post('demandes/(:num)/valider', 'CongeController::valider/$1');
    $routes->post('demandes/(:num)/refuser', 'CongeController::refuser/$1');
    $routes->get('a-valider', 'CongeController::aValider', ['filter' => 'role:manager,rh,admin,direction']);
    $routes->get('planning', 'CongeController::planning');
    $routes->get('jours-feries', 'CongeController::joursFeries');
    $routes->get('absences', 'CongeController::absences', ['filter' => 'role:rh,admin']);
    $routes->post('absences', 'CongeController::saisirAbsence', ['filter' => 'role:rh,admin']);
});

// document salaire
$routes->group('api/salaries/(:num)/documents', ['namespace' => 'App\Controllers\Personnel', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/',       'DocumentController::index/$1', ['filter' => 'role:rh,admin']);
    $routes->post('/',      'DocumentController::ajouter/$1', ['filter' => 'role:rh,admin']);
});

$routes->get('api/personnel/mes-documents', 'Personnel\DocumentController::mesDocuments', ['filter' => 'auth']);

// referentiels
$routes->group('api/referentiels', ['namespace' => 'App\Controllers\Admin', 'filter' => 'auth'], static function ($routes) {
    $routes->get('departements', 'ReferentielController::departements');
    $routes->get('services', 'ReferentielController::services');
    $routes->get('postes', 'ReferentielController::postes');
    $routes->get('grilles', 'ReferentielController::grilles');
    $routes->get('grilles/(:num)', 'ReferentielController::grillesPoste/$1');
    $routes->get('rangs', 'ReferentielController::rangs');
    $routes->get('types-contrat', 'ReferentielController::typesContrat');
    $routes->get('types-entretien', 'ReferentielController::typesEntretien');
    $routes->get('employes', 'ReferentielController::employes');
    $routes->get('statuts/(:segment)', 'ReferentielController::statuts/$1');
    $routes->get('natures-revendication', 'ReferentielController::naturesRevendication');
});

$routes->get('api/referentiels/types-document', 'Personnel\DocumentController::listerTypes', ['filter' => 'auth']);

// supprimer
$routes->group('api/documents', ['namespace' => 'App\Controllers\Personnel', 'filter' => 'auth'], static function ($routes) {
    $routes->get('expirant', 'DocumentController::expirant', ['filter' => 'role:rh,admin']);
    $routes->post('(:num)/remplacer', 'DocumentController::remplacer/$1', ['filter' => 'role:rh,admin']);
    $routes->get('(:num)/telecharger', 'DocumentController::telecharger/$1', ['filter' => 'role:rh,admin']);
    $routes->delete('(:num)', 'DocumentController::supprimer/$1', ['filter' => 'role:rh,admin']);
});

// grille salaire (CRUD Finance)
$routes->get('finance/bulletins', 'Pages::afficher/finance/bulletins', ['filter' => 'role:rh,admin,finance']);
$routes->group('api/finance/grilles-salariales', ['namespace' => 'App\Controllers\Finance', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/',           'GrilleSalarialeController::index', ['filter' => 'role:finance,admin']);
    $routes->post('/',          'GrilleSalarialeController::create', ['filter' => 'role:finance,admin']);
    $routes->get('(:num)',      'GrilleSalarialeController::show/$1', ['filter' => 'role:finance,admin']);
    $routes->patch('(:num)',    'GrilleSalarialeController::update/$1', ['filter' => 'role:finance,admin']);
    $routes->delete('(:num)',  'GrilleSalarialeController::delete/$1', ['filter' => 'role:finance,admin']);
});

// paie : bulletins (rh/admin)
$routes->group('api/paie', ['namespace' => 'App\Controllers\Paie', 'filter' => 'auth'], static function ($routes) {
    $routes->get('bulletins', 'BulletinController::liste', ['filter' => 'role:rh,admin']);
    $routes->post('bulletins/calculer', 'BulletinController::calculer', ['filter' => 'role:rh,admin']);
    $routes->get('bulletins/(:num)/detail', 'BulletinController::detail/$1', ['filter' => 'role:rh,admin']);
    $routes->get('bulletins/(:num)', 'BulletinController::show/$1', ['filter' => 'role:rh,admin']);
    $routes->post('bulletins/(:num)/valider', 'BulletinController::valider/$1', ['filter' => 'role:rh,admin']);
    $routes->get('elements', 'BulletinController::elements', ['filter' => 'role:rh,admin']);
    $routes->post('elements', 'BulletinController::saisirElement', ['filter' => 'role:rh,admin']);
    $routes->get('taux', 'BulletinController::taux', ['filter' => 'role:rh,admin']);
});

//entretiens
$routes->get('entretiens', 'Selection\EntretienController::page');
