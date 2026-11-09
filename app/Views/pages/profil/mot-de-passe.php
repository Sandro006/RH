<section class="card" style="max-width:650px">
    <h1>Changer mon mot de passe</h1>
    <p class="muted">Utilisez un mot de passe d'au moins 12 caractères et trois catégories de caractères.</p>
    <div id="message" hidden></div>
    <form id="form" class="form-grid">
        <label>Ancien mot de passe <input name="ancienMotDePasse" type="password" required></label>
        <label>Nouveau mot de passe <input name="nouveauMotDePasse" type="password" minlength="12" required></label>
        <label>Confirmation <input name="confirmation" type="password" minlength="12" required></label>
        <?= view('partials/form-actions', [
            'cancelUrl'   => '/dashboard',
            'submitLabel' => 'Enregistrer',
        ]) ?>
    </form>
</section>
