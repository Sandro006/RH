const evaluationId = new URLSearchParams(location.search).get('id');
const evaluationCandidateBox = document.querySelector('#evaluation-candidat');
const evaluationFormSection = document.querySelector('#evaluation-form-section');
const evaluationForm = document.querySelector('#evaluation-form');
const evaluationSummary = document.querySelector('#evaluation-synthese');
const evaluationMessage = document.querySelector('#evaluation-message');
let evaluationCandidate;

function evaluationAlert(text, type = 'error') { evaluationMessage.textContent = text; evaluationMessage.className = type; evaluationMessage.hidden = false; }
function candidateName(candidate) { return [candidate?.prenom, candidate?.nom].filter(Boolean).join(' ') || 'Candidat'; }
function displayCriteria(value) { try { const criteria = typeof value === 'string' ? JSON.parse(value) : value; return Object.entries(criteria || {}).map(([key, note]) => `${ui.escape(key)} : ${ui.escape(note)}/5`).join(' · '); } catch { return '—'; } }

function updateNote() {
    const values = Array.from(evaluationForm.querySelectorAll('input[name]')).map(input => Number(input.value)).filter(Number.isFinite);
    document.querySelector('#evaluation-note').value = values.length ? (values.reduce((sum, value) => sum + value, 0) / values.length).toFixed(2) : '';
}

function renderSummary(summary) {
    const average = summary.noteMoyenne === null || summary.noteMoyenne === undefined ? '—' : `${Number(summary.noteMoyenne).toFixed(2)} / 5`;
    const rows = summary.evaluations || [];
    evaluationSummary.innerHTML = `<p><strong>Note moyenne : ${ui.escape(average)}</strong> <span class="muted">(${rows.length} évaluation${rows.length > 1 ? 's' : ''})</span></p>${rows.length ? `<div class="table-wrap"><table><thead><tr><th>Date</th><th>Note</th><th>Critères</th><th>Commentaire</th></tr></thead><tbody>${rows.map(row => `<tr><td>${ui.date(row.date_evaluation)}</td><td>${ui.escape(row.note)} / 5</td><td>${displayCriteria(row.criteres)}</td><td>${ui.escape(row.commentaire || '—')}</td></tr>`).join('')}</tbody></table></div>` : '<p class="muted">Aucune évaluation enregistrée.</p>'}`;
}

async function loadEvaluation() {
    if (!/^\d+$/.test(evaluationId || '')) { evaluationCandidateBox.innerHTML = ui.state('Identifiant de candidature manquant.', true); return; }
    try {
        const [candidates, summary] = await Promise.all([api.get('/api/candidatures'), api.get(`/api/candidatures/${evaluationId}/evaluations/synthese`)]);
        evaluationCandidate = candidates.find(candidate => Number(candidate.id_candidature) === Number(evaluationId));
        if (!evaluationCandidate) throw new Error('Candidature introuvable.');
        evaluationCandidateBox.innerHTML = `<h2>${ui.escape(candidateName(evaluationCandidate))}</h2><div class="form-grid"><p><strong>Offre</strong><br>${ui.escape(evaluationCandidate.offre_titre || '—')}</p><p><strong>Statut</strong><br>${ui.status(evaluationCandidate.statut_designation || '—')}</p><p><strong>E-mail</strong><br>${ui.escape(evaluationCandidate.email || '—')}</p></div>`;
        if (evaluationCandidate.statut_designation === 'En évaluation') evaluationFormSection.classList.remove('hidden');
        else evaluationFormSection.innerHTML = `<h2>Évaluation indisponible</h2><p class="muted">La candidature doit être au statut « En évaluation ». Son statut actuel est ${ui.status(evaluationCandidate.statut_designation || 'inconnu')}.</p>`;
        renderSummary(summary);
    } catch (error) { evaluationCandidateBox.innerHTML = ui.state(error.message, true); evaluationSummary.innerHTML = ''; }
}

evaluationForm?.querySelectorAll('input[name]').forEach(input => input.addEventListener('input', updateNote));
evaluationForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const button = evaluationForm.querySelector('button[type=submit]'); button.disabled = true; evaluationMessage.hidden = true;
    try {
        const fields = Object.fromEntries(new FormData(evaluationForm));
        const criteres = Object.fromEntries(['technique', 'experience', 'communication', 'motivation', 'adequation'].map(key => [key, Number(fields[key])]));
        await api.post(`/api/candidatures/${evaluationId}/evaluations`, { criteres, note: Number(document.querySelector('#evaluation-note').value), commentaire: fields.commentaire });
        evaluationForm.reset(); updateNote(); evaluationAlert('Évaluation enregistrée.', 'success'); await loadEvaluation();
    } catch (error) { evaluationAlert(error.message); } finally { button.disabled = false; }
});
loadEvaluation();
