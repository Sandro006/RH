<div id="heading" class="page-heading">
    <h1>Proposition d'embauche</h1>
</div>

<?= view('partials/tabs', [
    'active' => 'details',
    'tabs'   => [
        'details'     => 'Détails',
        'negociation' => 'Négociation',
        'historique'  => 'Historique',
    ],
]) ?>

<section id="content" class="card">Chargement…</section>
