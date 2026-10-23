<?= view('partials/page-heading', [
    'heading'  => "Nouvelle proposition d'embauche",
    'subtitle' => 'La proposition ne peut être créée que pour une candidature Retenue.',
]) ?>

<section class="card">
    <form id="proposition-form" class="form-grid">
        <label class="field-wide">Candidature retenue
            <select name="idCandidature" required>
                <option value="">Chargement…</option>
            </select>
        </label>
        <label>Rémunération proposée (€)
            <input name="remunerationProposee" type="number" min="0" step="0.01" required>
        </label>
        <label>Date d'embauche proposée
            <input name="dateEmbaucheProposee" type="date">
        </label>
        <p class="field-wide">La rémunération doit rester dans la fourchette de la grille salariale du poste.</p>
        <?= view('partials/form-actions', ['cancelUrl' => '/selection/propositions', 'submitLabel' => 'Créer la proposition']) ?>
    </form>
</section>
