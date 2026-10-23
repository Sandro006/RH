const offresState = {
    tri: 'id_offre',
    ordre: 'desc',
};

async function chargerOffres() {
    const list = document.querySelector('#list');
    list.innerHTML = ui.state('Chargement…');

    try {
        const rows = await api.get(Table.query('/api/offres', offresState));
        Table.render(list, {
            rows,
            sort: { key: offresState.tri, order: offresState.ordre },
            empty: 'Aucune offre disponible.',
            columns: [
                { key: 'id_offre', label: 'N°' },
                { key: 'titre', label: 'Titre', render: row => ui.escape(row.titre || '—') },
                { key: 'canal', label: 'Canal', render: row => ui.escape(row.canal_diffusion || '—') },
                { key: 'publication', label: 'Publication', render: row => ui.date(row.date_publication) },
                {
                    key: 'statut',
                    label: 'Statut',
                    render: row => ui.status(row.statut_designation || row.statut || '—'),
                },
            ],
            onSort(key, order) {
                offresState.tri = key;
                offresState.ordre = order;
                chargerOffres();
            },
        });
    } catch (error) {
        list.innerHTML = ui.state(error.message, true);
    }
}

document.querySelector('#refresh')?.addEventListener('click', chargerOffres);
chargerOffres();
