<?php
$sections = [
    'Pilotage' => [
        'dashboard'   => ['label' => 'Tableau de bord', 'url' => '/dashboard'],
        'demandes'    => ['label' => 'Demandes de recrutement', 'url' => '/recrutement/demandes'],
        'offres'      => ['label' => "Offres d'emploi", 'url' => '/recrutement/offres'],
        'candidatures' => ['label' => 'Candidatures', 'url' => '/recrutement/candidatures'],
    ],
    'Sélection' => [
        'entretiens' => ['label' => 'Entretiens', 'url' => '/selection/entretiens'],
        'evaluations' => ['label' => 'Évaluations', 'url' => '/recrutement/candidatures'],
    ],
    'Équipe' => [
        'salaires'    => ['label' => 'Salariés', 'url' => '/personnel/salaries'],
        'contrats'    => ['label' => 'Contrats', 'url' => '/personnel/contrats'],
        'grille'      => ['label' => 'Grille salariale', 'url' => '/personnel/grille'],
        'onboardings' => ['label' => 'Onboarding', 'url' => '/personnel/onboardings'],
    ],
];
?>
<aside class="sidebar">
    <nav>
        <?php foreach ($sections as $section => $liens): ?>
            <div class="sidebar-label"><?= esc($section) ?></div>
            <?php foreach ($liens as $cle => $lien): ?>
                <a href="<?= esc($lien['url']) ?>"<?= ($active ?? '') === $cle ? ' class="active"' : '' ?>><?= esc($lien['label']) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>
