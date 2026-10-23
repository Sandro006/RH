<?= view('partials/page-heading', [
    'heading' => 'Planifier un entretien',
    'subtitle' => 'Les candidats proposés sont ceux dont la candidature est au statut « Entretien prévu ».',
]) ?>

<div id="entretien-message" hidden></div>

<section class="card" style="max-width:900px">
    <form id="entretien-form">
        <div class="form-grid">
            <label>Candidat<select id="entretien-candidat" name="idCandidature" required></select></label>
            <label>Recruteur<select id="entretien-recruteur" name="idRecruteur" required></select></label>
            <label>Date et heure<input id="entretien-date" name="dateEntretien" type="datetime-local" required></label>
            <label>Type d’entretien<select id="entretien-type" name="typeEntretien"></select></label>
        </div>
        <div class="actions">
            <a class="button button-secondary" href="/selection/entretiens">Retour aux entretiens</a>
            <button class="button button-primary" type="submit">Planifier l’entretien</button>
        </div>
    </form>
</section>
