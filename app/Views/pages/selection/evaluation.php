<?= view('partials/page-heading', ['heading' => 'Évaluation d’une candidature']) ?>

<div id="evaluation-message" hidden></div>
<section id="evaluation-candidat" class="card state">Chargement de la candidature…</section>

<section id="evaluation-form-section" class="card hidden">
    <h2>Ajouter une évaluation</h2>
    <p class="muted">Chaque critère est noté sur 5. La note globale est calculée automatiquement.</p>
    <form id="evaluation-form">
        <div class="form-grid">
            <label>Compétences techniques<input name="technique" type="number" min="0" max="5" step="0.1" required></label>
            <label>Expérience<input name="experience" type="number" min="0" max="5" step="0.1" required></label>
            <label>Communication<input name="communication" type="number" min="0" max="5" step="0.1" required></label>
            <label>Motivation<input name="motivation" type="number" min="0" max="5" step="0.1" required></label>
            <label>Adéquation au poste<input name="adequation" type="number" min="0" max="5" step="0.1" required></label>
            <label>Note globale<input id="evaluation-note" type="number" min="0" max="5" step="0.01" readonly></label>
            <label class="field-wide">Commentaire<textarea name="commentaire" maxlength="3000" placeholder="Observations complémentaires"></textarea></label>
        </div>
        <div class="actions"><button class="button button-primary" type="submit">Enregistrer l’évaluation</button></div>
    </form>
</section>

<section class="card">
    <h2>Synthèse</h2>
    <div id="evaluation-synthese" class="state">Chargement…</div>
</section>
