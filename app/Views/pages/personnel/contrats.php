<?= view('partials/page-heading', [
    'heading' => 'Contrats',
    'actions' => '<a class="button button-primary" href="/personnel/contrat-form">Nouveau contrat</a>',
]) ?>

<section class="card">
    <?= view('partials/filters', [
        'fields' => [
            ['name' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => [
                ''  => 'Tous',
                'En préparation'         => 'En préparation',
                'En attente de signature' => 'En attente de signature',
                'Signé'                  => 'Signé',
                'En cours'               => 'En cours',
                'Terminé'                => 'Terminé',
                'Résilié'                => 'Résilié',
            ]],
            ['name' => 'idSalarie', 'label' => 'Salarié', 'type' => 'number'],
            ['name' => 'typeContratId', 'label' => 'Type', 'type' => 'number'],
        ],
    ]) ?>
</section>

<section class="card">
    <div id="list" class="state">Chargement…</div>
</section>
