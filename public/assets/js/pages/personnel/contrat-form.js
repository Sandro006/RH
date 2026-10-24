const contractForm = document.querySelector('#contract-form');
const trial = document.querySelector('#with-trial');
const contractParams = new URLSearchParams(location.search);
const salaryField = contractForm.elements.idSalarie;

// Auto-remplissage avec les informations du salarié
async function loadSalarieInfo(idSalarie) {
    try {
        const salarie = await api.get(`/api/salaries/${idSalarie}`);
        if (salarie) {
            // Remplir les champs avec les informations du salarié
            if (salarie.idPoste) contractForm.elements.idPoste.value = salarie.idPoste;
            if (salarie.salaireBase) contractForm.elements.salaireBase.value = salarie.salaireBase;
            if (salarie.typeContratId) contractForm.elements.typeContratId.value = salarie.typeContratId;
            if (salarie.dateDebut) contractForm.elements.dateDebut.value = salarie.dateDebut;
            if (salarie.dateFin) contractForm.elements.dateFin.value = salarie.dateFin;
        }
    } catch (error) {
        console.error('Erreur lors du chargement des informations du salarié:', error);
    }
}

if (contractParams.get('idSalarie')) {
    salaryField.value = contractParams.get('idSalarie');
    loadSalarieInfo(contractParams.get('idSalarie'));
}

trial.addEventListener('change', () => document.querySelector('#trial-fields').classList.toggle('hidden', !trial.checked));

contractForm.addEventListener('submit', async event => {
    event.preventDefault();
    const body = Object.fromEntries(new FormData(contractForm));
    body.avecPeriodeEssai = trial.checked;
    ['idSalarie', 'idPoste', 'typeContratId', 'salaireBase', 'dureeEssaiJours'].forEach(key => { if (body[key] !== undefined && body[key] !== '') body[key] = Number(body[key]); });
    if (!body.avecPeriodeEssai) { delete body.categorie; delete body.dureeEssaiJours; }
    try {
        const result = await api.post('/api/contrats', body);
        ui.toast('Contrat enregistré.');
        location.href = `/personnel/contrat?id=${result.idContrat}`;
    } catch (error) { ui.error(error); }
});
