const demandeId = new URLSearchParams(location.search).get('id');
const demandeHeading = document.querySelector('#heading');
const demandeDetail = document.querySelector('#detail');
const coherenceCard = document.querySelector('#coherence-card');
const coherenceBox = document.querySelector('#coherence');
const actionsBox = document.querySelector('#actions');
const validationsBox = document.querySelector('#validations');
const historiqueBox = document.querySelector('#historique');
const demandeMessage = document.querySelector('#message');

let session;
let demande;
let coherence;
let grillePoste;

const champsSpeciaux = {
    commentaire: { label: 'Commentaire', type: 'textarea' },
    motif: { label: "Motif d'annulation", type: 'textarea' },
    montant_approuve: { label: 'Montant approuvé', type: 'number', min: 0, step: 0.01 },
    canal_diffusion: { label: 'Canal de diffusion', type: 'text' },
};

function afficherMessage(texte, type) {
    demandeMessage.textContent = texte;
    demandeMessage.className = type || '';
    demandeMessage.hidden = false;
}

function peut(roles) {
    return session?.role === 'admin' || roles.includes(session?.role);
}

function peutGerer() {
    return peut(['manager']) || Number(demande?.id_demandeur) === Number(session?.idEmploye);
}

function remunerationDansGrille() {
    if (!grillePoste) return null;
    const min = Number(demande?.remuneration_min);
    const max = Number(demande?.remuneration_max);
    if (Number.isNaN(min) || Number.isNaN(max)) return null;
    return min >= grillePoste.salaireMin && max <= grillePoste.salaireMax;
}

function grilleBadge() {
    const conforme = remunerationDansGrille();
    if (conforme === null) return '';
    return conforme
        ? '<span class="badge success">Dans la grille</span>'
        : '<span class="badge danger">Hors grille</span>';
}

function grilleNote() {
    const remuneration = `${ui.money(demande?.remuneration_min)} – ${ui.money(demande?.remuneration_max)}`;
    if (!grillePoste) {
        return '<p class="muted">Aucune grille salariale active pour ce poste.</p>';
    }
    return `<div class="grille-note">
        <p><strong>Grille salariale du poste :</strong> ${ui.money(grillePoste.salaireMin)} – ${ui.money(grillePoste.salaireMax)} ${grilleBadge()}</p>
        <p class="muted">Rémunération demandée : ${remuneration}</p>
    </div>`;
}

function actionsPour(statut) {
    const editer = { label: 'Modifier', href: `/recrutement/demande-edit?id=${demande.id_demande}`, variant: 'button-secondary' };
    const soumettre = { label: 'Soumettre au contrôle RH', endpoint: 'soumettre', needs: [], body: {}, variant: 'button-primary' };
    const annuler = { label: 'Annuler la demande', endpoint: 'annuler', needs: ['motif'], body: {}, variant: 'button-danger' };

    const controleRh = [
        { label: 'Valider', endpoint: 'controle-rh', body: { decision: 'VALIDER' }, needs: ['commentaire'], variant: 'button-primary', coherenceRequise: true },
        { label: 'Demander une correction', endpoint: 'controle-rh', body: { decision: 'CORRIGER' }, needs: ['commentaire'], variant: 'button-secondary' },
        { label: 'Refuser', endpoint: 'controle-rh', body: { decision: 'REFUSER' }, needs: ['commentaire'], variant: 'button-danger' },
    ];
    const budget = [
        { label: 'Valider le budget', endpoint: 'validation-budget', body: { decision: 'VALIDER' }, needs: ['montant_approuve', 'commentaire'], variant: 'button-primary' },
        { label: 'Demander une correction', endpoint: 'validation-budget', body: { decision: 'CORRIGER' }, needs: ['commentaire'], variant: 'button-secondary' },
        { label: 'Refuser', endpoint: 'validation-budget', body: { decision: 'REFUSER' }, needs: ['commentaire'], variant: 'button-danger' },
    ];
    const hierarchie = [
        { label: 'Donner le feu vert', endpoint: 'validation-hierarchie', body: { decision: 'FEU_VERT' }, needs: ['commentaire'], variant: 'button-primary' },
        { label: 'Valider avec consultation interne', endpoint: 'validation-hierarchie', body: { decision: 'VALIDER', consultation: true }, needs: ['commentaire'], variant: 'button-secondary' },
        { label: 'Demander une correction', endpoint: 'validation-hierarchie', body: { decision: 'CORRIGER' }, needs: ['commentaire'], variant: 'button-secondary' },
        { label: 'Refuser', endpoint: 'validation-hierarchie', body: { decision: 'REFUSER' }, needs: ['commentaire'], variant: 'button-danger' },
    ];
    const consultation = [
        { label: 'Clôturer : valider', endpoint: 'cloture-consultation', body: { decision: 'VALIDER' }, needs: ['commentaire'], variant: 'button-primary' },
        { label: 'Clôturer : refuser', endpoint: 'cloture-consultation', body: { decision: 'REFUSER' }, needs: ['commentaire'], variant: 'button-danger' },
    ];
    const feuVert = { label: "Donner le feu vert (créer l'offre)", endpoint: 'feu-vert', needs: ['canal_diffusion'], body: {}, variant: 'button-primary', redirection: '/recrutement/offres' };

    switch (statut) {
        case 'En cours':
        case 'À corriger':
            return peutGerer() ? [editer, soumettre, annuler] : [];
        case 'En contrôle RH':
            return peut(['rh']) ? controleRh : [];
        case 'En validation budgétaire':
            return peut(['controle_gestion']) ? budget : [];
        case 'En validation hiérarchique':
            return peut(['direction']) ? hierarchie : [];
        case 'En consultation interne':
            return peut(['rh', 'direction']) ? consultation : [];
        case 'Validée':
            return peut(['rh']) ? [feuVert] : [];
        default:
            return [];
    }
}

function rendreEntete() {
    demandeHeading.innerHTML = `<div>
        <h1>Demande n°${demande.id_demande}</h1>
        <p class="muted">${ui.escape(demande.poste || '')}</p>
    </div>
    <div class="actions">${ui.status(demande.designation_statut || demande.statut)}</div>`;
}

function rendreDetail() {
    const lignes = [
        ['Poste', ui.escape(demande.poste || demande.id_poste || '—')],
        ['Service', ui.escape(demande.service || '—')],
        ['Département', ui.escape(demande.departement || '—')],
        ['Demandeur', ui.escape(demande.demandeur_nom || '—')],
        ['Motif', ui.escape(demande.motif || '—')],
        ['Type de contrat', ui.escape(demande.type_contrat_designation || demande.type_contrat || '—')],
        ['Durée du contrat', ui.escape(demande.duree_contrat || '—')],
        ['Personne remplacée', ui.escape(demande.nom_personne_remplacee || '—')],
        ['Date souhaitée', ui.date(demande.date_souhaitee)],
        ['Rémunération', `${ui.money(demande.remuneration_min)} – ${ui.money(demande.remuneration_max)}`],
        ['Grille salariale du poste', grillePoste
            ? `${ui.money(grillePoste.salaireMin)} – ${ui.money(grillePoste.salaireMax)} ${grilleBadge()}`
            : '<span class="muted">Aucune grille active</span>'],
        ['Créée le', ui.date(demande.date_creation)],
        ['Missions', ui.escape(demande.missions || '—')],
    ];

    demandeDetail.innerHTML = `<div class="form-grid">${lignes.map(([label, valeur]) => `<p><strong>${label}</strong><br>${valeur}</p>`).join('')}</div>`;
}

function rendreCoherence() {
    if (!coherence) return;
    coherenceCard.classList.remove('hidden');
    const entete = coherence.conforme
        ? '<span class="badge success">Conforme</span>'
        : '<span class="badge danger">Anomalies détectées</span>';
    const lignes = (coherence.anomalies || []).map(anomalie => `<li>${anomalie.conforme ? '✔' : '✘'} ${ui.escape(anomalie.message || anomalie.regle)}</li>`).join('');
    coherenceBox.innerHTML = `<p>${entete}</p><ul>${lignes}</ul>`;
}

function rendreValidations(rows) {
    if (!rows || !rows.length) {
        validationsBox.innerHTML = ui.state('Aucune décision enregistrée.');
        return;
    }

    validationsBox.innerHTML = `<div class="table-wrap"><table><thead><tr><th>Date</th><th>Validateur</th><th>Statut résultant</th><th>Commentaire</th></tr></thead><tbody>${rows
        .map(row => `<tr><td>${ui.date(row.date_validation)}</td><td>${ui.escape(row.validateur_nom || row.validateur_identifiant || row.id_validateur || '—')}</td><td>${ui.status(row.statut_designation)}</td><td>${ui.escape(row.commentaire || '—')}</td></tr>`)
        .join('')}</tbody></table></div>`;
}

function rendreHistorique(rows) {
    if (!rows || !rows.length) {
        historiqueBox.innerHTML = ui.state('Aucun changement de statut.');
        return;
    }

    historiqueBox.innerHTML = rows
        .map(row => `<p><strong>${ui.escape(row.statut_ancien_designation || '—')} → ${ui.escape(row.statut_nouveau_designation || '—')}</strong><br><small class="muted">${ui.date(row.date_changement)}${row.utilisateur_identifiant ? ' · ' + ui.escape(row.utilisateur_identifiant) : ''}</small>${row.commentaire ? `<br>${ui.escape(row.commentaire)}` : ''}</p>`)
        .join('');
}

function rendreActions() {
    const actions = actionsPour(demande.designation_statut);
    if (!actions.length) {
        actionsBox.innerHTML = ui.state('Aucune action disponible pour votre rôle sur ce statut.');
        return;
    }

    const coherenceBloquee = (action) => action.coherenceRequise && coherence && !coherence.conforme;

    actionsBox.innerHTML = `<div class="actions">${actions
        .map((action, index) => action.href
            ? `<a class="button ${action.variant || 'button-secondary'}" href="${ui.escape(action.href)}">${ui.escape(action.label)}</a>`
            : `<button class="button ${action.variant || 'button-secondary'}" type="button" data-action="${index}"${coherenceBloquee(action) ? ' disabled title="Cohérence non conforme"' : ''}>${ui.escape(action.label)}</button>`)
        .join('')}</div><div id="action-panel"></div>`;

    actionsBox.querySelectorAll('button[data-action]').forEach(bouton => {
        bouton.addEventListener('click', () => ouvrirAction(actions[Number(bouton.dataset.action)]));
    });
}

function ouvrirAction(action) {
    const panel = document.querySelector('#action-panel');
    const champs = (action.needs || []).map(nom => {
        const configuration = champsSpeciaux[nom] || { label: nom, type: 'text' };
        if (configuration.type === 'textarea') {
            return `<label class="field-wide">${ui.escape(configuration.label)}<textarea data-field="${ui.escape(nom)}"></textarea></label>`;
        }
        return `<label>${ui.escape(configuration.label)}<input type="${configuration.type}" data-field="${ui.escape(nom)}"${configuration.min !== undefined ? ` min="${configuration.min}"` : ''}${configuration.step !== undefined ? ` step="${configuration.step}"` : ''}></label>`;
    }).join('');

    const note = action.endpoint === 'validation-budget' ? grilleNote() : '';

    panel.innerHTML = `<div class="card" style="margin-top:1rem">
        <h3>${ui.escape(action.label)}</h3>
        ${note}
        <div class="form-grid">${champs}</div>
        <div class="actions">
            <button class="button button-secondary" type="button" id="action-cancel">Annuler</button>
            <button class="button button-primary" type="button" id="action-confirm">Confirmer</button>
        </div>
    </div>`;

    if (action.endpoint === 'validation-budget') {
        const champ = panel.querySelector('[data-field="montant_approuve"]');
        const defaut = Number(demande.remuneration_max) || (grillePoste ? grillePoste.salaireMax : '');
        if (champ && defaut !== '') champ.value = defaut;
    }

    panel.querySelector('#action-cancel').addEventListener('click', () => { panel.innerHTML = ''; });
    panel.querySelector('#action-confirm').addEventListener('click', () => executerAction(action));
}

async function executerAction(action) {
    const panel = document.querySelector('#action-panel');
    const body = { ...(action.body || {}) };

    panel.querySelectorAll('[data-field]').forEach(field => {
        const nom = field.dataset.field;
        const valeur = field.value.trim();
        if (valeur === '') return;
        body[nom] = nom === 'montant_approuve' ? Number(valeur) : valeur;
    });

    const bouton = panel.querySelector('#action-confirm');
    bouton.disabled = true;
    demandeMessage.hidden = true;

    try {
        await api.post(`/api/demandes/${demande.id_demande}/${action.endpoint}`, body);
        ui.toast('Action effectuée.');
        if (action.redirection) {
            location.href = action.redirection;
            return;
        }
        await charger();
    } catch (error) {
        afficherMessage(error.message, 'error');
        bouton.disabled = false;
    }
}

async function charger() {
    demandeDetail.innerHTML = ui.state('Chargement…');
    actionsBox.innerHTML = ui.state('Chargement…');

    try {
        const [sessionData, detail] = await Promise.all([
            auth.current(),
            api.get(`/api/demandes/${demandeId}`),
        ]);

        session = sessionData;
        demande = detail && detail.id_demande ? detail : null;

        if (!demande) {
            demandeDetail.innerHTML = ui.state('Demande introuvable.', true);
            coherenceCard.classList.add('hidden');
            actionsBox.innerHTML = '';
            validationsBox.innerHTML = '';
            historiqueBox.innerHTML = '';
            return;
        }

        const [coherenceData, validations, historique, grillesData] = await Promise.all([
            api.get(`/api/demandes/${demandeId}/coherence`),
            api.get(`/api/demandes/${demandeId}/validations`),
            api.get(`/api/demandes/${demandeId}/historique`),
            api.get(`/api/referentiels/grilles/${demande.id_poste}`).catch(() => null),
        ]);

        coherence = coherenceData && 'conforme' in coherenceData ? coherenceData : null;

        const lignesGrille = grillesData?.donnees || [];
        grillePoste = lignesGrille.find(grille => grille.actif) || lignesGrille[0] || null;

        document.querySelector('#user').textContent = `${session.identifiant} · ${session.role}`;
        rendreEntete();
        rendreDetail();
        rendreCoherence();
        rendreValidations(validations);
        rendreHistorique(historique);
        rendreActions();
    } catch (error) {
        demandeDetail.innerHTML = ui.state(error.message, true);
        actionsBox.innerHTML = '';
    }
}

if (!demandeId) {
    demandeDetail.innerHTML = ui.state('Identifiant de demande manquant.', true);
    actionsBox.innerHTML = '';
} else {
    charger();
}
