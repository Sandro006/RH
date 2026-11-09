const amendmentForm = document.querySelector('#amendment-form');
const originalContractId = new URLSearchParams(location.search).get('id');
amendmentForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (!originalContractId) { ui.toast('Contrat d’origine manquant.'); return; }
    const body = Object.fromEntries(new FormData(amendmentForm));
    const type = body.type;
    delete body.type;
    Object.keys(body).forEach(key => { if (body[key] === '') delete body[key]; });
    if (body.idPoste) body.idPoste = Number(body.idPoste);
    if (body.salaireBase) body.salaireBase = Number(body.salaireBase);
    try {
        const result = type === 'renouvellement'
            ? await api.post(`/api/contrats/${originalContractId}/renouveler`, { nouvelleDateFin: body.nouvelleDateFin })
            : await api.post(`/api/contrats/${originalContractId}/avenant`, body);
        ui.toast('Opération enregistrée.');
        location.href = `/personnel/contrat?id=${result.idContrat}`;
    } catch (error) { ui.error(error); }
});
