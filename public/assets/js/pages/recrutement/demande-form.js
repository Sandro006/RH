const demandeForm = document.querySelector('#demande-form');
const demandeMessage = document.querySelector('#message');

function afficherMessage(texte, type) {
    demandeMessage.textContent = texte;
    demandeMessage.className = type || '';
    demandeMessage.hidden = false;
}

function remplirSelect(select, lignes, placeholder) {
    select.innerHTML = `<option value="">${placeholder}</option>`;
    lignes.forEach(ligne => select.append(new Option(ligne.libelle, ligne.id)));
}

async function chargerReferentiels() {
    try {
        const user = auth.cached() || await auth.current();
        if (user?.idEmploye) demandeForm.id_demandeur.value = user.idEmploye;

        const [postes, types] = await Promise.all([
            api.get('/api/referentiels/postes'),
            api.get('/api/referentiels/types-contrat'),
        ]);

        remplirSelect(demandeForm.id_poste, postes.donnees || postes.data || [], 'Choisir un poste…');
        remplirSelect(demandeForm.type_contrat, types.donnees || types.data || [], 'Choisir un type de contrat…');
    } catch (error) {
        afficherMessage(error.message, 'error');
    }
}

demandeForm?.addEventListener('submit', async event => {
    event.preventDefault();
    demandeMessage.hidden = true;

    const soumettreImmediatement = demandeForm.soumettre.checked;
    const body = Object.fromEntries(new FormData(demandeForm));
    delete body.soumettre;

    if (!body.id_demandeur) {
        afficherMessage("Aucun employé n'est associé à votre compte, impossible de créer la demande.", 'error');
        return;
    }

    ['id_poste', 'type_contrat', 'id_demandeur'].forEach(key => { body[key] = Number(body[key]); });
    ['duree_contrat', 'nom_personne_remplacee', 'missions', 'date_souhaitee', 'remuneration_min', 'remuneration_max']
        .forEach(key => { if (body[key] === '' || body[key] === undefined) delete body[key]; });
    if (body.remuneration_min !== undefined) body.remuneration_min = Number(body.remuneration_min);
    if (body.remuneration_max !== undefined) body.remuneration_max = Number(body.remuneration_max);

    const button = demandeForm.querySelector('button[type=submit]');
    button.disabled = true;

    try {
        const id = await api.post('/api/demandes', body);
        if (soumettreImmediatement) await api.post(`/api/demandes/${id}/soumettre`);
        ui.toast('Demande de recrutement créée.');
        location.href = '/recrutement/demandes';
    } catch (error) {
        afficherMessage(error.message, 'error');
        button.disabled = false;
    }
});

chargerReferentiels();
