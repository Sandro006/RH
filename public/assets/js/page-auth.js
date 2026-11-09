// Toutes les pages privées vérifient la session dès leur ouverture.
(async () => {
    try { await auth.current(); } catch (_) { /* auth.js redirige vers la connexion */ }
})();

document.querySelector('#logout')?.addEventListener('click', () => auth.logout());

const referentielsScript = document.createElement('script');
referentielsScript.src = '/assets/js/referentiels-ui.js';
document.head.append(referentielsScript);
