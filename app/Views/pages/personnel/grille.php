<?= view('partials/page-heading', [
    'heading'  => 'Grille salariale',
    'subtitle' => 'Fourchettes de rémunération par poste et par rang.',
    'actions'  => view('partials/refresh-button'),
]) ?>

<section class="card">
    <form id="filters" class="filters">
        <label>Département
            <select name="idDepartement"><option value="">Tous les départements</option></select>
        </label>
        <label>Poste
            <select name="idPoste"><option value="">Tous les postes</option></select>
        </label>
        <label>État
            <select name="actif">
                <option value="">Toutes</option>
                <option value="1">Actives</option>
                <option value="0">Historisées</option>
            </select>
        </label>
        <button class="button-primary" type="submit">Rechercher</button>
    </form>
</section>

<section class="card">
    <div id="grille-container" class="grille-container">
        <div id="list" class="state">Chargement…</div>
    </div>
</section>
