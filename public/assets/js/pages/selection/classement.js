const rankingOfferId = new URLSearchParams(location.search).get('offre');
const rankingBox = document.querySelector('#classement');
const rankingMessage = document.querySelector('#classement-message');
const rankingAlert = (text, type = 'error') => { rankingMessage.textContent = text; rankingMessage.className = type; rankingMessage.hidden = false; };

async function loadRanking() {
    if (!/^\d+$/.test(rankingOfferId || '')) { rankingBox.innerHTML = ui.state('Identifiant d’offre manquant.', true); return; }
    try {
        const [ranking, eligible] = await Promise.all([api.get(`/api/offres/${rankingOfferId}/classement`), api.get(`/api/offres/${rankingOfferId}/candidats-eligibles`)]);
        const eligibleIds = new Set((eligible.candidats || []).map(candidate => Number(candidate.idCandidature)));
        if (!ranking.length) { rankingBox.innerHTML = ui.state('Aucune candidature à évaluer pour cette offre.'); return; }
        rankingBox.innerHTML = `<p class="muted">Seuil de rétention : ${ui.escape(eligible.seuil)} / 5</p><div class="table-wrap"><table><thead><tr><th>Rang</th><th>Candidat</th><th>Évaluations</th><th>Note moyenne</th><th>Éligible</th><th>Décision</th></tr></thead><tbody>${ranking.map((candidate, index) => { const isEligible = eligibleIds.has(Number(candidate.idCandidature)); return `<tr><td>${index + 1}</td><td><a href="/selection/evaluation?id=${candidate.idCandidature}">${ui.escape([candidate.prenom, candidate.nom].filter(Boolean).join(' ') || candidate.idCandidature)}</a></td><td>${ui.escape(candidate.nombreEvaluations)}</td><td>${candidate.noteMoyenne === null ? '—' : ui.escape(Number(candidate.noteMoyenne).toFixed(2))} / 5</td><td>${isEligible ? ui.status('Oui') : ui.status('Non')}</td><td><div class="actions">${isEligible ? `<button class="button button-primary" data-decision="retenir" data-id="${candidate.idCandidature}">Retenir</button>` : ''}<button class="button button-secondary" data-decision="refuser" data-id="${candidate.idCandidature}">Refuser</button></div></td></tr>`; }).join('')}</tbody></table></div>`;
        rankingBox.querySelectorAll('[data-decision]').forEach(button => button.addEventListener('click', () => decide(button)));
    } catch (error) { rankingBox.innerHTML = ui.state(error.message, true); }
}

async function decide(button) {
    const retained = button.dataset.decision === 'retenir';
    const motive = retained ? null : window.prompt('Motif du refus (facultatif)');
    if (!retained && motive === null) return;
    button.disabled = true; rankingMessage.hidden = true;
    try { await api.post(`/api/candidatures/${button.dataset.id}/decision`, { retenue: retained, motif: motive || null }); rankingAlert(`Candidature ${retained ? 'retenue' : 'refusée'}.`, 'success'); await loadRanking(); }
    catch (error) { rankingAlert(error.message); button.disabled = false; }
}
loadRanking();
