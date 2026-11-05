<?= view('partials/page-heading', [
    'heading'  => 'Tableau de bord',
    'subtitle' => "La vue d'ensemble de votre activité RH.",
]) ?>

<section id="metrics" class="metrics">
    <?php foreach ([
        'Demandes en attente',
        'Offres publiées',
        'Candidatures actives',
        'Entretiens à venir',
        'Propositions en cours',
        'Tâches onboarding',
    ] as $label): ?>
        <div class="metric">
            <small><?= esc($label) ?></small>
            <strong>—</strong>
        </div>
    <?php endforeach; ?>
</section>

<section class="card">
    <h2>Mes actions en attente</h2>
    <div id="actions" class="state">Chargement…</div>
</section>

<section class="card">
    <h2>Accès rapides</h2>
    <div class="shortcut-grid">
        <a class="shortcut" href="/recrutement/demande-form">Créer une demande<br><small class="muted">Démarrer un recrutement</small></a>
        <a class="shortcut" href="/personnel/salaries">Voir l'effectif<br><small class="muted">Consulter les salariés</small></a>
        <a class="shortcut" href="/personnel/onboardings">Suivre l'onboarding<br><small class="muted">Avancement des arrivées</small></a>
    </div>
</section>
