<div id="heading" class="page-heading">
    <h1>Modifier la tâche</h1>
</div>

<section id="form-container" class="card">
    <form id="tache-form">
        <input type="hidden" id="id_tache" name="id_tache">
        <input type="hidden" id="id_onboarding" name="id_onboarding">

        <div class="form-group">
            <label for="intitule">Intitulé</label>
            <input type="text" id="intitule" name="intitule" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3"></textarea>
        </div>

        <div class="form-group">
            <label for="responsable">Responsable</label>
            <input type="text" id="responsable" name="responsable">
        </div>

        <div class="form-group">
            <label for="date_echeance">Date d'échéance</label>
            <input type="date" id="date_echeance" name="date_echeance">
        </div>

        <div class="actions">
            <button type="submit" class="button-primary">Enregistrer</button>
            <button type="button" id="cancel" class="button-secondary">Annuler</button>
        </div>
    </form>
</section>
