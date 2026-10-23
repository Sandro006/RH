const salariesList = document.querySelector('#list');
const salariesFilters = document.querySelector('#filters');
const salariesState = {
    tri: 'matricule',
    ordre: 'asc',
};

async function loadSalaries() {
    salariesList.innerHTML = ui.state('Chargement…');

    try {
        const parameters = new URLSearchParams(new FormData(salariesFilters));
        [...parameters].forEach(([key, value]) => { if (!value) parameters.delete(key); });
        parameters.set('tri', salariesState.tri);
        parameters.set('ordre', salariesState.ordre);

        const rows = await api.get(`/api/salaries?${parameters}`);
        Table.render(salariesList, {
            rows,
            sort: { key: salariesState.tri, order: salariesState.ordre },
            empty: 'Aucun salarié trouvé.',
            columns: [
                { key: 'matricule', label: 'Matricule', render: row => ui.escape(row.matricule || '—') },
                {
                    key: 'nom',
                    label: 'Nom',
                    render: row => ui.escape(`${row.prenom || ''} ${row.nom || ''}`.trim() || '—'),
                },
                { key: 'poste', label: 'Poste', render: row => ui.escape(row.poste || row.intitule || '—') },
                { key: 'date_embauche', label: 'Embauché le', render: row => ui.date(row.date_embauche) },
                {
                    key: 'statut',
                    label: 'Statut',
                    render: row => ui.badge(row.statut_designation || row.statut),
                },
                {
                    key: 'action',
                    label: 'Action',
                    sort: false,
                    render: row => `<a href="/personnel/salarie?id=${encodeURIComponent(row.id_salarie)}">Voir</a>`,
                },
            ],
            onSort(key, order) {
                salariesState.tri = key;
                salariesState.ordre = order;
                loadSalaries();
            },
        });
    } catch (error) {
        salariesList.innerHTML = ui.state(error.message, true);
    }
}

salariesFilters.addEventListener('submit', event => {
    event.preventDefault();
    loadSalaries();
});

loadSalaries();
