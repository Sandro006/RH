const dashboardLabels = {
    demandesEnAttente: 'Demandes en attente',
    offresPubliees: 'Offres publiées',
    candidatures: 'Candidatures actives',
    entretiensAVenir: 'Entretiens à venir',
    propositionsEnCours: 'Propositions en cours',
    tachesOnboarding: 'Tâches onboarding',
};

async function chargerDashboard() {
    try {
        const [session, data] = await Promise.all([auth.current(), api.get('/api/dashboard')]);
        document.querySelector('#user').textContent = `${session.identifiant} · ${session.role}`;

        const valeurs = data.indicateurs || {};
        document.querySelector('#metrics').innerHTML = Object.entries(dashboardLabels)
            .map(([key, label]) => `<div class="metric"><small>${label}</small><strong>${Number(valeurs[key] || 0)}</strong></div>`)
            .join('');

        const actions = (data.actionsEnAttente || []).flat();
        document.querySelector('#actions').innerHTML = actions.length
            ? actions.map(action => `<a class="shortcut" href="${ui.escape(action.lien || '#')}"><strong>${Number(action.nombre || 0)}</strong> ${ui.escape(action.libelle || 'Action')}</a>`).join('')
            : '<div class="state">Aucune action en attente. Votre équipe est à jour.</div>';
    } catch (error) {
        document.querySelector('#actions').innerHTML = ui.state(error.message, true);
    }
}

chargerDashboard();
