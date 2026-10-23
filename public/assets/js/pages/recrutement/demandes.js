const demandesState = {
    tri: 'id_demande',
    ordre: 'desc',
};

const demandesList = document.querySelector('#list');
const demandesFilters = document.querySelector('#filters');

function appliquerFiltreDepuisUrl() {
    const parameters = new URLSearchParams(location.search);
    ['statut', 'id_poste', 'id_demandeur'].forEach(nom => {
        const field = demandesFilters?.elements[nom];
        if (field && parameters.get(nom)) field.value = parameters.get(nom);
    });
}

async function chargerDemandes() {
    demandesList.innerHTML = ui.state('Chargement…');

    try {
        const parameters = new URLSearchParams(new FormData(demandesFilters));
        [...parameters].forEach(([key, value]) => { if (!value) parameters.delete(key); });
        parameters.set('tri', demandesState.tri);
        parameters.set('ordre', demandesState.ordre);

        const rows = await api.get(`/api/demandes?${parameters}`);
        Table.render(demandesList, {
            rows,
            sort: { key: demandesState.tri, order: demandesState.ordre },
            empty: 'Aucune demande trouvée.',
            columns: [
                { key: 'id_demande', label: 'N°' },
                { key: 'poste', label: 'Poste', render: row => ui.escape(row.intitule || row.id_poste || '—') },
                { key: 'motif', label: 'Motif', render: row => ui.escape(row.motif || '—') },
                {
                    key: 'remuneration',
                    label: 'Rémunération',
                    sort: false,
                    render: row => `${ui.money(row.remuneration_min)} – ${ui.money(row.remuneration_max)}`,
                },
                { key: 'statut', label: 'Statut', render: row => ui.status(row.designation_statut || row.statut || '—') },
                {
                    key: 'action',
                    label: '',
                    sort: false,
                    render: row => `<a href="/recrutement/demande?id=${encodeURIComponent(row.id_demande)}">Voir</a>`,
                },
            ],
            onSort(key, order) {
                demandesState.tri = key;
                demandesState.ordre = order;
                chargerDemandes();
            },
        });
    } catch (error) {
        demandesList.innerHTML = ui.state(error.message, true);
    }
}

demandesFilters?.addEventListener('submit', event => {
    event.preventDefault();
    chargerDemandes();
});

document.querySelector('#refresh')?.addEventListener('click', chargerDemandes);

appliquerFiltreDepuisUrl();
chargerDemandes();
