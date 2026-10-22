const list = document.querySelector('#list');
const form = document.querySelector('#filters');
async function loadSalaries() {
    list.innerHTML = ui.state('Chargement…');
    try {
        const query = new URLSearchParams(new FormData(form));
        [...query].forEach(([key, value]) => { if (!value) query.delete(key); });
        const rows = await api.get(`/api/salaries?${query}`);
        const data = Array.isArray(rows) ? rows : (rows?.data || []);
        if (!data.length) { list.innerHTML = ui.state('Aucun salarié trouvé.'); return; }
        list.innerHTML = `<div class="table-wrap"><table><thead><tr><th>Matricule</th><th>Nom</th><th>Poste</th><th>Embauché le</th><th>Statut</th><th>Action</th></tr></thead><tbody>${data.map(row => `<tr><td>${ui.escape(row.matricule)}</td><td>${ui.escape(`${row.prenom || ''} ${row.nom || ''}`)}</td><td>${ui.escape(row.poste || row.intitule || '—')}</td><td>${ui.date(row.date_embauche)}</td><td>${ui.badge(row.statut_designation || row.statut)}</td><td><a href="salarie.html?id=${row.id_salarie}">Voir</a></td></tr>`).join('')}</tbody></table></div>`;
    } catch (error) { list.innerHTML = ui.state(error.message, true); }
}
form.addEventListener('submit', event => { event.preventDefault(); loadSalaries(); });
loadSalaries();
