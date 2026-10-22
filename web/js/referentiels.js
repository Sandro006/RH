async function chargerOptions(select, endpoint, placeholder = 'Choisir…') {
    if (!select) return [];
    const response = await api.get(endpoint);
    const rows = response.donnees || response.data || response || [];
    select.innerHTML = `<option value="">${ui.escape(placeholder)}</option>`;
    rows.forEach(row => {
        const option = document.createElement('option');
        option.value = row.id ?? row.idPoste ?? row.idSalarie;
        option.textContent = row.libelle ?? row.intitule ?? row.nom ?? `${row.prenom || ''} ${row.nom || ''}`.trim();
        if (row.description) option.title = row.description;
        select.append(option);
    });
    return rows;
}

async function chargerReferentielsPersonnel() {
    const department = document.querySelector('[name="idDepartement"]');
    const poste = document.querySelector('[name="idPoste"]');
    const salary = document.querySelector('[name="idSalarie"]');
    const typeContract = document.querySelector('[name="typeContratId"]');

    if (department) await chargerOptions(department, '/api/referentiels/departements', 'Tous les départements');
    if (poste) await chargerOptions(poste, '/api/referentiels/postes', 'Tous les postes');
    if (department && poste) department.addEventListener('change', async () => {
        const response = await api.get('/api/referentiels/postes');
        const rows = (response.donnees || []).filter(row => !department.value || String(row.idDepartement) === department.value);
        poste.innerHTML = '<option value="">Tous les postes</option>';
        rows.forEach(row => poste.append(new Option(row.libelle, row.id)));
    });
    if (salary) {
        const response = await api.get('/api/salaries');
        const rows = Array.isArray(response) ? response : (response.data || []);
        salary.innerHTML = '<option value="">Tous les salariés</option>';
        rows.forEach(row => salary.append(new Option(`${row.prenom || ''} ${row.nom || ''} — ${row.matricule}`.trim(), row.id_salarie)));
    }
    if (typeContract) await chargerOptions(typeContract, '/api/referentiels/types-contrat', 'Choisir un type de contrat');
}

document.addEventListener('DOMContentLoaded', () => chargerReferentielsPersonnel().catch(error => ui.error(error)));
