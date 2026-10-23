<?= view('partials/page-heading', ['heading' => 'Avenant / Renouvellement']) ?>

<section class="card">
    <form id="amendment-form" class="form-grid">
        <label>Type
            <select name="type" required>
                <option value="avenant">Avenant</option>
                <option value="renouvellement">Renouvellement CDD</option>
            </select>
        </label>
        <label>Nouveau poste <input name="idPoste" type="number"></label>
        <label>Nouvelle rémunération <input name="salaireBase" type="number" min="0" step="0.01"></label>
        <label>Nouvelle date de fin <input name="nouvelleDateFin" type="date"></label>
        <?= view('partials/form-actions', ['cancelUrl' => '/personnel/contrats']) ?>
    </form>
</section>
