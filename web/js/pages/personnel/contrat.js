const contractId = new URLSearchParams(location.search).get('id');
const detail = document.querySelector('#detail');
const tabContent = document.querySelector('#tab-content');
let contract;
async function loadContract() {
    if (!contractId) { detail.innerHTML = ui.state('Identifiant contrat manquant.', true); return; }
    try {
        contract = await api.get(`/api/contrats/${contractId}`);
        document.querySelector('#heading').innerHTML = `<h1>Contrat n°${contract.id_contrat}</h1>${ui.badge(contract.statut_designation || contract.statut)}<div class="actions"><a class="button button-secondary" href="contrat-form.html?id=${contractId}">Modifier</a><a class="button button-primary" href="contrat-avenant-form.html?id=${contractId}">Avenant</a></div>`;
        renderDetail();
    } catch (error) { detail.innerHTML = ui.state(error.message, true); }
}
function renderDetail() {
    detail.innerHTML = `<div class="form-grid"><p><strong>Salarié</strong><br>${ui.escape(contract.salarie_nom || contract.id_salarie)}</p><p><strong>Type</strong><br>${ui.escape(contract.type_contrat_designation || contract.type_contrat_id)}</p><p><strong>Période</strong><br>${ui.date(contract.date_debut)} → ${ui.date(contract.date_fin)}</p><p><strong>Rémunération</strong><br>${ui.money(contract.salaire_base)}</p><p><strong>Période d'essai</strong><br>${ui.date(contract.periode_essai_debut)} → ${ui.date(contract.periode_essai_fin)}</p><p><strong>Signature</strong><br>${ui.date(contract.date_signature)}</p></div><div class="actions">${actionsForStatus(contract.statut_designation || contract.statut)}</div>`;
}
function actionsForStatus(status) {
    const action = (label, endpoint, body = {}) => `<button class="button-secondary" data-action="${endpoint}" data-body='${JSON.stringify(body)}'>${label}</button>`;
    if (status === 'En préparation') return action('Soumettre pour signature', 'soumettre');
    if (status === 'En attente de signature') return action('Signer', 'signer');
    if (status === 'Signé') return action('Activer', 'activer');
    if (status === 'En cours') return `${action('Créer un avenant', 'avenant')}${action('Mettre fin', 'mettre-fin')}`;
    return '';
}
document.addEventListener('click', async event => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const endpoint = button.dataset.action;
    try {
        const body = endpoint === 'signer' ? { dateSignature: new Date().toISOString().slice(0, 10) } : endpoint === 'mettre-fin' ? { motif: prompt('Motif de fin') || '' } : {};
        await api.post(`/api/contrats/${contractId}/${endpoint}`, body);
        ui.toast('Action effectuée.'); loadContract();
    } catch (error) { ui.error(error); }
});
document.querySelectorAll('[data-tab]').forEach(button => button.addEventListener('click', async () => {
    document.querySelectorAll('[data-tab]').forEach(item => item.classList.remove('active')); button.classList.add('active');
    if (button.dataset.tab === 'history') {
        try { const rows = await api.get(`/api/contrats/${contractId}/historique`); tabContent.innerHTML = (rows || []).map(row => `<p>${ui.date(row.date_changement)} — ${ui.badge(row.statut_nouveau)} ${ui.escape(row.commentaire || '')}</p>`).join('') || ui.state('Aucun historique.'); } catch (error) { tabContent.innerHTML = ui.state(error.message, true); }
    } else tabContent.innerHTML = '';
}));
loadContract();
