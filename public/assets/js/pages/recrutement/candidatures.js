const candidaturesState = {
    tri: 'id_candidature',
    ordre: 'desc',
};

const statutCard = document.querySelector('#statut-card');
const statutForm = document.querySelector('#statut-form');
const statutCible = document.querySelector('#statut-cible');
const statutCommentaire = document.querySelector('#statut-commentaire');
const statutCandidat = document.querySelector('#statut-candidat');
const statutMessage = document.querySelector('#statut-message');
const statutValider = statutForm?.querySelector('button[type=submit]');
let candidatureStatut = null;

function statutAlerte(texte, type = 'error') {
    statutMessage.textContent = texte;
    statutMessage.className = type;
    statutMessage.hidden = false;
}

function fermerFormulaireStatut() {
    candidatureStatut = null;
    statutCard.classList.add('hidden');
    statutForm.reset();
    statutMessage.hidden = true;
}

async function ouvrirFormulaireStatut(id) {
    candidatureStatut = id;
    statutCard.classList.remove('hidden');
    statutMessage.hidden = true;
    statutCandidat.textContent = 'Chargement…';
    statutCible.innerHTML = '';
    statutCible.disabled = false;
    statutValider.disabled = false;
    statutCommentaire.value = '';

    try {
        const transitions = await api.get(`/api/candidatures/${id}/transitions`);
        statutCandidat.textContent = `Statut actuel : ${transitions.statut}`;

        if (!transitions.suivants.length) {
            statutCible.innerHTML = '<option value="">Aucune transition possible</option>';
            statutCible.disabled = true;
            statutValider.disabled = true;
        } else {
            statutValider.disabled = false;
            statutCible.innerHTML = transitions.suivants
                .map(statut => `<option value="${ui.escape(statut)}">${ui.escape(statut)}</option>`)
                .join('');
        }

        statutCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
        statutCandidat.textContent = '';
        statutAlerte(error.message);
    }
}

async function chargerCandidatures() {
    const list = document.querySelector('#list');
    list.innerHTML = ui.state('Chargement…');

    try {
        const rows = await api.get(Table.query('/api/candidatures', candidaturesState));
        Table.render(list, {
            rows,
            sort: { key: candidaturesState.tri, order: candidaturesState.ordre },
            empty: 'Aucune candidature disponible.',
            columns: [
                {
                    key: 'candidat',
                    label: 'Candidat',
                    render: row => `<a href="/selection/evaluation?id=${encodeURIComponent(row.id_candidature)}">${ui.escape([row.prenom, row.nom].filter(Boolean).join(' ') || row.id_candidat)}</a>`,
                },
                { key: 'offre', label: 'Offre', render: row => ui.escape(row.offre_titre || row.id_offre || '—') },
                { key: 'origine', label: 'Origine', render: row => ui.escape(row.origine_candidature || '—') },
                {
                    key: 'statut',
                    label: 'Statut',
                    render: row => ui.status(row.statut_designation || row.statut || '—'),
                },
                {
                    key: 'actions',
                    label: 'Actions',
                    sort: false,
                    render: row => `<div class="actions">${row.statut_designation === 'Retenue' ? `<a class="button button-secondary" href="/selection/proposition-form?idCandidature=${encodeURIComponent(row.id_candidature)}">Proposition</a>` : ''}<button class="button button-secondary" type="button" data-statut="${ui.escape(row.id_candidature)}">Changer le statut</button></div>`,
                },
            ],
            onSort(key, order) {
                candidaturesState.tri = key;
                candidaturesState.ordre = order;
                chargerCandidatures();
            },
        });

        list.querySelectorAll('button[data-statut]').forEach(button => {
            button.addEventListener('click', () => ouvrirFormulaireStatut(button.dataset.statut));
        });
    } catch (error) {
        list.innerHTML = ui.state(error.message, true);
    }
}

statutForm?.addEventListener('submit', async event => {
    event.preventDefault();
    if (!candidatureStatut) return;

    statutValider.disabled = true;
    statutMessage.hidden = true;

    try {
        await api.post(`/api/candidatures/${candidatureStatut}/statut`, {
            statut: statutCible.value,
            commentaire: statutCommentaire.value.trim() || null,
        });
        ui.toast('Statut mis à jour.');
        fermerFormulaireStatut();
        chargerCandidatures();
    } catch (error) {
        statutAlerte(error.message);
    } finally {
        statutValider.disabled = false;
    }
});

document.querySelector('#statut-annuler')?.addEventListener('click', fermerFormulaireStatut);
document.querySelector('#refresh')?.addEventListener('click', chargerCandidatures);
chargerCandidatures();
