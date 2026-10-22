const onboardingList = document.querySelector('#list');
const onboardingFilters = document.querySelector('#filters');
async function loadOnboardings() {
    onboardingList.innerHTML = ui.state('Chargement…');
    try {
        const query = new URLSearchParams(new FormData(onboardingFilters));
        [...query].forEach(([key, value]) => { if (!value) query.delete(key); });
        const result = await api.get(`/api/onboardings?${query}`);
        const rows = Array.isArray(result) ? result : (result?.data || []);
        if (!rows.length) { onboardingList.innerHTML = ui.state('Aucun onboarding trouvé.'); return; }
        onboardingList.innerHTML = `<div class="table-wrap"><table><thead><tr><th>N°</th><th>Salarié</th><th>Début</th><th>Progression</th><th>Statut</th><th></th></tr></thead><tbody>${rows.map(row => { const progress = Number(row.progression || 0); return `<tr><td>${row.id_onboarding}</td><td>${ui.escape(row.salarie_nom || row.id_salarie)}</td><td>${ui.date(row.date_debut)}</td><td>${progress}%<div class="progress"><span style="width:${Math.min(progress, 100)}%"></span></div></td><td>${ui.badge(row.statut_designation || row.statut)}</td><td><a href="onboarding.html?id=${row.id_onboarding}">Voir</a></td></tr>`; }).join('')}</tbody></table></div>`;
    } catch (error) { onboardingList.innerHTML = ui.state(error.message, true); }
}
onboardingFilters.addEventListener('submit', event => { event.preventDefault(); loadOnboardings(); });
document.querySelector('#start').addEventListener('click', async () => {
    const idSalarie = prompt('Identifiant du salarié');
    if (!idSalarie) return;
    try { const result = await api.post('/api/onboardings', { idSalarie: Number(idSalarie), dateDebut: new Date().toISOString().slice(0, 10) }); location.href = `onboarding.html?id=${result.idOnboarding}`; } catch (error) { ui.error(error); }
});
loadOnboardings();
