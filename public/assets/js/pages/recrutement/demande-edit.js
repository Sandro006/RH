const editForm = document.querySelector('#demande-form');
const editMessage = document.querySelector('#message');
const editId = new URLSearchParams(location.search).get('id');
const statutsModifiables = ['En cours', 'À corriger'];

function afficherEdit(texte, type) {
    editMessage.textContent = texte;
    editMessage.className = type || '';
    editMessage.hidden = false;
}

function remplirEditSelect(select, lignes, placeholder) {
    select.innerHTML = `<option value="">${placeholder}</option>`;
    lignes.forEach(ligne => select.append(new Option(ligne.libelle, ligne.id)));
}

async function chargerDemandeAModifier() {
    if (!editId) {
        afficherEdit('Identifiant de demande manquant.', 'error');
        editForm.querySelector('button[type=submit]').disabled = true;
        return;
    }

    try {
        await auth.current();
        const [demande, postes, types] = await Promise.all([
            api.get(`/api/demandes/${editId}`),
            api.get('/api/referentiels/postes'),
            api.get('/api/referentiels/types-contrat'),
        ]);

        if (!statutsModifiables.includes(demande.designation_statut)) {
            afficherEdit("Cette demande n'est plus modifiable dans son statut actuel.", 'error');
            editForm.querySelector('button[type=submit]').disabled = true;
            return;
        }

        remplirEditSelect(editForm.id_poste, postes.donnees || [], 'Choisir un poste…');
        remplirEditSelect(editForm.type_contrat, types.donnees || [], 'Choisir un type de contrat…');

        editForm.id_poste.value = demande.id_poste;
        editForm.type_contrat.value = demande.type_contrat;
        editForm.motif.value = demande.motif || '';
        editForm.duree_contrat.value = demande.duree_contrat || '';
        editForm.nom_personne_remplacee.value = demande.nom_personne_remplacee || '';
        editForm.date_souhaitee.value = (demande.date_souhaitee || '').slice(0, 10);
        editForm.remuneration_min.value = demande.remuneration_min ?? '';
        editForm.remuneration_max.value = demande.remuneration_max ?? '';
        editForm.missions.value = demande.missions || '';
    } catch (error) {
        afficherEdit(error.message, 'error');
    }
}

editForm?.addEventListener('submit', async event => {
    event.preventDefault();
    editMessage.hidden = true;

    const body = Object.fromEntries(new FormData(editForm));
    ['id_poste', 'type_contrat'].forEach(key => { body[key] = Number(body[key]); });
    ['duree_contrat', 'nom_personne_remplacee', 'missions', 'date_souhaitee', 'remuneration_min', 'remuneration_max']
        .forEach(key => { if (body[key] === '' || body[key] === undefined) delete body[key]; });
    if (body.remuneration_min !== undefined) body.remuneration_min = Number(body.remuneration_min);
    if (body.remuneration_max !== undefined) body.remuneration_max = Number(body.remuneration_max);

    const bouton = editForm.querySelector('button[type=submit]');
    bouton.disabled = true;

    try {
        await api.patch(`/api/demandes/${editId}`, body);
        ui.toast('Demande mise à jour.');
        location.href = `/recrutement/demande?id=${editId}`;
    } catch (error) {
        afficherEdit(error.message, 'error');
        bouton.disabled = false;
    }
});

chargerDemandeAModifier();
