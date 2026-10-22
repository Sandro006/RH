const ui = {
    escape(value = '') { return String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character])); },
    status(value = '') {
        const label = String(value), status = label.toLowerCase(); let tone = '';
        if (/valid|retenue|actif|termin|sign/.test(status)) tone = 'success';
        else if (/refus|annul|retir|résili|parti/.test(status)) tone = 'danger';
        else if (/contrôle|validation|consultation|corriger|suspend|entretien|évaluation|cours|attente/.test(status)) tone = 'warning';
        return `<span class="badge ${tone}">${ui.escape(label)}</span>`;
    },
    date(value) { return value ? new Intl.DateTimeFormat('fr-FR').format(new Date(value)) : '—'; },
    money(value) { return value == null ? '—' : new Intl.NumberFormat('fr-FR').format(value); },
    state(message, error = false) { return `<div class="state ${error ? 'state-error' : ''}">${ui.escape(message)}</div>`; },
    toast(message) { const element = document.createElement('div'); element.className = 'toast'; element.textContent = message; document.body.append(element); setTimeout(() => element.remove(), 3200); },
    error(error) { const element = document.querySelector('[data-error]'); if (element) { element.textContent = error.message || error; element.hidden = false; } }
};
