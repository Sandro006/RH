// Toutes les pages privées vérifient la session dès leur ouverture.
(async () => {
    try { await auth.current(); } catch (_) { /* auth.js redirige vers la connexion */ }
})();

document.querySelector('#logout')?.addEventListener('click', async () => { try { await auth.logout(); } catch (error) { alert(error.message); } });
const avertissementPointage = sessionStorage.getItem('avertissement_pointage');
if (avertissementPointage) { alert(avertissementPointage); sessionStorage.removeItem('avertissement_pointage'); }

const referentielsScript = document.createElement('script');
referentielsScript.src = '/assets/js/referentiels-ui.js';
document.head.append(referentielsScript);
