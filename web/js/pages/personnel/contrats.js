const contractList = document.querySelector('#list');
const contractFilters = document.querySelector('#filters');
async function loadContracts() {
    contractList.innerHTML = ui.state('Chargement…');
    try {
        const query = new URLSearchParams(new FormData(contractFilters));
        [...query].forEach(([key, value]) => { if (!value) query.delete(key); });
        const result = await api.get(`/api/contrats?${query}`);
        const rows = Array.isArray(result) ? result : (result?.data || []);
        if (!rows.length) { contractList.innerHTML = ui.state('Aucun contrat trouvé.'); return; }
        contractList.innerHTML = `<div class="table-wrap"><table><thead><tr><th>N°</th><th>Salarié</th><th>Type</th><th>Du</th><th>Au</th><th>Essai</th><th>Statut</th><th></th></tr></thead><tbody>${rows.map(row => `<tr><td>${row.id_contrat}</td><td>${ui.escape(row.salarie_nom || row.nom || row.id_salarie)}</td><td>${ui.escape(row.type_contrat_designation || row.type_contrat_id)}</td><td>${ui.date(row.date_debut)}</td><td>${ui.date(row.date_fin)}</td><td>${row.periode_essai_fin ? 'Oui' : 'Non'}</td><td>${ui.badge(row.statut_designation || row.statut)}</td><td><a href="contrat.html?id=${row.id_contrat}">Voir</a></td></tr>`).join('')}</tbody></table></div>`;
    } catch (error) { contractList.innerHTML = ui.state(error.message, true); }
}
contractFilters.addEventListener('submit', event => { event.preventDefault(); loadContracts(); });
loadContracts();
