async function chargerListe(field, endpoint, placeholder, labeler) {
    if (!field) return;
    const select = field.tagName === 'SELECT' ? field : Object.assign(document.createElement('select'), {
        name: field.name,
        required: field.required,
        id: field.id,
    });
    if (field !== select) field.replaceWith(select);
    const response = await api.get(endpoint);
    const rows = response.donnees || response.data || response || [];
    select.innerHTML = `<option value="">${placeholder}</option>`;
    rows.forEach(row => select.append(new Option(labeler(row), row.id ?? row.id_salarie)));
    return select;
}

async function initialiserChampsLisibles() {
    const department = await chargerListe(document.querySelector('[name="idDepartement"]'), '/api/referentiels/departements', 'Tous les départements', row => row.libelle || row.nom);
    const poste = await chargerListe(document.querySelector('[name="idPoste"]'), '/api/referentiels/postes', 'Choisir un poste', row => row.libelle || row.intitule);
    await chargerListe(document.querySelector('[name="idSalarie"]'), '/api/salaries', 'Choisir un salarié', row => `${row.prenom || ''} ${row.nom || ''} — ${row.matricule}`.trim());
    await chargerListe(document.querySelector('[name="typeContratId"]'), '/api/referentiels/types-contrat', 'Choisir un type de contrat', row => row.libelle || row.designation);

    if (department && poste) {
        department.addEventListener('change', async () => {
            const response = await api.get('/api/referentiels/postes');
            const rows = (response.donnees || []).filter(row => !department.value || String(row.idDepartement) === department.value);
            poste.innerHTML = '<option value="">Choisir un poste</option>';
            rows.forEach(row => poste.append(new Option(row.libelle, row.id)));
        });
    }

    const params = new URLSearchParams(location.search);
    ['idDepartement', 'idPoste', 'idSalarie', 'typeContratId'].forEach(name => {
        const field = document.querySelector(`[name="${name}"]`);
        if (field && params.get(name)) field.value = params.get(name);
    });
}

async function afficherAffectationsEnrichies() {
    const active = document.querySelector('[data-tab].active');
    const content = document.querySelector('#content');
    const id = new URLSearchParams(location.search).get('id');
    if (!active || active.dataset.tab !== 'affectations' || !content || !id) return;

    try {
        const response = await api.get(`/api/salaries/${id}/affectations`);
        const rows = Array.isArray(response) ? response : (response.data || []);
        content.innerHTML = rows.length ? `<div class="table-wrap"><table><thead><tr><th>Poste</th><th>Département</th><th>Début</th><th>Fin</th><th>Motif</th></tr></thead><tbody>${rows.map(row => `<tr><td>${ui.escape(row.poste || '—')}</td><td>${ui.escape(row.departement || '—')}</td><td>${ui.date(row.date_debut)}</td><td>${ui.date(row.date_fin)}</td><td>${ui.escape(row.motif || '—')}</td></tr>`).join('')}</tbody></table></div>` : ui.state('Aucune affectation.');
    } catch (error) {
        content.innerHTML = ui.state(error.message, true);
    }
}

document.addEventListener('click', event => {
    if (event.target.closest('[data-tab="affectations"]')) setTimeout(afficherAffectationsEnrichies, 100);
});
setTimeout(afficherAffectationsEnrichies, 700);

const demarrerReferentiels = () => initialiserChampsLisibles().catch(error => console.warn(error));
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', demarrerReferentiels, { once: true });
} else {
    demarrerReferentiels();
}
