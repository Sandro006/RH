<div id="heading" class="page-heading">
    <h1>Fiche salarié</h1>
</div>

<?= view('partials/tabs', [
    'active' => 'identity',
    'tabs'   => [
        'identity'     => 'Identité',
        'affectations' => 'Affectations',
        'contrats'     => 'Contrats',
        'documents'    => 'Documents',
        'historique'   => 'Historique',
    ],
]) ?>

<section id="content" class="card">Chargement…</section>
