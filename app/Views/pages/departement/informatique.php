<?php

use App\Libraries\Common\Icons;

?>
<?= view('partials/page-heading', [
    'heading'  => 'Tableau de bord Informatique',
    'subtitle' => 'Gestion des accès et des comptes du département Informatique.',
]) ?>

<section id="tuiles" class="card">
    <div class="tuile-grid">
        <!-- Tuile : Créer un utilisateur -->
        <a href="/departement/informatique/creer-utilisateur" class="tuile tuile-primary">
            <div class="tuile-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
            </div>
            <div class="tuile-info">
                <h3>Créer un utilisateur</h3>
                <p>Créer un nouveau compte de connexion pour un employé.</p>
            </div>
            <div class="tuile-arrow">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </div>
        </a>

        <!-- Tuile : Réinitialiser mot de passe -->
        <a href="/departement/informatique/reinitialiser-mdp" class="tuile tuile-warning">
            <div class="tuile-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </div>
            <div class="tuile-info">
                <h3>Réinitialiser mot de passe</h3>
                <p>Générer un mot de passe temporaire pour un employé.</p>
            </div>
            <div class="tuile-arrow">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </div>
        </a>

        <!-- Tuile : Gérer les accès (liste) -->
        <a href="/securite/acces" class="tuile tuile-info">
            <div class="tuile-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
            <div class="tuile-info">
                <h3>Voir tous les utilisateurs</h3>
                <p>Consulter la liste complète des comptes et leur état.</p>
            </div>
            <div class="tuile-arrow">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </div>
        </a>

        <!-- Tuile : Créer des badges -->
        <div class="tuile tuile-disabled">
            <div class="tuile-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
            <div class="tuile-info">
                <h3>Créer un badge</h3>
                <p class="muted">Fonctionnalité à venir (gestion des badges physiques).</p>
            </div>
            <div class="tuile-arrow">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </div>
        </div>
    </div>
</section>

<section id="rapide" class="card">
    <h2>Accès rapides</h2>
    <div class="shortcut-grid">
        <a href="/personnel/mes-documents" class="shortcut">
            <div class="shortcut-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            </div>
            <div class="shortcut-content">
                <strong>Mes documents</strong>
                <small class="muted">Consulter mes documents</small>
            </div>
        </a>
        <a href="/personnel/mes-taches" class="shortcut">
            <div class="shortcut-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <div class="shortcut-content">
                <strong>Mes tâches onboarding</strong>
                <small class="muted">Suivre mon onboarding</small>
            </div>
        </a>
        <a href="/securite/acces" class="shortcut">
            <div class="shortcut-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            </div>
            <div class="shortcut-content">
                <strong>Gestion des accès</strong>
                <small class="muted">Administrer les comptes</small>
            </div>
        </a>
    </div>
</section>
