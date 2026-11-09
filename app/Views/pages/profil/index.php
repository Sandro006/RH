<?php
use App\Libraries\Common\SessionUtilisateur;

$session = SessionUtilisateur::courante();
?>

<?= view('partials/page-heading', [
    'heading'  => 'Mon profil',
    'subtitle' => 'Informations de votre compte utilisateur.',
]) ?>

<section class="card">
    <div class="profile-header">
        <div class="profile-avatar">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        </div>
        <div class="profile-info">
            <h2><?= esc($session->identifiant) ?></h2>
            <span class="badge badge-primary"><?= esc(ucfirst($session->role)) ?></span>
        </div>
    </div>
</section>

<section class="card">
    <h2>Informations du compte</h2>
    <div class="info-grid">
        <div class="info-item">
            <label>Identifiant</label>
            <strong><?= esc($session->identifiant) ?></strong>
        </div>
        <div class="info-item">
            <label>Rôle</label>
            <strong><?= esc(ucfirst($session->role)) ?></strong>
        </div>
        <div class="info-item">
            <label>ID Utilisateur</label>
            <strong><?= $session->idUtilisateur ?></strong>
        </div>
        <?php if ($session->idEmploye !== null): ?>
        <div class="info-item">
            <label>ID Employé</label>
            <strong><?= $session->idEmploye ?></strong>
        </div>
        <?php endif; ?>
        <div class="info-item">
            <label>Adresse IP</label>
            <strong><?= esc($session->adresseIp) ?></strong>
        </div>
        <div class="info-item">
            <label>Dernière connexion</label>
            <strong><?= esc(date('d/m/Y H:i', strtotime($session->dateConnexion))) ?></strong>
        </div>
        <div class="info-item">
            <label>Doit changer le mot de passe</label>
            <strong><?= $session->doitChangerMotDePasse ? 'Oui' : 'Non' ?></strong>
        </div>
    </div>
</section>

<section class="card">
    <h2>Actions rapides</h2>
    <div class="shortcut-grid">
        <a href="/profil/mot-de-passe" class="shortcut">
            <div class="shortcut-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </div>
            <div class="shortcut-content">
                <strong>Changer mon mot de passe</strong>
                <small class="muted">Mettre à jour mes identifiants</small>
            </div>
        </a>
        <a href="/dashboard" class="shortcut">
            <div class="shortcut-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            </div>
            <div class="shortcut-content">
                <strong>Tableau de bord</strong>
                <small class="muted">Retourner à l'accueil</small>
            </div>
        </a>
    </div>
</section>
