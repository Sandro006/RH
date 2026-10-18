<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// D1 : salaries
$routes->group('api/salaries', ['namespace' => 'App\Controllers\Personnel'], static function ($routes) {
    $routes->get('/',          'SalarieController::index');
    $routes->post('/',         'SalarieController::createDepuisCandidature');
    $routes->get('(:num)',     'SalarieController::show/$1');
    $routes->patch('(:num)',   'SalarieController::update/$1');
    $routes->post('(:num)/affectations', 'SalarieController::affecter/$1');
    $routes->get('(:num)/affectations',  'SalarieController::listerAffectations/$1');
    $routes->get('(:num)/historique',    'SalarieController::historique/$1');
});
