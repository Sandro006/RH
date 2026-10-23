<?= view('partials/page-heading', [
    'heading' => 'Entretiens',
    'actions' => view('partials/refresh-button'),
]) ?>

<section class="card">
    <?= view('partials/filters', [
        'fields' => [
            ['name' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => [
                ''           => 'Tous',
                'Planifié'   => 'Planifié',
                'Confirmé'   => 'Confirmé',
                'Réalisé'    => 'Réalisé',
                'Reporté'    => 'Reporté',
                'Annulé'     => 'Annulé',
            ]],
            ['name' => 'dateDebut', 'label' => 'Du', 'type' => 'date'],
            ['name' => 'dateFin', 'label' => 'Au', 'type' => 'date'],
        ],
    ]) ?>
</section>

<section class="card">
    <div id="list" class="state">Chargement…</div>
</section>
