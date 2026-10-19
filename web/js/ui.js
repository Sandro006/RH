const ui = {
    escape(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
    },
    date(value) {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.valueOf()) ? value : new Intl.DateTimeFormat('fr-FR').format(date);
    },
    money(value) {
        return value == null ? '—' : new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(value);
    },
    badge(value) {
        const text = value || '—';
        const lower = text.toLowerCase();
        const color = /actif|terminée|terminé|signé|en cours/.test(lower) ? 'green' :
            /refus|résili|annul/.test(lower) ? 'red' :
            /bloqu|attente|essai/.test(lower) ? 'orange' :
            /préparation|faire|démarrer|brouillon/.test(lower) ? 'gray' : 'blue';
        return `<span class="badge badge-${color}">${ui.escape(text)}</span>`;
    },
    state(message, error = false) {
        return `<div class="state${error ? ' state-error' : ''}">${ui.escape(message)}</div>`;
    },
    toast(message) {
        const node = document.createElement('div');
        node.className = 'toast';
        node.textContent = message;
        document.body.append(node);
        setTimeout(() => node.remove(), 3500);
    },
    error(error) {
        ui.toast(error.message || 'Une erreur est survenue.');
    }
};
