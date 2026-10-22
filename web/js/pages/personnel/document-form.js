const documentForm = document.querySelector('#document-form');
const documentSalaryId = new URLSearchParams(location.search).get('idSalarie') || new URLSearchParams(location.search).get('id');
async function loadDocumentTypes() {
    try {
        const result = await api.get('/api/referentiels/types-document');
        const rows = Array.isArray(result) ? result : (result?.data || []);
        documentForm.typeDocument.innerHTML = '<option value="">Choisir…</option>' + rows.map(row => `<option value="${row.id}">${ui.escape(row.designation)}</option>`).join('');
    } catch (error) { ui.error(error); }
}
documentForm.fichier.addEventListener('change', () => { if (!documentForm.nomFichier.value) documentForm.nomFichier.value = documentForm.fichier.files[0]?.name || ''; });
documentForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (!documentSalaryId) { ui.toast('Identifiant salarié manquant.'); return; }
    const file = documentForm.fichier.files[0];
    try {
        await api.post(`/api/salaries/${documentSalaryId}/documents`, { typeDocument: Number(documentForm.typeDocument.value), nomFichier: documentForm.nomFichier.value, cheminFichier: file.name });
        ui.toast('Document ajouté.');
        setTimeout(() => { location.href = `salarie.html?id=${documentSalaryId}`; }, 500);
    } catch (error) { ui.error(error); }
});
loadDocumentTypes();
