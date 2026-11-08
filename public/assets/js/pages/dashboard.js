async function chargerDashboard() {
    try {
        const [session, data] = await Promise.all([auth.current(), api.get('/api/dashboard')]);
        document.querySelector('#user').textContent = `${session.identifiant} · ${session.role}`;

        const valeurs = data.indicateurs || {};
        Object.keys(valeurs).forEach(key => {
            const element = document.querySelector(`[data-metric="${key}"]`);
            if (element) {
                element.textContent = Number(valeurs[key] || 0);
            }
        });

        const actions = (data.actionsEnAttente || []).flat();
        document.querySelector('#actions').innerHTML = actions.length
            ? `<div class="actions-grid">${actions.map(action => `
                <a class="action-card" href="${ui.escape(action.lien || '#')}">
                    <div class="action-count">${Number(action.nombre || 0)}</div>
                    <div class="action-label">${ui.escape(action.libelle || 'Action')}</div>
                </a>
            `).join('')}</div>`
            : '<div class="state">Aucune action en attente. Votre équipe est à jour.</div>';
    } catch (error) {
        document.querySelector('#actions').innerHTML = ui.state(error.message, true);
    }
}

chargerDashboard();
