const publicOffersBox = document.querySelector('#offres-publiques');

const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
const dateLabel = value => value ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(new Date(`${value}T00:00:00`)) : 'Non précisée';

fetch('/api/public/offres', { headers: { Accept: 'application/json' } })
    .then(response => response.ok ? response.json() : Promise.reject())
    .then(body => {
        const offers = body.data ?? body;
        if (!offers.length) {
            publicOffersBox.textContent = 'Aucune offre n’est disponible pour le moment.';
            return;
        }
        publicOffersBox.classList.remove('state');
        publicOffersBox.innerHTML = offers.map(offer => `<article class="card public-offer"><div><h2>${escapeHtml(offer.titre)}</h2><div class="public-offer-meta">Clôture des candidatures : ${escapeHtml(dateLabel(offer.date_cloture))}</div><p>${escapeHtml(offer.description || 'Description non renseignée.')}</p></div><a class="button button-primary" href="/candidatures/postuler/${encodeURIComponent(offer.id_offre)}">Postuler</a></article>`).join('');
    })
    .catch(() => { publicOffersBox.textContent = 'Les offres ne sont pas disponibles actuellement.'; });
