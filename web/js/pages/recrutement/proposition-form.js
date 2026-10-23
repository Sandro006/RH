const candidatureId = new URLSearchParams(location.search).get('idCandidature');
document.querySelector('#proposal-form').addEventListener('submit', async event => {
    event.preventDefault(); const form = event.currentTarget;
    try { const result = await api.post(`/api/candidatures/${candidatureId}/proposition`, { remunerationProposee: Number(form.remunerationProposee.value), dateEmbaucheProposee: form.dateEmbaucheProposee.value || null }); location.href = `proposition.html?id=${result.idProposition}`; }
    catch (error) { document.querySelector('#message').textContent = error.message; }
});
