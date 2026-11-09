<?= view('partials/page-heading', [
    'heading'  => "Propositions d'embauche",
    'subtitle' => 'Suivez les propositions, les réponses et la négociation.',
    'actions'  => view('partials/refresh-button') . '<a class="button button-primary" href="/selection/proposition-form">Nouvelle proposition</a>',
]) ?>

<section class="card">
    <?= view('partials/filters', [
        'fields' => [
            ['name' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => [
                ''               => 'Tous',
                'À envoyer'      => 'À envoyer',
                'Envoyée'        => 'Envoyée',
                'En négociation' => 'En négociation',
                'Acceptée'       => 'Acceptée',
                'Refusée'        => 'Refusée',
                'Expirée'        => 'Expirée',
            ]],
        ],
    ]) ?>
</section>

<section class="card">
    <div id="list" class="state">Chargement…</div>
</section>
