const contractForm = document.querySelector('#contract-form');
const trial = document.querySelector('#with-trial');
const contractParams = new URLSearchParams(location.search);
const salaryField = contractForm.elements.idSalarie;
if (contractParams.get('idSalarie')) salaryField.value = contractParams.get('idSalarie');
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
