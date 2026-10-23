const evaluationId = new URLSearchParams(location.search).get('idCandidature');
document.querySelector('#evaluation-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const values = Object.fromEntries(new FormData(form));
    const criteres = Object.fromEntries(Object.entries(values).filter(([key]) => key !== 'commentaire'));
    try {
        await api.post(`/api/candidatures/${evaluationId}/evaluation`, { criteres, commentaire: values.commentaire });
        location.href = `evaluation-synthese.html?idCandidature=${evaluationId}`;
    } catch (error) { document.querySelector('#message').textContent = error.message; }
});
