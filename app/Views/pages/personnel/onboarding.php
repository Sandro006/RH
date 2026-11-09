<div id="heading" class="page-heading">
    <h1>Fiche onboarding</h1>
    <button id="edit-onboarding" class="button-primary" type="button">Modifier</button>
</div>

<section id="summary" class="card">Chargement…</section>

<?= view('partials/tabs', [
    'active' => 'checklist',
    'tabs'   => [
        'checklist'  => 'Checklist',
        'documents'  => 'Documents',
        'historique' => 'Historique',
    ],
]) ?>

<section id="tab-content" class="card">
    <div id="checklist" class="tab-panel">
        <div class="actions">
            <button id="add-task" class="button-secondary" type="button">Ajouter une tâche</button>
            <button id="close-onboarding" class="button-primary" type="button">Clôturer</button>
            <button id="cancel-onboarding" class="button-danger" type="button">Annuler</button>
        </div>
        <div id="tasks"></div>
    </div>
    <div id="documents" class="tab-panel hidden"></div>
    <div id="historique" class="tab-panel hidden"></div>
</section>
