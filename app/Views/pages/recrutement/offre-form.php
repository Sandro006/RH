<div id="heading" class="page-heading">
    <h1>Publier une offre</h1>
    <p class="muted">Complétez la fiche de l'offre puis publiez-la (statut « Brouillon » uniquement).</p>
</div>

<div id="message" hidden></div>

<section class="card" style="max-width:900px">
    <form id="offre-form" class="form-grid insertion-form">
        <label>Titre <input name="titre" required maxlength="200"></label>
        <label>Date de clôture <input name="date_cloture" type="date"></label>
        <fieldset class="field-wide">
            <legend>Canaux de diffusion</legend>
            <div id="canaux" class="checkbox-grid">Chargement…</div>
        </fieldset>
        <label class="field-wide">Description <textarea name="description" placeholder="Contexte, missions, profil recherché…"></textarea></label>
        <div class="actions field-wide">
            <a class="button button-secondary" href="#" id="offre-cancel">Retour à l'offre</a>
            <button class="button button-secondary" type="submit">Enregistrer le brouillon</button>
            <button class="button button-primary" type="button" id="offre-publish">Publier l'offre</button>
        </div>
    </form>
</section>
