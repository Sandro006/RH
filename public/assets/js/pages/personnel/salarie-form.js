const salaryForm = document.querySelector('#salary-form');
const salaryCandidature = salaryForm.elements.idCandidature;
const salarySubmit = salaryForm.querySelector('button[type=submit]');
const salaryCandidaturePreselect = new URLSearchParams(location.search).get('idCandidature');

function salaryRows(response) {
    return Array.isArray(response) ? response : (response?.data || response?.donnees || []);
}

async function chargerCandidatures() {
    try {
        const [propositions, candidatures, salaries] = await Promise.all([
            api.get(`/api/propositions?statut=${encodeURIComponent('Acceptée')}`),
            api.get('/api/candidatures'),
            api.get('/api/salaries'),
        ]);

        const idCandidatParCandidature = new Map(
            salaryRows(candidatures).map(row => [Number(row.id_candidature), Number(row.id_candidat)])
        );
        const candidatsAvecSalarie = new Set(
            salaryRows(salaries).map(row => Number(row.id_candidat)).filter(Boolean)
        );

        const rows = salaryRows(propositions).filter(proposition => {
            const idCandidat = idCandidatParCandidature.get(Number(proposition.id_candidature));
            return proposition.id_candidature && !candidatsAvecSalarie.has(idCandidat);
        });

        if (!rows.length) {
            salaryCandidature.innerHTML = '<option value="">Aucune candidature éligible</option>';
            salarySubmit.disabled = true;
            return;
        }

        salaryCandidature.innerHTML = '<option value="">Choisir une candidature…</option>' + rows.map(proposition => {
            const candidat = `${proposition.prenom || ''} ${proposition.nom || ''}`.trim() || `Candidature #${proposition.id_candidature}`;
            const offre = proposition.offre ? ` — ${proposition.offre}` : '';
            return `<option value="${ui.escape(proposition.id_candidature)}">${ui.escape(candidat + offre)}</option>`;
        }).join('');

        if (salaryCandidaturePreselect) salaryCandidature.value = salaryCandidaturePreselect;
    } catch (error) {
        salaryCandidature.innerHTML = '<option value="">Impossible de charger les candidatures</option>';
        salarySubmit.disabled = true;
        ui.error(error);
    }
}

salaryForm.addEventListener('submit', async event => {
    event.preventDefault();
    const idCandidature = Number(salaryCandidature.value);
    if (!idCandidature) { ui.toast('Choisissez une candidature.'); return; }

    salarySubmit.disabled = true;
    try {
        const result = await api.post('/api/salaries', { idCandidature });
        if (!result?.idSalarie) { throw new Error('Identifiant salarié manquant dans la réponse.'); }
        ui.toast('Salarié créé.');
        location.href = `/personnel/salarie?id=${encodeURIComponent(result.idSalarie)}`;
    } catch (error) {
        ui.error(error);
        salarySubmit.disabled = false;
    }
});

chargerCandidatures();
