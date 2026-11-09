<?= view('partials/page-heading', [
    'heading' => 'Salariés',
    'actions' => '<a class="button button-primary" href="/personnel/salarie-form">Créer depuis une candidature</a>',
]) ?>

<section class="card">
    <?= view('partials/filters', [
        'fields' => [
            ['name' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => [
                ''  => 'Tous',
                '1' => "En attente d'embauche",
                '2' => 'Actif',
                '3' => "En période d'essai",
                '4' => 'Suspendu',
                '5' => 'Parti',
            ]],
            ['name' => 'idDepartement', 'label' => 'Département', 'type' => 'number'],
            ['name' => 'idPoste', 'label' => 'Poste', 'type' => 'number'],
        ],
    ]) ?>
</section>

<section class="card">
    <div id="list" class="state">Chargement…</div>
</section>
