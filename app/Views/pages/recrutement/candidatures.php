<?= view('partials/page-heading', [
    'heading' => 'Candidatures',
    'actions' => view('partials/refresh-button'),
]) ?>

<section class="card">
    <p class="muted">Ouvrez une candidature pour consulter ses évaluations ou ajouter une note.</p>
    <div id="list" class="state">Chargement…</div>
</section>
