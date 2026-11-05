const onboardingId = new URLSearchParams(location.search).get('id');
const summary = document.querySelector('#summary');
const tasks = document.querySelector('#tasks');
const documents = document.querySelector('#documents');
const historique = document.querySelector('#historique');
let currentTab = 'checklist';

async function loadOnboarding() {
    if (!onboardingId) { summary.innerHTML = ui.state('Identifiant onboarding manquant.', true); return; }
    try {
        const [data, rows] = await Promise.all([
            api.get(`/api/onboardings/${onboardingId}`),
            api.get(`/api/onboardings/${onboardingId}/taches`),
        ]);
        const progress = data.progression || 0;
        document.querySelector('#heading').innerHTML = `<h1>Onboarding n°${data.id_onboarding} — ${ui.escape([data.salarie_prenom, data.salarie_nom].filter(Boolean).join(' ') || data.id_salarie)}</h1>${ui.badge(data.statut_designation || data.statut)}`;
        summary.innerHTML = `<p><strong>Progression :</strong> ${progress}%<div class="progress"><span style="width:${Math.min(progress, 100)}%"></span></div></p><p><strong>Début :</strong> ${ui.date(data.date_debut)}</p>`;
        renderTasks(rows);
    } catch (error) { summary.innerHTML = ui.state(error.message, true); }
}

function renderTasks(rows) {
    if (!rows.length) { tasks.innerHTML = ui.state('Aucune tâche.'); return; }
    tasks.innerHTML = `<div class="table-wrap"><table><thead><tr><th>Tâche</th><th>Statut</th><th>Responsable</th><th>Échéance</th><th>Actions</th></tr></thead><tbody>${rows.map(row => `<tr><td>${ui.escape(row.intitule || row.titre || row.libelle || row.nom)}</td><td>${ui.badge(row.statut_designation || row.statut)}</td><td>${ui.escape(row.responsable || '-')}</td><td>${ui.date(row.date_echeance)}</td><td>${taskActions(row)}</td></tr>`).join('')}</tbody></table></div>`;
}

function taskActions(row) {
    const status = String(row.statut_designation || row.statut || '').toLowerCase();
    const idTask = row.id_tache;
    const editButton = `<button class="button-secondary edit-task" data-task="${idTask}">Modifier</button>`;
    
    if (status.includes('terminée') || status.includes('annulée')) return editButton;
    if (status.includes('bloquée')) return `${editButton} <button class="button-secondary task-action" data-task="${idTask}" data-endpoint="demarrer">Débloquer</button>`;
    const actions = status.includes('en cours') ? [['realiser', 'Terminer'], ['bloquer', 'Bloquer'], ['annuler', 'Annuler']] : [['demarrer', 'Démarrer'], ['bloquer', 'Bloquer'], ['annuler', 'Annuler']];
    return `${editButton} ${actions.map(([endpoint, label]) => `<button class="button-secondary task-action" data-task="${idTask}" data-endpoint="${endpoint}">${label}</button>`).join(' ')}`;
}

async function loadDocuments() {
    try {
        const docs = await api.get(`/api/salaries/${onboardingId}/documents`);
        if (!docs.length) { documents.innerHTML = ui.state('Aucun document.'); return; }
        documents.innerHTML = `<div class="table-wrap"><table><thead><tr><th>Type</th><th>Nom</th><th>Date</th></tr></thead><tbody>${docs.map(doc => `<tr><td>${ui.escape(doc.type_document_designation || doc.type_document)}</td><td>${ui.escape(doc.nom_fichier)}</td><td>${ui.date(doc.date_depot)}</td></tr>`).join('')}</tbody></table></div>`;
    } catch (error) { documents.innerHTML = ui.state(error.message, true); }
}

async function loadHistorique() {
    try {
        const hist = await api.get(`/api/onboardings/${onboardingId}/historique`);
        if (!hist.length) { historique.innerHTML = ui.state('Aucun historique.'); return; }
        historique.innerHTML = `<div class="table-wrap"><table><thead><tr><th>Date</th><th>Statut</th><th>Commentaire</th></tr></thead><tbody>${hist.map(h => `<tr><td>${ui.date(h.date_changement)}</td><td>${ui.badge(h.statut_nouveau_designation || h.statut_nouveau)}</td><td>${ui.escape(h.commentaire || '-')}</td></tr>`).join('')}</tbody></table></div>`;
    } catch (error) { historique.innerHTML = ui.state(error.message, true); }
}

function switchTab(tabName) {
    currentTab = tabName;
    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.add('hidden'));
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelector(`#${tabName}`).classList.remove('hidden');
    document.querySelector(`.tab[data-tab="${tabName}"]`).classList.add('active');
    
    if (tabName === 'documents') loadDocuments();
    if (tabName === 'historique') loadHistorique();
}

document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => switchTab(tab.dataset.tab));
});

document.addEventListener('click', async event => {
    const button = event.target.closest('.task-action');
    if (!button) return;
    try { const body = button.dataset.endpoint === 'bloquer' ? { motif: prompt('Motif du blocage') || '' } : {}; await api.post(`/api/onboardings/taches/${button.dataset.task}/${button.dataset.endpoint}`, body); ui.toast('Tâche mise à jour.'); loadOnboarding(); } catch (error) { ui.error(error); }
});

document.querySelector('#add-task').addEventListener('click', async () => {
    const intitule = prompt('Intitulé de la tâche');
    if (!intitule) return;
    try { await api.post(`/api/onboardings/${onboardingId}/taches`, { intitule }); ui.toast('Tâche ajoutée.'); loadOnboarding(); } catch (error) { ui.error(error); }
});

document.querySelector('#close-onboarding').addEventListener('click', async () => { try { await api.post(`/api/onboardings/${onboardingId}/cloturer`); ui.toast('Onboarding clôturé.'); loadOnboarding(); } catch (error) { ui.error(error); } });

document.querySelector('#cancel-onboarding').addEventListener('click', async () => { const motif = prompt('Motif d’annulation'); if (!motif) return; try { await api.post(`/api/onboardings/${onboardingId}/annuler`, { motif }); ui.toast('Onboarding annulé.'); loadOnboarding(); } catch (error) { ui.error(error); } });

document.querySelector('#edit-onboarding').addEventListener('click', () => {
    location.href = `/personnel/onboarding-form?id=${encodeURIComponent(onboardingId)}`;
});

document.addEventListener('click', async event => {
    const editButton = event.target.closest('.edit-task');
    if (!editButton) return;
    
    const idTask = editButton.dataset.task;
    location.href = `/personnel/onboarding-tache-form?id=${encodeURIComponent(idTask)}&onboarding=${encodeURIComponent(onboardingId)}`;
});

loadOnboarding();
