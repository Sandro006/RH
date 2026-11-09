<?= view('partials/page-heading', [
    'heading'  => 'Tableau de bord',
    'subtitle' => "La vue d'ensemble de votre activité RH.",
]) ?>

<section id="mon-profil" class="card">
    <div class="profil-entete">
        <div class="profil-avatar" id="profil-initiales">—</div>
        <div class="profil-identite">
            <strong id="profil-nom">Chargement…</strong>
            <span id="profil-poste" class="muted">—</span>
        </div>
    </div>
    <div class="form-grid" id="profil-details">
        <div><small>Matricule</small><strong id="profil-matricule">—</strong></div>
        <div><small>Service</small><strong id="profil-service">—</strong></div>
        <div><small>Département</small><strong id="profil-departement">—</strong></div>
        <div><small>E-mail</small><strong id="profil-email">—</strong></div>
        <div><small>Téléphone</small><strong id="profil-telephone">—</strong></div>
        <div><small>Rôle</small><strong id="profil-role">—</strong></div>
    </div>
</section>

<section id="metrics" class="metrics">
    <div class="metric metric-primary">
        <div class="metric-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
        </div>
        <div class="metric-content">
            <small>Demandes en attente</small>
            <strong data-metric="demandesEnAttente">—</strong>
        </div>
    </div>
    <div class="metric metric-success">
        <div class="metric-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m16 12-4-4-4 4"></path><path d="M12 16V8"></path></svg>
        </div>
        <div class="metric-content">
            <small>Offres publiées</small>
            <strong data-metric="offresPubliees">—</strong>
        </div>
    </div>
    <div class="metric metric-info">
        <div class="metric-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </div>
        <div class="metric-content">
            <small>Candidatures actives</small>
            <strong data-metric="candidatures">—</strong>
        </div>
    </div>
    <div class="metric metric-warning">
        <div class="metric-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><path d="M8 10h.01"></path><path d="M12 10h.01"></path><path d="M16 10h.01"></path></svg>
        </div>
        <div class="metric-content">
            <small>Entretiens à venir</small>
            <strong data-metric="entretiensAVenir">—</strong>
        </div>
    </div>
    <div class="metric metric-primary">
        <div class="metric-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="m9 15 2 2 4-4"></path></svg>
        </div>
        <div class="metric-content">
            <small>Propositions en cours</small>
            <strong data-metric="propositionsEnCours">—</strong>
        </div>
    </div>
    <div class="metric metric-info">
        <div class="metric-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        </div>
        <div class="metric-content">
            <small>Tâches onboarding</small>
            <strong data-metric="tachesOnboarding">—</strong>
        </div>
    </div>
    <div class="metric metric-warning">
        <div class="metric-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        </div>
        <div class="metric-content">
            <small>Documents expirant (30 j)</small>
            <strong data-metric="documentsExpirant">—</strong>
        </div>
    </div>
    <div class="metric metric-danger">
        <div class="metric-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
        <div class="metric-content">
            <small>Documents expirés</small>
            <strong data-metric="documentsExpires">—</strong>
        </div>
    </div>
</section>

<section class="card">
    <h2>Mes actions en attente</h2>
    <div id="actions" class="state">Chargement…</div>
</section>

<section class="card">
    <h2>Accès rapides</h2>
    <div class="shortcut-grid">
        <a class="shortcut" href="/recrutement/demande-form">
            <div class="shortcut-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <div class="shortcut-content">
                <strong>Créer une demande</strong>
                <small class="muted">Démarrer un recrutement</small>
            </div>
        </a>
        <a class="shortcut" href="/personnel/salaries">
            <div class="shortcut-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <div class="shortcut-content">
                <strong>Voir l'effectif</strong>
                <small class="muted">Consulter les salariés</small>
            </div>
        </a>
        <a class="shortcut" href="/personnel/onboardings">
            <div class="shortcut-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <div class="shortcut-content">
                <strong>Suivre l'onboarding</strong>
                <small class="muted">Avancement des arrivées</small>
            </div>
        </a>
    </div>
</section>
