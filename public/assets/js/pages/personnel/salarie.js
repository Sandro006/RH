const salaryIdParam = new URLSearchParams(location.search).get('id');
const salaryId = Number(salaryIdParam);
const hasValidSalaryId = Number.isInteger(salaryId) && salaryId > 0;

const dossierHeading = document.querySelector('#heading');
const dossierContent = document.querySelector('#content');
const replaceModal = document.querySelector('#replace-modal');
const replaceForm = document.querySelector('#replace-form');
const replaceContext = document.querySelector('#replace-context');

const TABS = ['identity', 'contrats', 'affectations', 'documents', 'carriere', 'historique'];
const DOC_STATUS = {
    valide: 'Valide',
    expire: 'Expiré',
    expire_bientot: 'Expire bientôt',
    sans_expiration: 'Sans expiration',
    archive: 'Archivé',
};
const DOC_TONE = {
    valide: 'success',
    expire: 'danger',
    expire_bientot: 'warning',
    sans_expiration: '',
    archive: '',
};
const CONFIDENTIALITE = {
    public: 'Public',
    interne: 'Interne',
    rh: 'RH',
    restreint: 'Restreint',
};
const EVENT_LABELS = {
    contrat: 'Contrat',
    affectation: 'Affectation',
    statut: 'Statut',
};

const dossier = {
    data: null,
    user: null,
    tab: 'identity',
    editing: false,
    replacing: null,
};

function currentRole() {
    return (dossier.user && dossier.user.role) || (auth.cached() && auth.cached().role) || '';
}

function hasRole(roles) {
    return roles.includes(currentRole());
}

function canEditIdentity() {
    return hasRole(['admin', 'rh', 'recruteur']);
}

function canAddDocument() {
    return hasRole(['admin', 'rh', 'recruteur']);
}

function canReplaceDocument() {
    return hasRole(['admin', 'rh']);
}

function canArchiveDocument() {
    return hasRole(['admin', 'rh']);
}

function confidentialiteLabel(value) {
    return CONFIDENTIALITE[value] || value || '—';
}

function pourcentage(value) {
    return Math.max(0, Math.min(100, Number(value) || 0));
}

function renderHeading() {
    const identite = dossier.data?.identite || {};
    const situation = dossier.data?.situationProfessionnelle || {};
    const completude = dossier.data?.documents?.completude || {};
    const alertes = dossier.data?.alertes || {};
    const pct = pourcentage(completude.pourcentage);

    const badges = [];
    if (alertes.nombre_manquants) {
        badges.push(`<span class="badge warning">${Number(alertes.nombre_manquants)} pièce(s) manquante(s)</span>`);
    }
    if (alertes.nombre_expires) {
        badges.push(`<span class="badge danger">${Number(alertes.nombre_expires)} document(s) expiré(s)</span>`);
    }
    if (alertes.nombre_expirant_bientot) {
        badges.push(`<span class="badge warning">${Number(alertes.nombre_expirant_bientot)} à renouveler</span>`);
    }
    if (!badges.length) {
        badges.push('<span class="badge success">Dossier documentaire complet</span>');
    }

    const actions = [];
    if (canEditIdentity()) {
        actions.push(`<a class="button button-secondary" href="/personnel/affectation-form?id=${salaryId}">Affecter</a>`);
        actions.push(`<a class="button button-secondary" href="/personnel/contrat-form?idSalarie=${salaryId}">Nouveau contrat</a>`);
    }
    if (canAddDocument()) {
        actions.push(`<a class="button button-primary" href="/personnel/document-form?idSalarie=${salaryId}">Déposer un document</a>`);
    }
    actions.push('<a class="button button-ghost" href="/personnel/salaries">Retour à la liste</a>');

    dossierHeading.innerHTML = `
        <div class="dossier-heading-main">
            <h1>${ui.escape(identite.nom_complet || 'Salarié')}</h1>
            <p class="muted">${ui.escape(identite.matricule || 'Matricule inconnu')} · ${ui.escape(situation.poste || 'Poste non affecté')}${situation.departement ? ' · ' + ui.escape(situation.departement) : ''}</p>
            <div class="dossier-meta">
                ${ui.badge(situation.statut_designation || 'Statut non renseigné')}
                <span class="badge">${pct}% des pièces</span>
                ${badges.join('')}
            </div>
            <div class="progress dossier-progress" title="Complétude documentaire"><span style="width:${pct}%"></span></div>
        </div>
        <div class="actions">${actions.join('')}</div>`;
}

function renderIdentity() {
    if (dossier.editing) {
        renderIdentityForm();
        return;
    }

    const identite = dossier.data?.identite || {};
    const champs = [
        ['Matricule', ui.escape(identite.matricule || '—')],
        ['Nom complet', ui.escape(identite.nom_complet || '—')],
        ['Email', ui.escape(identite.email || '—')],
        ['Téléphone', ui.escape(identite.telephone || '—')],
        ['Date de naissance', ui.date(identite.date_naissance)],
        ['Lieu de naissance', ui.escape(identite.lieu_naissance || '—')],
        ['Nationalité', ui.escape(identite.nationalite || '—')],
        ['Situation familiale', ui.escape(identite.situation_familiale || '—')],
        ['Adresse', ui.escape(identite.adresse || '—')],
        ['Ville', ui.escape(identite.ville || '—')],
        ['Pays', ui.escape(identite.pays || '—')],
        ["Date d'embauche", ui.date(identite.date_embauche)],
        ['Date de sortie', ui.date(identite.date_sortie)],
    ];

    dossierContent.innerHTML = `
        <div class="section-head">
            <h2>Identité</h2>
            ${canEditIdentity() ? '<button class="button button-secondary" type="button" data-action="edit-identity">Modifier</button>' : ''}
        </div>
        <div class="form-grid">
            ${champs.map(([label, value]) => `<div class="dossier-field"><small class="muted">${ui.escape(label)}</small><p>${value}</p></div>`).join('')}
        </div>`;
}

function renderIdentityForm() {
    const identite = dossier.data?.identite || {};
    const input = (name, label, value, type = 'text') =>
        `<label>${ui.escape(label)}<input name="${name}" type="${type}" value="${ui.escape(value || '')}"></label>`;

    dossierContent.innerHTML = `
        <form id="identity-form" class="form-grid insertion-form">
            <h2 class="field-wide">Modifier l'identité</h2>
            ${input('prenom', 'Prénom', identite.prenom)}
            ${input('nom', 'Nom', identite.nom)}
            ${input('email', 'Email', identite.email, 'email')}
            ${input('telephone', 'Téléphone', identite.telephone, 'tel')}
            ${input('date_naissance', 'Date de naissance', identite.date_naissance, 'date')}
            ${input('lieu_naissance', 'Lieu de naissance', identite.lieu_naissance)}
            ${input('nationalite', 'Nationalité', identite.nationalite)}
            ${input('situation_familiale', 'Situation familiale', identite.situation_familiale)}
            ${input('ville', 'Ville', identite.ville)}
            ${input('pays', 'Pays', identite.pays)}
            <label class="field-wide">Adresse<textarea name="adresse">${ui.escape(identite.adresse || '')}</textarea></label>
            <div class="actions field-wide">
                <button type="button" class="button button-secondary" data-action="cancel-identity">Annuler</button>
                <button type="submit" class="button-primary">Enregistrer</button>
            </div>
        </form>`;
}

function renderContrats() {
    const contrats = Array.isArray(dossier.data?.contrats) ? dossier.data.contrats : [];
    const toolbar = canEditIdentity()
        ? `<a class="button button-primary" href="/personnel/contrat-form?idSalarie=${salaryId}">Nouveau contrat</a>`
        : '';

    if (!contrats.length) {
        dossierContent.innerHTML = `<div class="section-head"><h2>Contrats</h2>${toolbar}</div>${ui.state('Aucun contrat enregistré pour ce salarié.')}`;
        return;
    }

    const lignes = contrats.map(contrat => `<tr>
        <td><strong>${ui.escape(contrat.type_contrat || 'Contrat')}</strong><br><small class="muted">${ui.escape(contrat.poste || 'Poste non renseigné')}</small></td>
        <td>${ui.date(contrat.date_debut)} → ${ui.date(contrat.date_fin)}</td>
        <td>${ui.money(contrat.salaire_base)}</td>
        <td>${ui.date(contrat.date_signature)}</td>
        <td>${ui.badge(contrat.statut_designation || (contrat.est_en_cours ? 'En cours' : '—'))}</td>
        <td><a href="/personnel/contrat?id=${Number(contrat.id_contrat)}">Voir la fiche</a></td>
    </tr>`).join('');

    dossierContent.innerHTML = `
        <div class="section-head"><h2>Contrats</h2>${toolbar}</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Type / poste</th><th>Période</th><th>Salaire de base</th><th>Signé le</th><th>Statut</th><th></th></tr></thead>
                <tbody>${lignes}</tbody>
            </table>
        </div>`;
}

function renderAffectations() {
    const affectations = Array.isArray(dossier.data?.affectations) ? dossier.data.affectations : [];
    const toolbar = canEditIdentity()
        ? `<a class="button button-primary" href="/personnel/affectation-form?id=${salaryId}">Affecter</a>`
        : '';

    if (!affectations.length) {
        dossierContent.innerHTML = `<div class="section-head"><h2>Affectations</h2>${toolbar}</div>${ui.state('Aucune affectation enregistrée pour ce salarié.')}`;
        return;
    }

    const lignes = affectations.map(affectation => `<tr>
        <td><strong>${ui.escape(affectation.poste || 'Poste #' + affectation.id_poste)}</strong><br><small class="muted">${ui.escape(affectation.departement || '—')}</small></td>
        <td>${ui.date(affectation.date_debut)} → ${ui.date(affectation.date_fin)}</td>
        <td>${ui.escape(affectation.motif || '—')}</td>
        <td>${ui.badge(affectation.en_cours ? 'En cours' : 'Terminée')}</td>
    </tr>`).join('');

    dossierContent.innerHTML = `
        <div class="section-head"><h2>Affectations</h2>${toolbar}</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Poste / département</th><th>Période</th><th>Motif</th><th>Statut</th></tr></thead>
                <tbody>${lignes}</tbody>
            </table>
        </div>`;
}

function renderDocuments() {
    const section = dossier.data?.documents || {};
    const pieces = Array.isArray(section.pieces) ? section.pieces : [];
    const manquants = Array.isArray(section.manquants) ? section.manquants : [];
    const completude = section.completude || {};
    const pct = pourcentage(completude.pourcentage);

    const resume = `
        <div class="doc-summary">
            <div>
                <strong>${Number(completude.presents) || 0}/${Number(completude.attendus) || 0}</strong>
                <span class="muted">pièces obligatoires présentes (${pct}%)</span>
            </div>
            ${canAddDocument() ? `<a class="button button-primary" href="/personnel/document-form?idSalarie=${salaryId}">Déposer un document</a>` : ''}
        </div>
        <div class="progress"><span style="width:${pct}%"></span></div>
        ${manquants.length
            ? `<div class="doc-missing"><strong>Pièces manquantes :</strong>${manquants.map(item => `<span class="badge warning">${ui.escape(item.designation)}</span>`).join('')}</div>`
            : '<p class="muted">Toutes les pièces obligatoires sont présentes.</p>'}`;

    if (!pieces.length) {
        dossierContent.innerHTML = `<div class="section-head"><h2>Documents</h2></div>${resume}${ui.state('Aucun document déposé pour ce salarié.')}`;
        return;
    }

    const lignes = pieces.map(piece => {
        const idDocument = Number(piece.id_document);
        const statut = piece.statut_validite || 'sans_expiration';
        const actions = [
            `<a class="button button-secondary" href="/api/documents/${idDocument}/telecharger">Télécharger</a>`,
        ];

        if (canReplaceDocument()) {
            actions.push(`<button class="button button-secondary" type="button" data-action="replace-document" data-document-id="${idDocument}">Remplacer</button>`);
        }
        if (canArchiveDocument()) {
            actions.push(`<button class="button button-secondary" type="button" data-action="archive-document" data-document-id="${idDocument}">Archiver</button>`);
        }

        return `<tr>
            <td><strong>${ui.escape(piece.type_designation || 'Document')}</strong><br><small class="muted">${ui.escape(piece.nom_fichier || '')}</small></td>
            <td>${ui.date(piece.date_emission)}</td>
            <td>${ui.date(piece.date_expiration)}</td>
            <td><span class="badge">${ui.escape(confidentialiteLabel(piece.niveau_confidentialite))}</span></td>
            <td><span class="badge ${DOC_TONE[statut] || ''}">${ui.escape(DOC_STATUS[statut] || statut)}</span></td>
            <td><div class="doc-actions">${actions.join('')}</div></td>
        </tr>`;
    }).join('');

    dossierContent.innerHTML = `
        <div class="section-head"><h2>Documents</h2></div>
        ${resume}
        <div class="table-wrap">
            <table>
                <thead><tr><th>Type</th><th>Émission</th><th>Expiration</th><th>Confidentialité</th><th>Validité</th><th>Actions</th></tr></thead>
                <tbody>${lignes}</tbody>
            </table>
        </div>`;
}

function timelineDetails(event) {
    const details = event.details || {};
    const parties = [];

    if (details.poste) parties.push(`Poste : ${ui.escape(details.poste)}`);
    if (details.departement) parties.push(`Département : ${ui.escape(details.departement)}`);
    if (details.date_fin) parties.push(`Fin : ${ui.date(details.date_fin)}`);
    if (details.motif) parties.push(`Motif : ${ui.escape(details.motif)}`);
    if (details.commentaire) parties.push(ui.escape(details.commentaire));

    return parties.length ? `<p class="muted">${parties.join(' · ')}</p>` : '';
}

function renderTimeline(events) {
    return `<div class="timeline">${events.map(event => `
        <div class="timeline-item">
            <div class="timeline-item-head">
                <strong>${ui.escape(event.libelle || 'Évènement')}</strong>
                <span class="badge">${ui.escape(EVENT_LABELS[event.type] || event.type || '—')}</span>
            </div>
            <small class="muted">${ui.date(event.date)}</small>
            ${timelineDetails(event)}
        </div>`).join('')}</div>`;
}

function renderCarriere() {
    const evenements = Array.isArray(dossier.data?.carriere) ? dossier.data.carriere : [];

    if (!evenements.length) {
        dossierContent.innerHTML = `<div class="section-head"><h2>Carrière</h2></div>${ui.state('Aucun évènement de carrière enregistré.')}`;
        return;
    }

    dossierContent.innerHTML = `
        <div class="section-head"><h2>Carrière</h2><span class="muted">${evenements.length} évènement(s)</span></div>
        ${renderTimeline(evenements)}`;
}

function renderHistorique() {
    const statuts = (Array.isArray(dossier.data?.carriere) ? dossier.data.carriere : [])
        .filter(event => event.type === 'statut');
    const onboarding = dossier.data?.onboarding;
    const blocs = [];

    if (onboarding) {
        const progression = pourcentage(onboarding.progression);
        blocs.push(`<div class="card dossier-subcard">
            <h3>Onboarding</h3>
            <p>${ui.badge(onboarding.statut_designation || '—')} ${ui.date(onboarding.date_debut)} → ${ui.date(onboarding.date_fin)}</p>
            <p class="muted">${Number(onboarding.taches_terminees) || 0}/${Number(onboarding.taches_total) || 0} tâches terminées (${progression}%)</p>
            <div class="progress"><span style="width:${progression}%"></span></div>
        </div>`);
    }

    if (statuts.length) {
        blocs.push(`<div class="section-head"><h2>Changements de statut</h2></div>${renderTimeline(statuts)}`);
    } else {
        blocs.push(ui.state('Aucun changement de statut enregistré.'));
    }

    dossierContent.innerHTML = `<div class="section-head"><h2>Historique</h2></div>${blocs.join('')}`;
}

function renderTab(tab) {
    dossier.tab = TABS.includes(tab) ? tab : 'identity';

    document.querySelectorAll('[data-tab]').forEach(button => {
        button.classList.toggle('active', button.dataset.tab === dossier.tab);
    });

    if (!hasValidSalaryId) return;

    if (!dossier.data) {
        dossierContent.innerHTML = ui.state('Chargement du dossier…');
        return;
    }

    dossierContent.innerHTML = ui.state('Chargement…');

    window.requestAnimationFrame(() => {
        switch (dossier.tab) {
            case 'contrats':
                renderContrats();
                break;
            case 'affectations':
                renderAffectations();
                break;
            case 'documents':
                renderDocuments();
                break;
            case 'carriere':
                renderCarriere();
                break;
            case 'historique':
                renderHistorique();
                break;
            default:
                renderIdentity();
        }
    });
}

function openReplaceModal(idDocument) {
    const document = (dossier.data?.documents?.pieces || [])
        .find(item => Number(item.id_document) === idDocument);

    if (!document || !replaceForm) return;

    dossier.replacing = idDocument;
    replaceForm.reset();
    replaceForm.elements.nomFichier.value = document.nom_fichier || '';
    replaceForm.elements.dateEmission.value = document.date_emission || '';
    replaceForm.elements.dateExpiration.value = document.date_expiration || '';
    replaceForm.elements.niveauConfidentialite.value = document.niveau_confidentialite || '';
    replaceContext.textContent = `${document.type_designation || 'Document'} — ${document.nom_fichier || ''}`;
    replaceModal.classList.remove('hidden');
    replaceForm.elements.fichier.focus();
}

function closeReplaceModal() {
    dossier.replacing = null;
    if (replaceModal) replaceModal.classList.add('hidden');
    if (replaceForm) replaceForm.reset();
}

async function archiverDocument(idDocument) {
    if (!window.confirm('Archiver ce document ? Il ne sera plus affiché dans le dossier.')) return;

    try {
        await api.delete(`/api/documents/${idDocument}`);
        ui.toast('Document archivé.');
        await loadDossier();
    } catch (error) {
        ui.error(error);
    }
}

async function loadDossier() {
    if (!hasValidSalaryId) {
        document.querySelectorAll('[data-tab]').forEach(button => { button.disabled = true; });
        dossierHeading.innerHTML = '<h1>Fiche salarié</h1><p class="muted">Identifiant manquant</p>';
        dossierContent.innerHTML = `${ui.state('Identifiant salarié manquant. Choisissez un salarié dans la liste.', true)}<div class="actions"><a class="button button-secondary" href="/personnel/salaries">Retour à la liste</a></div>`;
        return;
    }

    try {
        dossier.user = auth.cached() || await auth.current();
        dossier.data = await api.get(`/api/salaries/${salaryId}/dossier`);
        renderHeading();
        renderTab(dossier.tab);
    } catch (error) {
        document.querySelectorAll('[data-tab]').forEach(button => { button.disabled = true; });
        dossierHeading.innerHTML = '<h1>Fiche salarié</h1><p class="muted">Dossier indisponible</p>';
        dossierContent.innerHTML = `${ui.state(error.message, true)}<div class="actions"><a class="button button-secondary" href="/personnel/salaries">Retour à la liste</a></div>`;
    }
}

document.querySelectorAll('[data-tab]').forEach(button => {
    button.addEventListener('click', () => {
        dossier.editing = false;
        history.replaceState(null, '', `#${button.dataset.tab}`);
        renderTab(button.dataset.tab);
    });
});

dossierContent.addEventListener('click', event => {
    const button = event.target.closest('[data-action]');
    if (!button) return;

    const action = button.dataset.action;

    if (action === 'edit-identity') {
        dossier.editing = true;
        renderIdentity();
    } else if (action === 'cancel-identity') {
        dossier.editing = false;
        renderIdentity();
    } else if (action === 'replace-document') {
        openReplaceModal(Number(button.dataset.documentId));
    } else if (action === 'archive-document') {
        archiverDocument(Number(button.dataset.documentId));
    }
});

dossierContent.addEventListener('submit', async event => {
    if (event.target.id !== 'identity-form') return;

    event.preventDefault();

    const body = Object.fromEntries(new FormData(event.target));
    if (!body.date_naissance) body.date_naissance = null;

    try {
        await api.patch(`/api/salaries/${salaryId}`, body);
        ui.toast('Identité mise à jour.');
        dossier.editing = false;
        await loadDossier();
    } catch (error) {
        ui.error(error);
    }
});

if (replaceForm) {
    replaceForm.addEventListener('submit', async event => {
        event.preventDefault();

        if (!dossier.replacing) return;

        const fichier = replaceForm.elements.fichier.files[0];
        if (!fichier) {
            ui.toast('Veuillez sélectionner un fichier.');
            return;
        }

        const donnees = new FormData();
        donnees.append('fichier', fichier);
        if (replaceForm.elements.nomFichier.value) donnees.append('nomFichier', replaceForm.elements.nomFichier.value);
        if (replaceForm.elements.dateEmission.value) donnees.append('dateEmission', replaceForm.elements.dateEmission.value);
        if (replaceForm.elements.dateExpiration.value) donnees.append('dateExpiration', replaceForm.elements.dateExpiration.value);
        if (replaceForm.elements.niveauConfidentialite.value) donnees.append('niveauConfidentialite', replaceForm.elements.niveauConfidentialite.value);

        try {
            await api.request(`/api/documents/${dossier.replacing}/remplacer`, { method: 'POST', body: donnees });
            ui.toast('Document remplacé.');
            closeReplaceModal();
            await loadDossier();
        } catch (error) {
            ui.error(error);
        }
    });
}

if (replaceModal) {
    replaceModal.addEventListener('click', event => {
        if (event.target === replaceModal || event.target.closest('[data-close-modal]')) {
            closeReplaceModal();
        }
    });
}

document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && replaceModal && !replaceModal.classList.contains('hidden')) {
        closeReplaceModal();
    }
});

const requestedTab = location.hash.replace('#', '');
if (TABS.includes(requestedTab)) dossier.tab = requestedTab;

loadDossier();
