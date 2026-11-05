const propositionsList = document.querySelector('#list');
const propositionsFilters = document.querySelector('#filters');
const propositionsState = {
    tri: 'id_proposition',
    ordre: 'desc',
};

async function loadPropositions() {
    propositionsList.innerHTML = ui.state('Chargement…');

    try {
        const parameters = new URLSearchParams(new FormData(propositionsFilters));
        [...parameters].forEach(([key, value]) => { if (!value) parameters.delete(key); });
        parameters.set('tri', propositionsState.tri);
        parameters.set('ordre', propositionsState.ordre);

        const rows = await api.get(`/api/propositions?${parameters}`);
        Table.render(propositionsList, {
            rows,
            sort: { key: propositionsState.tri, order: propositionsState.ordre },
            empty: 'Aucune proposition trouvée.',
            columns: [
                {
                    key: 'candidat',
                    label: 'Candidat',
                    render: row => ui.escape([row.prenom, row.nom].filter(Boolean).join(' ') || '—'),
                },
                { key: 'offre', label: 'Offre', render: row => ui.escape(row.offre || '—') },
                { key: 'remuneration', label: 'Rémunération', sort: false, render: row => ui.money(row.remuneration_proposee) },
                { key: 'date_embauche', label: 'Embauche prévue', render: row => ui.date(row.date_embauche_proposee) },
                { key: 'statut', label: 'Statut', render: row => ui.badge(row.statut_designation || row.reponse || '') },
                {
                    key: 'action',
                    label: 'Action',
                    sort: false,
                    render: row => `<a href="/selection/proposition?id=${encodeURIComponent(row.id_proposition)}">Ouvrir</a>`,
                },
            ],
            onSort(key, order) {
                propositionsState.tri = key;
                propositionsState.ordre = order;
                loadPropositions();
            },
        });
    } catch (error) {
        propositionsList.innerHTML = ui.state(error.message, true);
    }
}

propositionsFilters.addEventListener('submit', event => {
    event.preventDefault();
    loadPropositions();
});

document.querySelector('#refresh')?.addEventListener('click', loadPropositions);

loadPropositions();
