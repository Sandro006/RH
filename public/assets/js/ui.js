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

        // Statuts Onboarding et Tâche spécifiques
        if (/à démarrer|à faire/.test(normalized)) tone = '';
        else if (/en cours/.test(normalized)) tone = 'primary';
        else if (/en attente|bloquée/.test(normalized)) tone = 'warning';
        else if (/terminé|terminée/.test(normalized)) tone = 'success';
        else if (/annulé|annulée/.test(normalized)) tone = 'danger';
        // Statuts généraux
        else if (/valid|retenue|actif|sign/.test(normalized)) tone = 'success';
        else if (/refus|retir|résili|parti/.test(normalized)) tone = 'danger';
        else if (/contrôle|validation|consultation|corriger|suspend|entretien|évaluation/.test(normalized)) tone = 'warning';

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
        return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(Number(value))} AR`;
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
