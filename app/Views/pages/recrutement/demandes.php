<?= view('partials/page-heading', [
    'heading' => 'Demandes de recrutement',
    'actions' => '<a class="button button-primary" href="/recrutement/demande-form">Nouvelle demande</a>' . view('partials/refresh-button'),
]) ?>

<section class="card">
    <?= view('partials/filters', [
        'fields' => [
            ['name' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => [
                '' => 'Tous',
                'En cours' => 'En cours',
                'En contrôle RH' => 'En contrôle RH',
                'En validation budgétaire' => 'En validation budgétaire',
                'En validation hiérarchique' => 'En validation hiérarchique',
                'En consultation interne' => 'En consultation interne',
                'À corriger' => 'À corriger',
                'Validée' => 'Validée',
                'Refusée' => 'Refusée',
                'Annulée' => 'Annulée',
            ]],
            ['name' => 'id_poste', 'label' => 'Poste', 'type' => 'number'],
            ['name' => 'id_demandeur', 'label' => 'Demandeur', 'type' => 'number'],
        ],
    ]) ?>
</section>

<section class="card">
    <div id="list" class="state">Chargement…</div>
</section>
