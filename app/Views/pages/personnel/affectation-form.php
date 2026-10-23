<?= view('partials/page-heading', ['heading' => 'Affecter à un poste']) ?>

<section class="card">
    <form id="assignment-form" class="form-grid">
        <label>Poste <input name="idPoste" type="number" required></label>
        <label>Date de début <input name="dateDebut" type="date" required></label>
        <label class="field-wide">Motif <textarea name="motif" placeholder="Nomination, mobilité…"></textarea></label>
        <?= view('partials/form-actions', ['cancelUrl' => '/personnel/salaries', 'submitLabel' => 'Affecter']) ?>
    </form>
</section>
