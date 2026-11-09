const OffreFormId = new URLSearchParams(location.search).get('id');
const offreForm = document.querySelector('#offre-form');
const offreFormMessage = document.querySelector('#message');
const canauxBox = document.querySelector('#canaux');

const canauxOffre = ['Site carrières', 'Site Interne', 'LinkedIn', 'Facebook', 'JobBoard', 'Cabinet', 'Réseaux sociaux', 'Autre'];

let offreEnCours;

function afficherFormMessage(texte, type) {
    offreFormMessage.textContent = texte;
    offreFormMessage.className = type || '';
    offreFormMessage.hidden = false;
}

function rendreCanaux(selection) {
    const choisis = (selection || '').split(',').map(value => value.trim());
    canauxBox.innerHTML = canauxOffre
        .map(canal => `<label class="checkbox"><input type="checkbox" value="${ui.escape(canal)}"${choisis.includes(canal) ? ' checked' : ''}> ${ui.escape(canal)}</label>`)
        .join('');
}

function canauxSelectionnes() {
    return Array.from(canauxBox.querySelectorAll('input[type=checkbox]:checked')).map(input => input.value);
}

function corpsOffre() {
    const body = {
        titre: offreForm.titre.value.trim(),
        description: offreForm.description.value.trim() || null,
        canal_diffusion: canauxSelectionnes().join(',') || null,
        date_cloture: offreForm.date_cloture.value || null,
    };
    return body;
}

async function chargerOffreForm() {
    if (!OffreFormId) {
        afficherFormMessage("Identifiant d'offre manquant.", 'error');
        return;
    }

    try {
        await auth.current();
        const offre = await api.get(`/api/offres/${OffreFormId}`);

        if (!offre || !offre.id_offre) {
            afficherFormMessage('Offre introuvable.', 'error');
            return;
        }

        offreEnCours = offre;
        document.querySelector('#offre-cancel').href = `/recrutement/offre?id=${offre.id_offre}`;

        if (offre.statut_designation !== 'Brouillon') {
            afficherFormMessage("Cette offre n'est plus modifiable : elle n'est plus en brouillon.", 'error');
            offreForm.titre.value = offre.titre || '';
            offreForm.date_cloture.value = (offre.date_cloture || '').slice(0, 10);
            rendreCanaux(offre.canal_diffusion);
            offreForm.description.value = offre.description || '';
            offreForm.querySelectorAll('input, textarea, button[type=submit], #offre-publish').forEach(element => { element.disabled = true; });
            return;
        }

        offreForm.titre.value = offre.titre || '';
        offreForm.date_cloture.value = (offre.date_cloture || '').slice(0, 10);
        rendreCanaux(offre.canal_diffusion);
        offreForm.description.value = offre.description || '';
    } catch (error) {
        afficherFormMessage(error.message, 'error');
    }
}

async function enregistrer() {
    await api.patch(`/api/offres/${OffreFormId}`, corpsOffre());
}

offreForm?.addEventListener('submit', async event => {
    event.preventDefault();
    offreFormMessage.hidden = true;

    const bouton = offreForm.querySelector('button[type=submit]');
    bouton.disabled = true;

    try {
        await enregistrer();
        ui.toast('Brouillon enregistré.');
        location.href = `/recrutement/offre?id=${OffreFormId}`;
    } catch (error) {
        afficherFormMessage(error.message, 'error');
        bouton.disabled = false;
    }
});

document.querySelector('#offre-publish')?.addEventListener('click', async () => {
    if (!offreForm.reportValidity()) return;
    if (!canauxSelectionnes().length) {
        afficherFormMessage('Sélectionnez au moins un canal de diffusion.', 'error');
        return;
    }

    const bouton = document.querySelector('#offre-publish');
    bouton.disabled = true;
    offreFormMessage.hidden = true;

    try {
        const body = corpsOffre();
        await enregistrer();
        await api.post(`/api/offres/${OffreFormId}/publier`, {
            canal_diffusion: body.canal_diffusion,
            date_cloture: body.date_cloture,
        });
        ui.toast("Offre publiée.");
        location.href = `/recrutement/offre?id=${OffreFormId}`;
    } catch (error) {
        afficherFormMessage(error.message, 'error');
        bouton.disabled = false;
    }
});

chargerOffreForm();
