const propositionForm = document.querySelector('#proposition-form');
const propositionCandidature = propositionForm.elements.idCandidature;
const propositionSubmit = propositionForm.querySelector('button[type=submit]');
const propositionParams = new URLSearchParams(location.search);
const propositionPreselect = propositionParams.get('idCandidature');

const PROPOSITION_STATUTS_NON_RECREABLES = ['À envoyer', 'Envoyée', 'En négociation', 'Acceptée'];

async function chargerCandidaturesRetenues() {
    try {
        const [candidatures, propositions] = await Promise.all([
            api.get('/api/candidatures'),
            api.get('/api/propositions'),
        ]);

        const propositionsActives = propositions.filter(proposition =>
            PROPOSITION_STATUTS_NON_RECREABLES.includes(proposition.statut_designation)
        );

        if (propositionPreselect) {
            const existante = propositionsActives.find(proposition =>
                String(proposition.id_candidature) === String(propositionPreselect)
            );
            if (existante) {
                location.href = `/selection/proposition?id=${encodeURIComponent(existante.id_proposition)}`;
                return;
            }
        }

        const candidaturesActives = new Set(propositionsActives.map(proposition => String(proposition.id_candidature)));
        const rows = candidatures.filter(candidature =>
            candidature.statut_designation === 'Retenue'
            && !candidaturesActives.has(String(candidature.id_candidature))
        );

        if (!rows.length) {
            propositionCandidature.innerHTML = '<option value="">Aucune candidature éligible</option>';
            propositionSubmit.disabled = true;
            return;
        }

        propositionCandidature.innerHTML = '<option value="">Choisir une candidature…</option>' + rows.map(candidature => {
            const candidat = [candidature.prenom, candidature.nom].filter(Boolean).join(' ') || `Candidature #${candidature.id_candidature}`;
            const offre = candidature.offre_titre ? ` — ${candidature.offre_titre}` : '';
            return `<option value="${ui.escape(candidature.id_candidature)}">${ui.escape(candidat + offre)}</option>`;
        }).join('');

        if (propositionPreselect) propositionCandidature.value = propositionPreselect;
    } catch (error) {
        propositionCandidature.innerHTML = '<option value="">Impossible de charger les candidatures</option>';
        propositionSubmit.disabled = true;
        ui.error(error);
    }
}

propositionForm.addEventListener('submit', async event => {
    event.preventDefault();

    const idCandidature = Number(propositionCandidature.value);
    if (!idCandidature) { ui.toast('Choisissez une candidature.'); return; }

    const remunerationProposee = Number(propositionForm.remunerationProposee.value);
    if (!(remunerationProposee > 0)) { ui.toast('Saisissez une rémunération supérieure à 0.'); return; }

    propositionSubmit.disabled = true;
    try {
        const result = await api.post('/api/propositions', {
            idCandidature,
            remunerationProposee,
            dateEmbaucheProposee: propositionForm.dateEmbaucheProposee.value || null,
        });
        ui.toast('Proposition créée.');
        location.href = `/selection/proposition?id=${encodeURIComponent(result.idProposition)}`;
    } catch (error) {
        ui.error(error);
        propositionSubmit.disabled = false;
    }
});

chargerCandidaturesRetenues();
