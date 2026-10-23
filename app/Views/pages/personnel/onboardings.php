<?= view('partials/page-heading', [
    'heading' => 'Onboardings',
    'actions' => '<button id="start" class="button-primary" type="button">Démarrer un onboarding</button>',
]) ?>

<section class="card">
    <?= view('partials/filters', [
        'fields' => [
            ['name' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => [
                ''  => 'Tous',
                'À démarrer' => 'À démarrer',
                'En cours'   => 'En cours',
                'Terminé'    => 'Terminé',
                'Annulé'     => 'Annulé',
            ]],
            ['name' => 'idSalarie', 'label' => 'Salarié', 'type' => 'number'],
        ],
    ]) ?>
</section>

<section class="card">
    <div id="list" class="state">Chargement…</div>
</section>
