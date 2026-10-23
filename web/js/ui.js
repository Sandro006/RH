const ui = {
    escape(value = '') {
        return String(value).replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[character]));
    },
    status(value = '') {
        const label = String(value || '—');
        const normalized = label.toLocaleLowerCase('fr-FR');
        let tone = '';
        if (/valid|retenue|actif|termin|sign/.test(normalized)) tone = 'success';
        else if (/refus|annul|retir|résili|parti/.test(normalized)) tone = 'danger';
        else if (/contrôle|validation|consultation|corriger|suspend|entretien|évaluation|cours|attente/.test(normalized)) tone = 'warning';
        return `<span class="badge ${tone}">${ui.escape(label)}</span>`;
    },
    badge(value = '') { return ui.status(value); },
    date(value) {
        if (!value) return '—';
        const raw = String(value);
        const date = /^\d{4}-\d{2}-\d{2}$/.test(raw) ? new Date(`${raw}T12:00:00`) : new Date(raw);
        return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('fr-FR').format(date);
    },
    money(value) {
        if (value == null || value === '') return '—';
        return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(Number(value))} €`;
    },
    state(message, error = false) {
        return `<div class="state ${error ? 'state-error' : ''}">${ui.escape(message)}</div>`;
    },
    toast(message) {
        const element = document.createElement('div');
        element.className = 'toast';
        element.textContent = message;
        document.body.append(element);
        setTimeout(() => element.remove(), 3200);
    },
    error(error) {
        const message = error?.message || String(error);
        const element = document.querySelector('[data-error]');
        if (element) {
            element.textContent = message;
            element.hidden = false;
            return;
        }
        ui.toast(message);
    }
};

// Navigation commune des écrans applicatifs.
(() => {
    if (location.pathname.endsWith('/login.html') || document.querySelector('.module-nav')) return;
    const nav = document.createElement('nav');
    nav.className = 'module-nav';
    nav.innerHTML = '<a href="/web/dashboard.html">Tableau de bord</a><a href="/web/pages/recrutement/demandes.html">Demandes</a><a href="/web/pages/recrutement/offres.html">Offres</a><a href="/web/pages/recrutement/candidatures.html">Candidatures</a><a href="/web/pages/selection/entretiens.html">Entretiens</a><a href="/web/pages/recrutement/propositions.html">Propositions</a><a href="/web/pages/personnel/salaires.html">Salariés</a><a href="/web/pages/personnel/contrats.html">Contrats</a><a href="/web/pages/personnel/onboardings.html">Onboarding</a>';
    nav.style.cssText = 'display:flex;flex-wrap:wrap;gap:.45rem;padding:.6rem 1rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.9rem;position:relative;z-index:2';
    nav.querySelectorAll('a').forEach(link => { link.style.cssText = 'padding:.35rem .55rem;color:#172d51;text-decoration:none;border-radius:6px'; });
    document.body.prepend(nav);
})();
