const applicationForm = document.querySelector('#application-form');
const applicationOffer = document.querySelector('#application-offer');
const applicationError = document.querySelector('#application-error');
const applicationSuccess = document.querySelector('#application-success');
const offerId = applicationForm?.dataset.offerId;

const showError = message => { applicationError.textContent = message; applicationError.classList.remove('hidden'); };
const escapeOffer = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);

if (!/^\d+$/.test(offerId || '')) {
    applicationForm.classList.add('hidden');
    showError('Cette offre est introuvable.');
} else {
    fetch(`/api/public/offres/${offerId}`, { headers: { Accept: 'application/json' } })
        .then(response => response.ok ? response.json() : Promise.reject())
        .then(body => {
            const offer = body.data ?? body;
            applicationOffer.innerHTML = `<strong>${escapeOffer(offer.titre)}</strong><br><span>${escapeOffer(offer.description || '')}</span>`;
        })
        .catch(() => { applicationForm.classList.add('hidden'); showError('Cette offre n’accepte plus de candidatures.'); });

    applicationForm.addEventListener('submit', async event => {
        event.preventDefault();
        applicationError.classList.add('hidden');
        const button = applicationForm.querySelector('button[type=submit]');
        button.disabled = true;
        try {
            const response = await fetch(`/api/public/offres/${offerId}/candidatures`, { method: 'POST', headers: { Accept: 'application/json' }, body: new FormData(applicationForm) });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(body.error || body.message || 'Impossible d’envoyer votre candidature.');
            applicationForm.classList.add('hidden');
            applicationSuccess.textContent = 'Votre candidature a bien été envoyée. Nous vous remercions pour votre intérêt.';
            applicationSuccess.classList.remove('hidden');
        } catch (error) {
            showError(error.message);
            button.disabled = false;
        }
    });
}
