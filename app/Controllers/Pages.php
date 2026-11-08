<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

final class Pages extends BaseController
{
    private const PAGES = [
        'temps/calendrier' => ['vue' => 'pages/temps/calendrier', 'titre' => 'Temps de travail · Gestion RH', 'actif' => 'temps', 'fil' => [['label' => 'Temps de travail']], 'scripts' => ['/assets/js/pages/temps/calendrier.js']],
        'dashboard' => [
            'vue'      => 'pages/dashboard',
            'titre'    => 'Tableau de bord · Gestion RH',
            'actif'    => 'dashboard',
            'fil'      => [['label' => 'Accueil / Tableau de bord']],
            'scripts'  => ['/assets/js/pages/dashboard.js'],
        ],
        'recrutement/demandes' => [
            'vue'      => 'pages/recrutement/demandes',
            'titre'    => 'Demandes de recrutement · Gestion RH',
            'actif'    => 'demandes',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Recrutement'],
            ],
            'scripts'  => ['/assets/js/pages/recrutement/demandes.js'],
        ],
        'recrutement/demande-form' => [
            'vue'      => 'pages/recrutement/demande-form',
            'titre'    => 'Nouvelle demande de recrutement · Gestion RH',
            'actif'    => 'demandes',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Recrutement', 'url' => '/recrutement/demandes'],
                ['label' => 'Nouvelle demande'],
            ],
            'scripts'  => ['/assets/js/pages/recrutement/demande-form.js'],
        ],
        'recrutement/demande' => [
            'vue'      => 'pages/recrutement/demande',
            'titre'    => 'Demande de recrutement · Gestion RH',
            'actif'    => 'demandes',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Recrutement', 'url' => '/recrutement/demandes'],
                ['label' => 'Demande'],
            ],
            'scripts'  => ['/assets/js/pages/recrutement/demande.js'],
        ],
        'recrutement/demande-edit' => [
            'vue'      => 'pages/recrutement/demande-edit',
            'titre'    => 'Modifier une demande · Gestion RH',
            'actif'    => 'demandes',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Recrutement', 'url' => '/recrutement/demandes'],
                ['label' => 'Modifier'],
            ],
            'scripts'  => ['/assets/js/pages/recrutement/demande-edit.js'],
        ],
        'recrutement/offres' => [
            'vue'      => 'pages/recrutement/offres',
            'titre'    => "Offres d'emploi · Gestion RH",
            'actif'    => 'offres',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Recrutement'],
            ],
            'scripts'  => ['/assets/js/pages/recrutement/offres.js'],
        ],
        'recrutement/offre' => [
            'vue'      => 'pages/recrutement/offre',
            'titre'    => "Offre d'emploi · Gestion RH",
            'actif'    => 'offres',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Recrutement', 'url' => '/recrutement/offres'],
                ['label' => 'Offre'],
            ],
            'scripts'  => ['/assets/js/pages/recrutement/offre.js'],
        ],
        'recrutement/offre-form' => [
            'vue'      => 'pages/recrutement/offre-form',
            'titre'    => "Publier une offre · Gestion RH",
            'actif'    => 'offres',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Recrutement', 'url' => '/recrutement/offres'],
                ['label' => 'Offre'],
            ],
            'scripts'  => ['/assets/js/pages/recrutement/offre-form.js'],
        ],
        'recrutement/candidatures' => [
            'vue'      => 'pages/recrutement/candidatures',
            'titre'    => 'Candidatures · Gestion RH',
            'actif'    => 'candidatures',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Recrutement'],
            ],
            'scripts'  => ['/assets/js/pages/recrutement/candidatures.js'],
        ],
        'selection/evaluation' => [
            'vue'      => 'pages/selection/evaluation',
            'titre'    => 'Évaluer une candidature · Gestion RH',
            'actif'    => 'evaluations',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Sélection'],
            ],
            'scripts'  => ['/assets/js/pages/selection/evaluation.js'],
        ],
        'selection/classement' => [
            'vue'      => 'pages/selection/classement',
            'titre'    => 'Classement des candidatures · Gestion RH',
            'actif'    => 'evaluations',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Sélection'],
            ],
            'scripts'  => ['/assets/js/pages/selection/classement.js'],
        ],
        'selection/propositions' => [
            'vue'      => 'pages/selection/propositions',
            'titre'    => 'Propositions d\'embauche · Gestion RH',
            'actif'    => 'propositions',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Sélection / Propositions'],
            ],
            'scripts'  => ['/assets/js/pages/selection/propositions.js'],
        ],
        'selection/proposition-form' => [
            'vue'      => 'pages/selection/proposition-form',
            'titre'    => 'Nouvelle proposition · Gestion RH',
            'actif'    => 'propositions',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Sélection / Propositions'],
            ],
            'scripts'  => ['/assets/js/pages/selection/proposition-form.js'],
        ],
        'selection/proposition' => [
            'vue'      => 'pages/selection/proposition',
            'titre'    => 'Proposition d\'embauche · Gestion RH',
            'actif'    => 'propositions',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Sélection / Propositions'],
            ],
            'scripts'  => ['/assets/js/pages/selection/proposition.js'],
        ],
        'selection/entretiens' => [
            'vue'      => 'pages/selection/entretiens',
            'titre'    => 'Entretiens · Gestion RH',
            'actif'    => 'entretiens',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Sélection'],
            ],
            'scripts'  => ['/assets/js/pages/selection/entretiens.js'],
        ],
        'selection/entretien-form' => [
            'vue'      => 'pages/selection/entretien-form',
            'titre'    => 'Planifier un entretien · Gestion RH',
            'actif'    => 'entretiens',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Sélection'],
            ],
            'scripts'  => ['/assets/js/pages/selection/entretien-form.js'],
        ],
        'personnel/salaries' => [
            'vue'      => 'pages/personnel/salaires',
            'titre'    => 'Salariés · Gestion RH',
            'actif'    => 'salaires',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/salaries.js'],
        ],
        'personnel/salarie' => [
            'vue'      => 'pages/personnel/salarie',
            'titre'    => 'Fiche salarié · Gestion RH',
            'actif'    => 'salaires',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel / Salariés'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/salarie.js'],
        ],
        'personnel/salarie-form' => [
            'vue'      => 'pages/personnel/salarie-form',
            'titre'    => 'Créer un salarié · Gestion RH',
            'actif'    => 'salaires',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel / Salariés'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/salarie-form.js'],
        ],
        'personnel/grille' => [
            'vue'      => 'pages/personnel/grille',
            'titre'    => 'Grille salariale · Gestion RH',
            'actif'    => 'grille',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel / Grille salariale'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/grille.js'],
        ],
        'personnel/contrats' => [
            'vue'      => 'pages/personnel/contrats',
            'titre'    => 'Contrats · Gestion RH',
            'actif'    => 'contrats',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/contrats.js'],
        ],
        'personnel/contrat' => [
            'vue'      => 'pages/personnel/contrat',
            'titre'    => 'Contrat · Gestion RH',
            'actif'    => 'contrats',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel / Contrats'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/contrat.js'],
        ],
        'personnel/contrat-form' => [
            'vue'      => 'pages/personnel/contrat-form',
            'titre'    => 'Contrat · Gestion RH',
            'actif'    => 'contrats',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel / Contrats'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/contrat-form.js'],
        ],
        'personnel/contrat-preview' => [
            'vue'      => 'pages/personnel/contrat-preview',
            'titre'    => 'Aperçu du contrat · Gestion RH',
            'actif'    => 'contrats',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel / Contrats'],
            ],
            'layouts'  => 'blank',
            'scripts'  => ['/assets/js/api.js', '/assets/js/ui.js'],
        ],
        'personnel/contrat-avenant-form' => [
            'vue'      => 'pages/personnel/contrat-avenant-form',
            'titre'    => 'Avenant · Gestion RH',
            'actif'    => 'contrats',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel / Contrats'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/contrat-avenant-form.js'],
        ],
        'personnel/affectation-form' => [
            'vue'      => 'pages/personnel/affectation-form',
            'titre'    => 'Affectation · Gestion RH',
            'actif'    => 'salaires',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel / Salariés'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/affectation-form.js'],
        ],
        'personnel/onboardings' => [
            'vue'      => 'pages/personnel/onboardings',
            'titre'    => 'Onboardings · Gestion RH',
            'actif'    => 'onboardings',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/onboardings.js'],
        ],
        'personnel/onboarding' => [
            'vue'      => 'pages/personnel/onboarding',
            'titre'    => 'Onboarding · Gestion RH',
            'actif'    => 'onboardings',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel / Onboarding'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/onboarding.js'],
        ],
        'personnel/onboarding-form' => [
            'vue'      => 'pages/personnel/onboarding-form',
            'titre'    => 'Modifier l\'onboarding · Gestion RH',
            'actif'    => 'onboardings',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel / Onboardings', 'url' => '/personnel/onboardings'],
                ['label' => 'Modifier'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/onboarding-form.js'],
        ],
        'personnel/onboarding-tache-form' => [
            'vue'      => 'pages/personnel/onboarding-tache-form',
            'titre'    => 'Modifier la tâche · Gestion RH',
            'actif'    => 'onboardings',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel / Onboardings', 'url' => '/personnel/onboardings'],
                ['label' => 'Tâche'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/onboarding-tache-form.js'],
        ],
        'personnel/document-form' => [
            'vue'      => 'pages/personnel/document-form',
            'titre'    => 'Document salarié · Gestion RH',
            'actif'    => 'salaires',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Personnel / Salariés'],
            ],
            'scripts'  => ['/assets/js/pages/personnel/document-form.js'],
        ],
        'profil/mot-de-passe' => [
            'vue'      => 'pages/profil/mot-de-passe',
            'titre'    => 'Changer le mot de passe · Gestion RH',
            'actif'    => '',
            'fil'      => [
                ['label' => 'Tableau de bord', 'url' => '/dashboard'],
                ['label' => 'Profil'],
            ],
            'scripts'  => ['/assets/js/pages/profil/mot-de-passe.js'],
        ],
    ];

    public function login()
    {
        return view('layouts/auth', [
            'title'   => 'Connexion · Gestion RH',
            'content' => view('pages/auth/login'),
        ]);
    }

    public function dashboard()
    {
        return $this->rendre('dashboard');
    }

    public function afficher(string $section, ?string $page = null)
    {
        $cle = $page === null ? $section : $section . '/' . $page;

        if (!isset(self::PAGES[$cle])) {
            throw PageNotFoundException::forPageNotFound();
        }

        // Pour les pages avec layout blank, retourner la vue directement
        if (isset(self::PAGES[$cle]['layouts']) && self::PAGES[$cle]['layouts'] === 'blank') {
            return view(self::PAGES[$cle]['vue']);
        }

        return $this->rendre($cle);
    }

    private function rendre(string $cle)
    {
        $page = self::PAGES[$cle];

        return view('layouts/app', [
            'title'      => $page['titre'],
            'active'     => $page['actif'],
            'breadcrumb' => $page['fil'],
            'content'    => view($page['vue']),
            'scripts'    => $page['scripts'] ?? [],
        ]);
    }
}
