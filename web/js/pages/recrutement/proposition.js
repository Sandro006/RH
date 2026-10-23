const propositionId = new URLSearchParams(location.search).get('id');
const proposalBox = document.querySelector('#proposal');
const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
async function loadProposal() {
    try {
        const proposition = await api.get(`/api/propositions/${propositionId}`);
        proposalBox.innerHTML = `<h1>Proposition #${proposition.id_proposition}</h1><p>Statut : ${escapeHtml(proposition.statut_designation || '')}</p><p>Rémunération : ${proposition.remuneration_proposee ?? '—'}</p><p>Date d’embauche : ${proposition.date_embauche_proposee ?? '—'}</p><div>${(proposition.revendications || []).map(r => `<p>${escapeHtml(r.nature)} : ${escapeHtml(r.valeur_demandee || '')} — ${escapeHtml(r.statut_designation || '')}</p>`).join('')}</div><div class="actions"><button data-action="envoyer">Envoyer</button><button data-action="repondre">Accepter</button></div>`;
        proposalBox.querySelectorAll('[data-action]').forEach(button => button.onclick = async () => { try { await api.post(`/api/propositions/${propositionId}/${button.dataset.action}`, button.dataset.action === 'repondre' ? { accepte: true } : {}); loadProposal(); } catch (error) { proposalBox.insertAdjacentHTML('beforeend', `<p>${escapeHtml(error.message)}</p>`); } });
    } catch (error) { proposalBox.textContent = error.message; }
}
document.querySelector('#claim-form').addEventListener('submit', async event => { event.preventDefault(); try { await api.post(`/api/propositions/${propositionId}/revendications`, Object.fromEntries(new FormData(event.currentTarget))); event.currentTarget.reset(); loadProposal(); } catch (error) { proposalBox.textContent = error.message; } });
loadProposal();
