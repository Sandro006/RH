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
        'dashboard'   => ['label' => 'Tableau de bord', 'url' => '/dashboard', 'roles' => [Roles::ADMIN, Roles::MANAGER, Roles::RH, Roles::SALARIE, Roles::DIRECTION]],
        'demandes'    => ['label' => 'Demandes de recrutement', 'url' => '/recrutement/demandes', 'roles' => [Roles::ADMIN, Roles::MANAGER, Roles::RH, Roles::RECRUTEUR, Roles::CONTROLE_GESTION]],
        'offres'      => ['label' => "Offres d'emploi", 'url' => '/recrutement/offres', 'roles' => [Roles::ADMIN, Roles::RH, Roles::RECRUTEUR]],
        'candidatures' => ['label' => 'Candidatures', 'url' => '/recrutement/candidatures', 'roles' => [Roles::ADMIN, Roles::RH, Roles::RECRUTEUR]],
    ],
    'Congés' => [
        'mesConges'    => ['label' => 'Mes congés', 'url' => '/personnel/mes-conges', 'roles' => Roles::TOUS],
        'congeForm'    => ['label' => 'Nouvelle demande de congé', 'url' => '/personnel/conge-form', 'roles' => Roles::TOUS],
    ],
    'Sélection' => [
        'entretiens' => ['label' => 'Entretiens', 'url' => '/selection/entretiens', 'roles' => [Roles::ADMIN, Roles::RH, Roles::RECRUTEUR]],
        'evaluations' => ['label' => 'Évaluations', 'url' => '/selection/evaluation', 'roles' => [Roles::ADMIN, Roles::RH, Roles::RECRUTEUR]],
        'propositions' => ['label' => "Propositions d'embauche", 'url' => '/selection/propositions', 'roles' => [Roles::ADMIN, Roles::RH, Roles::DIRECTION, Roles::CONTROLE_GESTION]],
    ],
    'Temps de travail' => [
        'temps' => ['label' => 'Calendrier et pointage', 'url' => '/temps/calendrier', 'roles' => [Roles::ADMIN, Roles::RH]],
    ],
    'Mon espace' => [
        'congesPlanning' => ['label' => 'Planning des congés', 'url' => '/personnel/conges-planning', 'roles' => [Roles::SALARIE]],
        'mesDocuments' => ['label' => 'Mes documents', 'url' => '/personnel/mes-documents', 'roles' => [Roles::SALARIE]],
        'mesTaches'    => ['label' => 'Mes tâches onboarding', 'url' => '/personnel/mes-taches', 'roles' => [Roles::SALARIE]],
    ],
    'Équipe' => [
        'salaires'    => ['label' => 'Salariés', 'url' => '/personnel/salaries', 'roles' => [Roles::ADMIN, Roles::RH, Roles::MANAGER]],
        'contrats'    => ['label' => 'Contrats', 'url' => '/personnel/contrats', 'roles' => [Roles::ADMIN, Roles::RH, Roles::DIRECTION]],
        'grille'      => ['label' => 'Grille salariale', 'url' => '/personnel/grille', 'roles' => [Roles::ADMIN, Roles::RH, Roles::CONTROLE_GESTION]],
        'onboardings' => ['label' => 'Onboarding', 'url' => '/personnel/onboardings', 'roles' => [Roles::ADMIN, Roles::RH]],
        'mesDocuments' => ['label' => 'Mes documents', 'url' => '/personnel/mes-documents', 'roles' => [Roles::ADMIN, Roles::RH, Roles::MANAGER]],
        'mesTaches'    => ['label' => 'Mes tâches onboarding', 'url' => '/personnel/mes-taches', 'roles' => [Roles::MANAGER]],
        'congesAValider' => ['label' => 'Validations des congés', 'url' => '/personnel/conges-a-valider', 'roles' => [Roles::ADMIN, Roles::RH, Roles::MANAGER, Roles::DIRECTION]],
        'congesPlanning' => ['label' => 'Planning des congés', 'url' => '/personnel/conges-planning', 'roles' => [Roles::ADMIN, Roles::RH, Roles::MANAGER, Roles::DIRECTION]],
        'congesSoldes' => ['label' => 'Soldes des congés (RH)', 'url' => '/personnel/conges-soldes', 'roles' => [Roles::ADMIN, Roles::RH]],
        'congesAbsences' => ['label' => 'Absences (RH)', 'url' => '/personnel/conges-absences', 'roles' => [Roles::ADMIN, Roles::RH]],
    ],
    'Finances' => [
        'gestionPaie' => ['label' => 'Gestion paie', 'url' => '/finance/bulletins', 'roles' => [Roles::ADMIN, Roles::RH, Roles::FINANCE]],
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
