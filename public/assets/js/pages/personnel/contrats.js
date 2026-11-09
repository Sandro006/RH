const contractList = document.querySelector('#list');
const contractFilters = document.querySelector('#filters');
const contractState = {
    tri: 'date_debut',
    ordre: 'desc',
};

async function loadContracts() {
    contractList.innerHTML = ui.state('Chargement…');

    try {
        const parameters = new URLSearchParams(new FormData(contractFilters));
        [...parameters].forEach(([key, value]) => { if (!value) parameters.delete(key); });
        parameters.set('tri', contractState.tri);
        parameters.set('ordre', contractState.ordre);

        const rows = await api.get(`/api/contrats?${parameters}`);
        Table.render(contractList, {
            rows,
            sort: { key: contractState.tri, order: contractState.ordre },
            empty: 'Aucun contrat trouvé.',
            columns: [
                { key: 'id_contrat', label: 'N°' },
                {
                    key: 'id_salarie',
                    label: 'Salarié',
                    render: row => ui.escape([row.salarie_prenom, row.salarie_nom].filter(Boolean).join(' ') || row.id_salarie),
                },
                {
                    key: 'type_contrat_id',
                    label: 'Type',
                    render: row => ui.escape(row.type_contrat_designation || row.type_contrat_id),
                },
                { key: 'date_debut', label: 'Du', render: row => ui.date(row.date_debut) },
                { key: 'date_fin', label: 'Au', render: row => ui.date(row.date_fin) },
                { key: 'essai', label: 'Essai', sort: false, render: row => row.periode_essai_fin ? 'Oui' : 'Non' },
                {
                    key: 'statut',
                    label: 'Statut',
                    render: row => ui.badge(row.statut_designation || row.statut),
                },
                {
                    key: 'action',
                    label: '',
                    sort: false,
                    render: row => `<a href="/personnel/contrat?id=${encodeURIComponent(row.id_contrat)}">Voir</a>`,
                },
            ],
            onSort(key, order) {
                contractState.tri = key;
                contractState.ordre = order;
                loadContracts();
            },
        });
    } catch (error) {
        contractList.innerHTML = ui.state(error.message, true);
    }
}

contractFilters.addEventListener('submit', event => {
    event.preventDefault();
    loadContracts();
});

loadContracts();
