<main class="public-main">
    <p><a href="/candidatures">← Retour aux offres</a></p>
    <section class="public-hero">
        <h1>Déposer ma candidature</h1>
        <p>Complétez vos informations et joignez votre CV pour postuler à cette offre.</p>
    </section>
    <div id="application-error" class="error hidden" role="alert"></div>
    <div id="application-success" class="success hidden" role="status"></div>
    <form id="application-form" class="card" enctype="multipart/form-data" data-offer-id="<?= esc((string) $offreId) ?>">
        <div id="application-offer" class="application-summary">Chargement de l’offre…</div>
        <div class="form-grid">
            <label>Nom *<input name="nom" maxlength="150" autocomplete="family-name" required></label>
            <label>Prénom *<input name="prenom" maxlength="150" autocomplete="given-name" required></label>
            <label>Adresse e-mail *<input name="email" type="email" maxlength="200" autocomplete="email" required></label>
            <label>Téléphone *<input name="telephone" maxlength="30" autocomplete="tel" required></label>
            <label class="field-wide">Adresse complète *<textarea name="adresse" autocomplete="street-address" required></textarea></label>
            <label class="field-wide">CV (PDF, DOC ou DOCX, 5 Mo maximum) *<input name="cv" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required></label>
            <label class="field-wide">Lettre de motivation <textarea name="lettre_motivation" maxlength="5000" placeholder="Présentez brièvement votre motivation."></textarea></label>
        </div>
        <p class="muted">Les champs marqués d’un astérisque sont obligatoires.</p>
        <button class="button-primary" type="submit">Envoyer ma candidature</button>
    </form>
</main>
