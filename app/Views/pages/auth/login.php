<main class="login-shell">
    <section class="login-intro">
        <span class="brand-mark">RH</span>
        <h1>Les talents font la différence.</h1>
        <p>Un espace unique pour piloter vos recrutements, vos équipes et l'intégration de chaque collaborateur.</p>
    </section>
    <section class="login-form">
        <h2>Bienvenue</h2>
        <p class="muted">Connectez-vous à votre espace professionnel.</p>
        <div id="error" class="login-error" hidden></div>
        <form id="login-form">
            <label>Identifiant
                <input name="identifiant" autocomplete="username" required autofocus>
            </label>
            <label class="password">Mot de passe
                <input id="password" name="motDePasse" type="password" autocomplete="current-password" required>
                <button type="button" id="toggle" aria-label="Afficher le mot de passe">Afficher</button>
            </label>
            <button class="button-primary" type="submit">Se connecter</button>
        </form>
    </section>
</main>
