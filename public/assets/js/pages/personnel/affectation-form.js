const assignmentForm = document.querySelector('#assignment-form');
const salaryId = new URLSearchParams(location.search).get('id');
assignmentForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (!salaryId) { ui.toast('Identifiant salarié manquant.'); return; }
    try {
        const body = Object.fromEntries(new FormData(assignmentForm));
        await api.post(`/api/salaries/${salaryId}/affectations`, body);
        ui.toast('Affectation enregistrée.');
        setTimeout(() => { location.href = `/personnel/salarie?id=${salaryId}`; }, 500);
    } catch (error) { ui.error(error); }
});
