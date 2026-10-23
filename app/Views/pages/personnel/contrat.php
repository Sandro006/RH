<div id="heading" class="page-heading">
    <h1>Fiche contrat</h1>
</div>

<section id="detail" class="card">Chargement…</section>

<?= view('partials/tabs', [
    'active' => 'detail',
    'tabs'   => [
        'detail'  => 'Détail',
        'history' => 'Historique',
    ],
]) ?>

<section id="tab-content" class="card"></section>
