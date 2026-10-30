const contractForm = document.querySelector('#contract-form');
const trial = document.querySelector('#with-trial');
const contractParams = new URLSearchParams(location.search);
const salarieSelect = document.querySelector('#idSalarie');
const posteSelect = document.querySelector('#idPoste');

// Charger la liste des salariés
async function loadSalaries() {
    try {
        const salaries = await api.get('/api/salaries');
        salaries.forEach(salarie => {
            const option = document.createElement('option');
            option.value = salarie.idSalarie;
            option.textContent = `${salarie.nom} ${salarie.prenom}`;
            salarieSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Erreur lors du chargement des salariés:', error);
    }
}

// Charger la liste des postes
async function loadPostes() {
    try {
        const postes = await api.get('/api/postes');
        postes.forEach(poste => {
            const option = document.createElement('option');
            option.value = poste.idPoste;
            option.textContent = poste.titre;
            posteSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Erreur lors du chargement des postes:', error);
    }
}

// Auto-remplissage avec les informations du salarié
async function loadSalarieInfo(idSalarie) {
    try {
        const salarie = await api.get(`/api/salaries/${idSalarie}`);
        if (salarie) {
            // Remplir les champs avec les informations du salarié
            if (salarie.idPoste) posteSelect.value = salarie.idPoste;
            if (salarie.salaireBase) contractForm.elements.salaireBase.value = salarie.salaireBase;
            if (salarie.typeContratId) contractForm.elements.typeContratId.value = salarie.typeContratId;
            if (salarie.dateDebut) contractForm.elements.dateDebut.value = salarie.dateDebut;
            if (salarie.dateFin) contractForm.elements.dateFin.value = salarie.dateFin;
        }
    } catch (error) {
        console.error('Erreur lors du chargement des informations du salarié:', error);
    }
}

// Auto-remplissage depuis une offre d'emploi
async function loadOffreInfo(idOffre) {
    try {
        const offre = await api.get(`/api/offres/${idOffre}`);
        if (offre) {
            // Remplir les champs avec les informations de l'offre
            if (offre.idPoste) posteSelect.value = offre.idPoste;
            if (offre.remunerationMin) contractForm.elements.salaireBase.value = offre.remunerationMin;
            if (offre.typeContratId) contractForm.elements.typeContratId.value = offre.typeContratId;
            if (offre.dateDebut) contractForm.elements.dateDebut.value = offre.dateDebut;
            if (offre.dateFin) contractForm.elements.dateFin.value = offre.dateFin;
        }
    } catch (error) {
        console.error('Erreur lors du chargement des informations de l\'offre:', error);
    }
}

// Auto-remplissage depuis une demande de recrutement
async function loadDemandeInfo(idDemande) {
    try {
        const demande = await api.get(`/api/demandes/${idDemande}`);
        if (demande) {
            // Remplir les champs avec les informations de la demande
            if (demande.idPoste) posteSelect.value = demande.idPoste;
            if (demande.remunerationMin) contractForm.elements.salaireBase.value = demande.remunerationMin;
            if (demande.typeContratId) contractForm.elements.typeContratId.value = demande.typeContratId;
            if (demande.dateSouhaitee) contractForm.elements.dateDebut.value = demande.dateSouhaitee;
            if (demande.dureeContrat) {
                // Calculer la date de fin à partir de la durée
                const dateDebut = new Date(demande.dateSouhaitee);
                const dureeMois = parseInt(demande.dureeContrat);
                dateDebut.setMonth(dateDebut.getMonth() + dureeMois);
                contractForm.elements.dateFin.value = dateDebut.toISOString().slice(0, 10);
            }
        }
    } catch (error) {
        console.error('Erreur lors du chargement des informations de la demande:', error);
    }
}

// Initialisation
loadSalaries();
loadPostes();

// Charger les infos selon le paramètre passé
if (contractParams.get('idOffre')) {
    loadOffreInfo(contractParams.get('idOffre'));
} else if (contractParams.get('idDemande')) {
    loadDemandeInfo(contractParams.get('idDemande'));
} else if (contractParams.get('idSalarie')) {
    salarieSelect.value = contractParams.get('idSalarie');
    loadSalarieInfo(contractParams.get('idSalarie'));
}

trial.addEventListener('change', () => document.querySelector('#trial-fields').classList.toggle('hidden', !trial.checked));

contractForm.addEventListener('submit', async event => {
    event.preventDefault();
    const body = Object.fromEntries(new FormData(contractForm));
    body.avecPeriodeEssai = trial.checked;
    ['idSalarie', 'idPoste', 'typeContratId', 'salaireBase', 'dureeEssaiJours'].forEach(key => { if (body[key] !== undefined && body[key] !== '') body[key] = Number(body[key]); });
    if (!body.avecPeriodeEssai) { delete body.categorie; delete body.dureeEssaiJours; }
    try {
        const result = await api.post('/api/contrats', body);
        ui.toast('Contrat enregistré.');
        location.href = `/personnel/contrat?id=${result.idContrat}`;
    } catch (error) { ui.error(error); }
});
