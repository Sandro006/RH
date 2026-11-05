const id = Number(new URLSearchParams(location.search).get('id'));
const hasValidId = Number.isInteger(id) && id > 0;
const content = document.querySelector('#content');
let salary;
async function loadSalary() {
    if (!hasValidId) {
        content.innerHTML = `${ui.state('Identifiant salarié manquant. Choisissez un salarié dans la liste.', true)}<div class="actions"><a class="button button-secondary" href="/personnel/salaries">Retour à la liste</a></div>`;
        document.querySelectorAll('[data-tab]').forEach(button => { button.disabled = true; });
        return;
    }
    try {
        salary = await api.get(`/api/salaries/${id}`);
        document.querySelector('#heading').innerHTML = `<h1>${ui.escape(salary.prenom || '')} ${ui.escape(salary.nom || '')}</h1><span>${ui.badge(salary.statut_designation || salary.statut)}</span><div class="actions"><a class="button button-secondary" href="/personnel/affectation-form?id=${id}">Affecter</a><a class="button button-primary" href="/personnel/contrat-form?idSalarie=${id}">Nouveau contrat</a></div>`;
        renderIdentity();
    } catch (error) { content.innerHTML = ui.state(error.message, true); }
}
function renderIdentity() {
    content.innerHTML = `<div class="form-grid"><p><strong>Matricule</strong><br>${ui.escape(salary.matricule)}</p><p><strong>Nom complet</strong><br>${ui.escape(`${salary.prenom || ''} ${salary.nom || ''}`)}</p><p><strong>Email</strong><br>${ui.escape(salary.email || '—')}</p><p><strong>Téléphone</strong><br>${ui.escape(salary.telephone || '—')}</p><p><strong>Date d'embauche</strong><br>${ui.date(salary.date_embauche)}</p></div>`;
}
async function renderTab(tab) {
    if (!hasValidId) return;
    if (tab === 'identity') { renderIdentity(); return; }
    const endpoints = { affectations: `/api/salaries/${id}/affectations`, contrats: `/api/contrats?idSalarie=${id}`, documents: `/api/salaries/${id}/documents`, historique: `/api/salaries/${id}/historique` };
    try {
        const result = await api.get(endpoints[tab]);
        const rows = Array.isArray(result) ? result : (result?.data || []);
        const toolbar = tab === 'documents' ? `<div class="actions"><a class="button button-primary" href="/personnel/document-form?idSalarie=${id}">Déposer un document</a></div>` : '';
        content.innerHTML = toolbar + (rows.length ? `<div class="table-wrap"><table><tbody>${rows.map(row => `<tr><td>${ui.escape(row.designation || row.nom_fichier || row.statut_nouveau || row.id_contrat || row.id_affectation || '')}</td><td>${ui.date(row.date_debut || row.date_changement || row.date_creation)}</td><td>${ui.badge(row.statut_designation || row.statut_nouveau || '')}</td></tr>`).join('')}</tbody></table></div>` : ui.state('Aucune donnée.'));
    } catch (error) { content.innerHTML = ui.state(error.message, true); }
}
document.querySelectorAll('[data-tab]').forEach(button => button.addEventListener('click', () => { document.querySelectorAll('[data-tab]').forEach(item => item.classList.remove('active')); button.classList.add('active'); renderTab(button.dataset.tab); }));
loadSalary();
