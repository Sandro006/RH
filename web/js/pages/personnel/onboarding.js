const onboardingId = new URLSearchParams(location.search).get('id');
const summary = document.querySelector('#summary');
const tasks = document.querySelector('#tasks');
async function loadOnboarding() {
    if (!onboardingId) { summary.innerHTML = ui.state('Identifiant onboarding manquant.', true); return; }
    try {
        const data = await api.get(`/api/onboardings/${onboardingId}`);
        const rows = data.taches || data.tasks || [];
        document.querySelector('#heading').innerHTML = `<h1>Onboarding n°${data.id_onboarding}</h1>${ui.badge(data.statut_designation || data.statut)}`;
        summary.innerHTML = `<p><strong>Salarié :</strong> ${ui.escape(data.salarie_nom || data.id_salarie)}</p><p><strong>Début :</strong> ${ui.date(data.date_debut)}</p>`;
        renderTasks(rows);
    } catch (error) { summary.innerHTML = ui.state(error.message, true); }
}
function renderTasks(rows) {
    if (!rows.length) { tasks.innerHTML = ui.state('Aucune tâche.'); return; }
    tasks.innerHTML = `<div class="table-wrap"><table><thead><tr><th>Tâche</th><th>Statut</th><th>Échéance</th><th>Actions</th></tr></thead><tbody>${rows.map(row => `<tr><td>${ui.escape(row.titre || row.libelle || row.nom)}</td><td>${ui.badge(row.statut_designation || row.statut)}</td><td>${ui.date(row.date_echeance)}</td><td>${taskActions(row)}</td></tr>`).join('')}</tbody></table></div>`;
}
function taskActions(row) {
    const status = row.statut_designation || row.statut || '';
    const idTask = row.id_tache;
    if (/terminée|annulée/i.test(status)) return '';
    const actions = /en cours/i.test(status) ? [['realiser', 'Terminer'], ['bloquer', 'Bloquer'], ['annuler', 'Annuler']] : [['demarrer', 'Démarrer'], ['bloquer', 'Bloquer'], ['annuler', 'Annuler']];
    return actions.map(([endpoint, label]) => `<button class="button-secondary task-action" data-task="${idTask}" data-endpoint="${endpoint}">${label}</button>`).join(' ');
}
document.addEventListener('click', async event => {
    const button = event.target.closest('.task-action');
    if (!button) return;
    try { const body = button.dataset.endpoint === 'bloquer' ? { motif: prompt('Motif du blocage') || '' } : {}; await api.post(`/api/onboardings/taches/${button.dataset.task}/${button.dataset.endpoint}`, body); ui.toast('Tâche mise à jour.'); loadOnboarding(); } catch (error) { ui.error(error); }
});
document.querySelector('#add-task').addEventListener('click', async () => {
    const titre = prompt('Intitulé de la tâche');
    if (!titre) return;
    try { await api.post(`/api/onboardings/${onboardingId}/taches`, { titre }); ui.toast('Tâche ajoutée.'); loadOnboarding(); } catch (error) { ui.error(error); }
});
document.querySelector('#close-onboarding').addEventListener('click', async () => { try { await api.post(`/api/onboardings/${onboardingId}/cloturer`); ui.toast('Onboarding clôturé.'); loadOnboarding(); } catch (error) { ui.error(error); } });
document.querySelector('#cancel-onboarding').addEventListener('click', async () => { const motif = prompt('Motif d’annulation'); if (!motif) return; try { await api.post(`/api/onboardings/${onboardingId}/annuler`, { motif }); ui.toast('Onboarding annulé.'); loadOnboarding(); } catch (error) { ui.error(error); } });
loadOnboarding();
