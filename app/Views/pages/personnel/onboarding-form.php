<div id="heading" class="page-heading">
    <h1>Modifier l'onboarding</h1>
</div>

<section id="form-container" class="card">
    <form id="onboarding-form">
        <input type="hidden" id="id_onboarding" name="id_onboarding">

        <div class="form-group">
            <label for="date_debut">Date de début</label>
            <input type="date" id="date_debut" name="date_debut" required>
        </div>

        <div class="form-group">
            <label for="date_fin">Date de fin</label>
            <input type="date" id="date_fin" name="date_fin">
        </div>

        <div class="actions">
            <button type="submit" class="button-primary">Enregistrer</button>
            <button type="button" id="cancel" class="button-secondary">Annuler</button>
        </div>
    </form>
</section>
