const loginForm = document.querySelector('#login-form');
const loginError = document.querySelector('#error');
const passwordToggle = document.querySelector('#toggle');

passwordToggle?.addEventListener('click', () => {
    const password = document.querySelector('#password');
    const visible = password.type === 'password';
    password.type = visible ? 'text' : 'password';
    passwordToggle.textContent = visible ? 'Masquer' : 'Afficher';
    passwordToggle.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
});

loginForm?.addEventListener('submit', async event => {
    event.preventDefault();
    loginError.hidden = true;

    const button = loginForm.querySelector('button[type=submit]');
    button.disabled = true;

    try {
        const result = await api.post('/api/auth/login', Object.fromEntries(new FormData(loginForm)));
        if (result.avertissement_pointage) sessionStorage.setItem('avertissement_pointage', result.avertissement_pointage);
        else sessionStorage.removeItem('avertissement_pointage');
        sessionStorage.setItem('user', JSON.stringify(result.utilisateur || result));
        location.href = '/dashboard';
    } catch (error) {
        loginError.textContent = error.code === 'COMPTE_DESACTIVE'
            ? 'Ce compte est désactivé'
            : error.code === 'CHAMPS_VIDES'
                ? 'Identifiant et mot de passe obligatoires'
                : error.message || 'Identifiants invalides';
        loginError.hidden = false;
        button.disabled = false;
    }
});
