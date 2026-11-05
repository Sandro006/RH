<?php
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Common\Roles;

$session = SessionUtilisateur::courante();
$roleUtilisateur = $session ? $session->role : null;

$icons = [
    'dashboard' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
    'demandes' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
    'offres' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m16 12-4-4-4 4"></path><path d="M12 16V8"></path></svg>',
    'candidatures' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
    'entretiens' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><path d="M8 10h.01"></path><path d="M12 10h.01"></path><path d="M16 10h.01"></path></svg>',
    'evaluations' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>',
    'propositions' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="m9 15 2 2 4-4"></path></svg>',
    'salaires' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
    'contrats' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
    'grille' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>',
    'onboardings' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
];

$sections = [
    'Pilotage' => [
        'dashboard'   => ['label' => 'Tableau de bord', 'url' => '/dashboard', 'roles' => [Roles::ADMIN, Roles::MANAGER, Roles::RH, Roles::DIRECTION]],
        'demandes'    => ['label' => 'Demandes de recrutement', 'url' => '/recrutement/demandes', 'roles' => [Roles::ADMIN, Roles::MANAGER, Roles::RH, Roles::RECRUTEUR, Roles::CONTROLE_GESTION]],
        'offres'      => ['label' => "Offres d'emploi", 'url' => '/recrutement/offres', 'roles' => [Roles::ADMIN, Roles::RH, Roles::RECRUTEUR]],
        'candidatures' => ['label' => 'Candidatures', 'url' => '/recrutement/candidatures', 'roles' => [Roles::ADMIN, Roles::RH, Roles::RECRUTEUR]],
    ],
    'Sélection' => [
        'entretiens' => ['label' => 'Entretiens', 'url' => '/selection/entretiens', 'roles' => [Roles::ADMIN, Roles::RH, Roles::RECRUTEUR]],
        'evaluations' => ['label' => 'Évaluations', 'url' => '/recrutement/candidatures', 'roles' => [Roles::ADMIN, Roles::RH, Roles::RECRUTEUR]],
        'propositions' => ['label' => "Propositions d'embauche", 'url' => '/selection/propositions', 'roles' => [Roles::ADMIN, Roles::RH, Roles::DIRECTION, Roles::CONTROLE_GESTION]],
    ],
    'Équipe' => [
        'salaires'    => ['label' => 'Salariés', 'url' => '/personnel/salaries', 'roles' => [Roles::ADMIN, Roles::RH, Roles::MANAGER]],
        'contrats'    => ['label' => 'Contrats', 'url' => '/personnel/contrats', 'roles' => [Roles::ADMIN, Roles::RH]],
        'grille'      => ['label' => 'Grille salariale', 'url' => '/personnel/grille', 'roles' => [Roles::ADMIN, Roles::RH, Roles::CONTROLE_GESTION]],
        'onboardings' => ['label' => 'Onboarding', 'url' => '/personnel/onboardings', 'roles' => [Roles::ADMIN, Roles::RH]],
    ],
];

function peutVoirLien(?string $roleUtilisateur, array $rolesAutorises): bool
{
    if ($roleUtilisateur === null) {
        return false;
    }
    if ($roleUtilisateur === Roles::ADMIN) {
        return true;
    }
    return in_array($roleUtilisateur, $rolesAutorises, true);
}
?>
<aside class="sidebar">
    <nav>
        <?php foreach ($sections as $section => $liens): ?>
            <?php 
            $liensVisibles = array_filter($liens, function($lien) use ($roleUtilisateur) {
                return peutVoirLien($roleUtilisateur, $lien['roles'] ?? []);
            });
            ?>
            <?php if (!empty($liensVisibles)): ?>
                <div class="sidebar-label"><?= esc($section) ?></div>
                <?php foreach ($liensVisibles as $cle => $lien): ?>
                    <a href="<?= esc($lien['url']) ?>"<?= ($active ?? '') === $cle ? ' class="active"' : '' ?>>
                        <?= $icons[$cle] ?? '' ?>
                        <?= esc($lien['label']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</aside>
