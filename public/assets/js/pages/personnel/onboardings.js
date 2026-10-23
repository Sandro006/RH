const onboardingList = document.querySelector('#list');
const onboardingFilters = document.querySelector('#filters');
const onboardingState = {
    tri: 'date_debut',
    ordre: 'desc',
};

async function loadOnboardings() {
    onboardingList.innerHTML = ui.state('Chargement…');

    try {
        const parameters = new URLSearchParams(new FormData(onboardingFilters));
        [...parameters].forEach(([key, value]) => { if (!value) parameters.delete(key); });
        parameters.set('tri', onboardingState.tri);
        parameters.set('ordre', onboardingState.ordre);

        const rows = await api.get(`/api/onboardings?${parameters}`);
        Table.render(onboardingList, {
            rows,
            sort: { key: onboardingState.tri, order: onboardingState.ordre },
            empty: 'Aucun onboarding trouvé.',
            columns: [
                { key: 'id_onboarding', label: 'N°' },
                {
                    key: 'id_salarie',
                    label: 'Salarié',
                    render: row => ui.escape([row.salarie_prenom, row.salarie_nom].filter(Boolean).join(' ') || row.id_salarie),
                },
                { key: 'date_debut', label: 'Début', render: row => ui.date(row.date_debut) },
                {
                    key: 'progression',
                    label: 'Progression',
                    sort: false,
                    render: row => {
                        const progress = Number(row.progression || 0);
                        return `${progress}%<div class="progress"><span style="width:${Math.min(progress, 100)}%"></span></div>`;
                    },
                },
                {
                    key: 'statut',
                    label: 'Statut',
                    render: row => ui.badge(row.statut_designation || row.statut),
                },
                {
                    key: 'action',
                    label: '',
                    sort: false,
                    render: row => `<a href="/personnel/onboarding?id=${encodeURIComponent(row.id_onboarding)}">Voir</a>`,
                },
            ],
            onSort(key, order) {
                onboardingState.tri = key;
                onboardingState.ordre = order;
                loadOnboardings();
            },
        });
    } catch (error) {
        onboardingList.innerHTML = ui.state(error.message, true);
    }
}

onboardingFilters.addEventListener('submit', event => {
    event.preventDefault();
    loadOnboardings();
});

document.querySelector('#start')?.addEventListener('click', async () => {
    const idSalarie = prompt('Identifiant du salarié');
    if (!idSalarie) return;
    try {
        const result = await api.post('/api/onboardings', {
            idSalarie: Number(idSalarie),
            dateDebut: new Date().toISOString().slice(0, 10),
        });
        location.href = `/personnel/onboarding?id=${encodeURIComponent(result.idOnboarding)}`;
    } catch (error) {
        ui.error(error);
    }
});

loadOnboardings();
