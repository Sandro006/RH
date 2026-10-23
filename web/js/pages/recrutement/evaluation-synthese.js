const evaluationCandidatureId = new URLSearchParams(location.search).get('idCandidature');
const summary = document.querySelector('#summary');
const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
async function loadEvaluation() {
    try {
        const data = await api.get(`/api/candidatures/${evaluationCandidatureId}/evaluation`);
        summary.innerHTML = `<h2>Note moyenne : ${data.noteMoyenne}/5</h2><p>${data.nombreEvaluations} évaluation(s)</p><ul>${data.evaluations.map(e => `<li>${e.note}/5 — ${escapeHtml(e.commentaire || '')}</li>`).join('')}</ul>`;
        document.querySelector('#proposal').href = `proposition-form.html?idCandidature=${evaluationCandidatureId}`;
    } catch (error) { summary.textContent = error.message; }
}
document.querySelector('#retain').onclick = async () => { try { await api.post(`/api/candidatures/${evaluationCandidatureId}/retenir`); loadEvaluation(); } catch (error) { summary.textContent = error.message; } };
document.querySelector('#reject').onclick = async () => { try { await api.post(`/api/candidatures/${evaluationCandidatureId}/refuser`, { motif: prompt('Motif du refus') || null }); loadEvaluation(); } catch (error) { summary.textContent = error.message; } };
loadEvaluation();
