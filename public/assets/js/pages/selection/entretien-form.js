const entretienForm = document.querySelector('#entretien-form');
const entretienCandidat = document.querySelector('#entretien-candidat');
const entretienRecruteur = document.querySelector('#entretien-recruteur');
const entretienDate = document.querySelector('#entretien-date');
const entretienType = document.querySelector('#entretien-type');
const entretienMessage = document.querySelector('#entretien-message');
const entretienValider = entretienForm?.querySelector('button[type=submit]');

function entretienAlerte(texte, type = 'error') {
    entretienMessage.textContent = texte;
    entretienMessage.className = type;
    entretienMessage.hidden = false;
}

function nomCandidature(row) {
    return [row.prenom, row.nom].filter(Boolean).join(' ') || `Candidature #${row.id_candidature}`;
}

async function chargerFormulaireEntretien() {
    entretienCandidat.innerHTML = '<option value="">Chargement…</option>';
    entretienRecruteur.innerHTML = '<option value="">Chargement…</option>';
    entretienType.innerHTML = '<option value="">—</option>';

    try {
        const [candidatures, employes, types] = await Promise.all([
            api.get(Table.query('/api/candidatures', { statut_designation: 'Entretien prévu' })),
            api.get('/api/referentiels/employes'),
            api.get('/api/referentiels/types-entretien'),
        ]);

        entretienCandidat.innerHTML = candidatures.length
            ? candidatures.map(row => `<option value="${row.id_candidature}">${ui.escape(nomCandidature(row))} — ${ui.escape(row.offre_titre || 'Offre')}</option>`).join('')
            : '<option value="">Aucun candidat au statut « Entretien prévu »</option>';
        entretienValider.disabled = candidatures.length === 0;

        const employesRows = employes.donnees || [];
        entretienRecruteur.innerHTML = employesRows.length
            ? employesRows.map(row => `<option value="${row.id}">${ui.escape(row.libelle)}</option>`).join('')
            : '<option value="">Aucun employé disponible</option>';

        const typesRows = types.donnees || [];
        entretienType.innerHTML = '<option value="">—</option>' + typesRows
            .map(row => `<option value="${row.id}">${ui.escape(row.libelle)}</option>`)
            .join('');
    } catch (error) {
        entretienCandidat.innerHTML = '';
        entretienRecruteur.innerHTML = '';
        entretienType.innerHTML = '<option value="">—</option>';
        entretienValider.disabled = true;
        entretienAlerte(error.message);
    }
}

entretienForm?.addEventListener('submit', async event => {
    event.preventDefault();

    entretienValider.disabled = true;
    entretienMessage.hidden = true;

    try {
        await api.post('/api/entretiens', {
            idCandidature: Number(entretienCandidat.value),
            idRecruteur: Number(entretienRecruteur.value),
            dateEntretien: entretienDate.value.replace('T', ' '),
            typeEntretien: entretienType.value ? Number(entretienType.value) : null,
        });
        entretienForm.reset();
        await chargerFormulaireEntretien();
        entretienAlerte('Entretien planifié avec succès.', 'success');
    } catch (error) {
        entretienAlerte(error.message);
        entretienValider.disabled = false;
    }
});

chargerFormulaireEntretien();
