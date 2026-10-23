// Toutes les pages privées vérifient la session dès leur ouverture.
(async () => {
    try { await auth.current(); } catch (_) { /* auth.js redirige vers la connexion */ }
})();

const referentielsScript = document.createElement('script');
referentielsScript.src = '/web/js/referentiels-ui.js';
document.head.append(referentielsScript);

if (!document.querySelector('.module-nav')) {
    const nav = document.createElement('nav');
    nav.className = 'module-nav';
    nav.innerHTML = '<a href="/web/dashboard.html">Tableau de bord</a><a href="/web/pages/recrutement/demandes.html">Demandes</a><a href="/web/pages/recrutement/offres.html">Offres</a><a href="/web/pages/recrutement/candidatures.html">Candidatures</a><a href="/web/pages/selection/entretiens.html">Entretiens</a><a href="/web/pages/recrutement/propositions.html">Propositions</a><a href="/web/pages/personnel/salaires.html">Salariés</a><a href="/web/pages/personnel/contrats.html">Contrats</a><a href="/web/pages/personnel/onboardings.html">Onboarding</a>';
    nav.style.cssText = 'display:flex;flex-wrap:wrap;gap:.45rem;padding:.6rem 1rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.9rem;position:relative;z-index:2';
    document.body.prepend(nav);
}
