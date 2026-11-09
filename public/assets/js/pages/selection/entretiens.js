const entretiensList = document.querySelector('#list');
const entretiensFilters = document.querySelector('#filters');
const entretiensState = {
    tri: 'date_entretien',
    ordre: 'asc',
};

function nomCandidat(row) {
    return [row.prenom_candidat, row.nom_candidat].filter(Boolean).join(' ') || '—';
}

async function chargerEntretiens() {
    entretiensList.innerHTML = ui.state('Chargement…');

    try {
        const parameters = new URLSearchParams(new FormData(entretiensFilters));
        [...parameters].forEach(([key, value]) => { if (!value) parameters.delete(key); });
        parameters.set('tri', entretiensState.tri);
        parameters.set('ordre', entretiensState.ordre);

        const rows = await api.get(`/api/entretiens?${parameters}`);
        Table.render(entretiensList, {
            rows,
            sort: { key: entretiensState.tri, order: entretiensState.ordre },
            empty: 'Aucun entretien trouvé.',
            columns: [
                { key: 'id_entretien', label: 'N°' },
                { key: 'candidat', label: 'Candidat', render: row => ui.escape(nomCandidat(row)) },
                { key: 'offre', label: 'Offre', render: row => ui.escape(row.offre || '—') },
                { key: 'date_entretien', label: 'Date', render: row => ui.date(row.date_entretien) },
                { key: 'type', label: 'Type', render: row => ui.escape(row.type_entretien_designation || '—') },
                { key: 'recruteur', label: 'Recruteur', render: row => ui.escape(row.recruteur || '—') },
                {
                    key: 'statut',
                    label: 'Statut',
                    render: row => ui.badge(row.statut_designation || row.statut),
                },
            ],
            onSort(key, order) {
                entretiensState.tri = key;
                entretiensState.ordre = order;
                chargerEntretiens();
            },
        });
    } catch (error) {
        entretiensList.innerHTML = ui.state(error.message, true);
    }
}

entretiensFilters?.addEventListener('submit', event => {
    event.preventDefault();
    chargerEntretiens();
});

document.querySelector('#refresh')?.addEventListener('click', chargerEntretiens);
chargerEntretiens();
