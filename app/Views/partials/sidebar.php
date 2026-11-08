<?php
use App\Libraries\Common\Icons;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Common\Roles;

$session = SessionUtilisateur::courante();
$roleUtilisateur = $session ? $session->role : null;

// Département de l'utilisateur connecté (pour le filtrage des liens spécifiques)
$departementUtilisateur = null;
if ($session && $session->idEmploye !== null) {
    $row = \Config\Database::connect()->table('employe e')
        ->select('d.nom AS departement')
        ->join('poste p', 'p.id_poste = e.poste_id')
        ->join('service s', 's.id_service = p.id_service')
        ->join('departement d', 'd.id_departement = s.id_departement')
        ->where('e.id', $session->idEmploye)
        ->get()
        ->getRow();
    $departementUtilisateur = $row !== null ? (string) $row->departement : null;
}

$icons = Icons::all();

$sections = [
    'Pilotage' => [
        'dashboard'   => ['label' => 'Tableau de bord', 'url' => '/dashboard', 'roles' => [Roles::ADMIN, Roles::MANAGER, Roles::RH, Roles::DIRECTION]],
        'demandes'    => ['label' => 'Demandes de recrutement', 'url' => '/recrutement/demandes', 'roles' => [Roles::ADMIN, Roles::MANAGER, Roles::RH, Roles::RECRUTEUR, Roles::CONTROLE_GESTION]],
        'offres'      => ['label' => "Offres d'emploi", 'url' => '/recrutement/offres', 'roles' => [Roles::ADMIN, Roles::RH, Roles::RECRUTEUR]],
        'candidatures' => ['label' => 'Candidatures', 'url' => '/recrutement/candidatures', 'roles' => [Roles::ADMIN, Roles::RH, Roles::RECRUTEUR]],
    ],
    'Sélection' => [
        'entretiens' => ['label' => 'Entretiens', 'url' => '/selection/entretiens', 'roles' => [Roles::ADMIN, Roles::RH, Roles::RECRUTEUR]],
        'evaluations' => ['label' => 'Évaluations', 'url' => '/selection/evaluation', 'roles' => [Roles::ADMIN, Roles::RH, Roles::RECRUTEUR]],
        'propositions' => ['label' => "Propositions d'embauche", 'url' => '/selection/propositions', 'roles' => [Roles::ADMIN, Roles::RH, Roles::DIRECTION, Roles::CONTROLE_GESTION]],
    ],
    'Équipe' => [
        'salaires'    => ['label' => 'Salariés', 'url' => '/personnel/salaries', 'roles' => [Roles::ADMIN, Roles::RH, Roles::MANAGER]],
        'contrats'    => ['label' => 'Contrats', 'url' => '/personnel/contrats', 'roles' => [Roles::ADMIN, Roles::RH, Roles::DIRECTION]],
        'grille'      => ['label' => 'Grille salariale', 'url' => '/personnel/grille', 'roles' => [Roles::ADMIN, Roles::RH, Roles::CONTROLE_GESTION]],
        'onboardings' => ['label' => 'Onboarding', 'url' => '/personnel/onboardings', 'roles' => [Roles::ADMIN, Roles::RH]],
        'mesDocuments' => ['label' => 'Mes documents', 'url' => '/personnel/mes-documents', 'roles' => [Roles::ADMIN, Roles::RH, Roles::SALARIE]],
        'mesTaches'    => ['label' => 'Mes tâches onboarding', 'url' => '/personnel/mes-taches', 'roles' => [Roles::SALARIE]],
    ],
    'Sécurité & confidentialité' => [
        'audit' => ['label' => 'Audit des accès documents', 'url' => '/securite/audit', 'roles' => [Roles::ADMIN, Roles::RH]],
        'acces' => ['label' => 'Gestion des accès', 'url' => '/securite/acces', 'roles' => [Roles::ADMIN, Roles::RH]],
    ],
    'Informatique' => [
        'dashboardIt' => ['label' => 'Tableau de bord informatique', 'url' => '/departement/informatique', 'roles' => [Roles::ADMIN, Roles::RH, Roles::SALARIE], 'departement' => 'Informatique'],
        'creerUtilisateur' => ['label' => 'Créer un utilisateur', 'url' => '/departement/informatique/creer-utilisateur', 'roles' => [Roles::ADMIN, Roles::RH, Roles::SALARIE], 'departement' => 'Informatique'],
        'reinitialiserMdp' => ['label' => 'Réinitialiser un mot de passe', 'url' => '/departement/informatique/reinitialiser-mdp', 'roles' => [Roles::ADMIN, Roles::RH, Roles::SALARIE], 'departement' => 'Informatique'],
    ],
];

function peutVoirLien(?string $roleUtilisateur, ?string $departementUtilisateur, array $lien): bool
{
    if ($roleUtilisateur === null) {
        return false;
    }
    if ($roleUtilisateur === Roles::ADMIN) {
        return true;
    }
    if (!in_array($roleUtilisateur, $lien['roles'] ?? [], true)) {
        return false;
    }
    // Restriction par département
    if (!empty($lien['departement']) && $departementUtilisateur !== $lien['departement']) {
        return false;
    }
    return true;
}
?>
<aside class="sidebar">
    <nav>
        <?php foreach ($sections as $section => $liens): ?>
            <?php 
            $liensVisibles = array_filter($liens, function($lien) use ($roleUtilisateur, $departementUtilisateur) {
                return peutVoirLien($roleUtilisateur, $departementUtilisateur, $lien);
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
