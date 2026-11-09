<?= view('partials/page-heading', [
    'heading' => 'Candidatures',
    'actions' => view('partials/refresh-button'),
]) ?>

<section class="card">
    <p class="muted">Ouvrez une candidature pour consulter ses évaluations ou ajouter une note.</p>
    <div id="list" class="state">Chargement…</div>
</section>

<section id="statut-card" class="card hidden">
    <h2>Changer le statut</h2>
    <div id="statut-message" hidden></div>
    <form id="statut-form">
        <p id="statut-candidat" class="muted"></p>
        <div class="form-grid">
            <label>Nouveau statut<select id="statut-cible" name="statut" required></select></label>
            <label class="field-wide">Commentaire<textarea id="statut-commentaire" name="commentaire" maxlength="3000" placeholder="Motif du refus, date d’entretien…"></textarea></label>
        </div>
        <div class="actions">
            <button class="button button-secondary" type="button" id="statut-annuler">Annuler</button>
            <button class="button button-primary" type="submit">Enregistrer</button>
        </div>
    </form>
</section>
