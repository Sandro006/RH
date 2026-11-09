const publicOffersBox = document.querySelector('#offres-publiques');
const publicOffersSearch = document.querySelector('#recherche-offres');

const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
const dateLabel = value => value ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(new Date(`${value}T00:00:00`)) : 'Non précisée';
const searchableText = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

let publicOffers = [];

const renderOffers = offers => {
    publicOffersBox.classList.remove('state');
    if (!offers.length) {
        publicOffersBox.textContent = publicOffers.length
            ? 'Aucune offre ne correspond à votre recherche.'
            : 'Aucune offre n’est disponible pour le moment.';
        return;
    }
    publicOffersBox.innerHTML = offers.map(offer => `<article class="card public-offer"><div><h2>${escapeHtml(offer.titre)}</h2><div class="public-offer-meta">Clôture des candidatures : ${escapeHtml(dateLabel(offer.date_cloture))}</div><p>${escapeHtml(offer.description || 'Description non renseignée.')}</p></div><a class="button button-primary" href="/candidatures/postuler/${encodeURIComponent(offer.id_offre)}">Postuler</a></article>`).join('');
};

publicOffersSearch?.addEventListener('input', () => {
    const query = searchableText(publicOffersSearch.value.trim());
    renderOffers(publicOffers.filter(offer => searchableText(`${offer.titre} ${offer.description}`).includes(query)));
});

fetch('/api/public/offres', { headers: { Accept: 'application/json' } })
    .then(response => response.ok ? response.json() : Promise.reject())
    .then(body => {
        publicOffers = body.data ?? body;
        renderOffers(publicOffers);
    })
    .catch(() => { publicOffersBox.textContent = 'Les offres ne sont pas disponibles actuellement.'; });
