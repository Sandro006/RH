const documentForm = document.querySelector('#document-form');
const documentSalaryId = new URLSearchParams(location.search).get('idSalarie') || new URLSearchParams(location.search).get('id');
const documentHint = document.querySelector('#document-type-hint');
const documentTypes = new Map();

const CONFIDENTIALITE_LABELS = {
    public: 'Public',
    interne: 'Interne',
    rh: 'RH',
    restreint: 'Restreint',
};

if (documentForm && documentSalaryId) documentForm.dataset.salaryId = documentSalaryId;

function ajouterMois(dateIso, mois) {
    const [annee, moisCourant, jour] = dateIso.split('-').map(Number);
    if (!annee || !moisCourant || !jour) return '';

    const date = new Date(Date.UTC(annee, moisCourant - 1, jour));
    date.setUTCDate(1);
    date.setUTCMonth(date.getUTCMonth() + mois);

    const dernierJour = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0)).getUTCDate();
    date.setUTCDate(Math.min(jour, dernierJour));

    return date.toISOString().slice(0, 10);
}

function typeSelectionne() {
    return documentTypes.get(documentForm?.typeDocument.value) || null;
}

function remplirDateExpiration(force = false) {
    if (!documentForm) return;

    const type = typeSelectionne();
    const mois = Number(type?.duree_validite_mois || 0);
    const emission = documentForm.dateEmission.value;
    const expiration = documentForm.dateExpiration;

    if (!mois || !emission) return;
    if (!force && expiration.value && expiration.dataset.manuel === '1') return;

    expiration.value = ajouterMois(emission, mois);
    expiration.dataset.manuel = '';
}

function appliquerTypeDocument() {
    if (!documentForm) return;

    const type = typeSelectionne();
    if (!type) {
        if (documentHint) documentHint.textContent = '';
        return;
    }

    if (type.niveau_confidentialite) {
        documentForm.niveauConfidentialite.value = type.niveau_confidentialite;
    }

    const informations = [];
    if (type.obligatoire) informations.push('Pièce obligatoire');
    if (type.duree_validite_mois) informations.push(`Validité : ${type.duree_validite_mois} mois`);
    if (type.niveau_confidentialite) {
        informations.push(`Confidentialité par défaut : ${CONFIDENTIALITE_LABELS[type.niveau_confidentialite] || type.niveau_confidentialite}`);
    }

    if (documentHint) documentHint.textContent = informations.join(' · ');

    documentForm.dateExpiration.dataset.manuel = '';
    remplirDateExpiration(true);
}

async function loadDocumentTypes() {
    if (!documentForm) return;

    try {
        const result = await api.get('/api/referentiels/types-document');
        const rows = Array.isArray(result) ? result : (result?.data || []);

        documentTypes.clear();
        rows.forEach(row => documentTypes.set(String(row.id), row));

        documentForm.typeDocument.innerHTML = '<option value="">Choisir…</option>'
            + rows.map(row => `<option value="${row.id}">${ui.escape(row.designation)}</option>`).join('');
    } catch (error) {
        ui.error(error);
    }
}

if (documentForm) {
    documentForm.fichier.addEventListener('change', () => {
        const file = documentForm.fichier.files[0];
        if (file && !documentForm.nomFichier.value) documentForm.nomFichier.value = file.name;
    });

    documentForm.typeDocument.addEventListener('change', appliquerTypeDocument);

    documentForm.dateEmission.addEventListener('change', () => remplirDateExpiration());

    documentForm.dateExpiration.addEventListener('change', () => {
        documentForm.dateExpiration.dataset.manuel = documentForm.dateExpiration.value ? '1' : '';
    });

    documentForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!documentSalaryId) { ui.toast('Identifiant salarié manquant.'); return; }

        const file = documentForm.fichier.files[0];
        if (!file) { ui.toast('Veuillez sélectionner un fichier.'); return; }

        const formData = new FormData();
        formData.append('typeDocument', documentForm.typeDocument.value);
        formData.append('fichier', file);
        if (documentForm.nomFichier.value) formData.append('nomFichier', documentForm.nomFichier.value);
        if (documentForm.dateEmission.value) formData.append('dateEmission', documentForm.dateEmission.value);
        if (documentForm.dateExpiration.value) formData.append('dateExpiration', documentForm.dateExpiration.value);
        if (documentForm.niveauConfidentialite.value) formData.append('niveauConfidentialite', documentForm.niveauConfidentialite.value);

        try {
            await api.request(`/api/salaries/${documentSalaryId}/documents`, { method: 'POST', body: formData });
            ui.toast('Document ajouté.');
            setTimeout(() => { location.href = `/personnel/salarie?id=${documentSalaryId}`; }, 500);
        } catch (error) { ui.error(error); }
    });
}

loadDocumentTypes();
