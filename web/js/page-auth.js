// Toutes les pages privées vérifient la session dès leur ouverture.
(async () => {
    try { await auth.current(); } catch (_) { /* auth.js redirige vers la connexion */ }
})();

const referentielsScript = document.createElement('script');
referentielsScript.src = '/web/js/referentiels-ui.js';
document.head.append(referentielsScript);
