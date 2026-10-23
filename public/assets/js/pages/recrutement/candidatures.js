const candidaturesState = {
    tri: 'id_candidature',
    ordre: 'desc',
};

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
                    render: row => ui.escape([row.prenom, row.nom].filter(Boolean).join(' ') || row.id_candidat),
                },
                { key: 'offre', label: 'Offre', render: row => ui.escape(row.offre_titre || row.id_offre || '—') },
                { key: 'origine', label: 'Origine', render: row => ui.escape(row.origine_candidature || '—') },
                {
                    key: 'statut',
                    label: 'Statut',
                    render: row => ui.status(row.statut_designation || row.statut || '—'),
                },
            ],
            onSort(key, order) {
                candidaturesState.tri = key;
                candidaturesState.ordre = order;
                chargerCandidatures();
            },
        });
    } catch (error) {
        list.innerHTML = ui.state(error.message, true);
    }
}

document.querySelector('#refresh')?.addEventListener('click', chargerCandidatures);
chargerCandidatures();
