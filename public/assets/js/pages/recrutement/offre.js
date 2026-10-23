const offreId = new URLSearchParams(location.search).get('id');
const offreHeading = document.querySelector('#heading');
const offreDetail = document.querySelector('#detail');
const actionsBox = document.querySelector('#actions');
const candidaturesBox = document.querySelector('#candidatures');
const historiqueBox = document.querySelector('#historique');
const offreMessage = document.querySelector('#message');

let session;
let offre;

const canaux = ['Site carrières', 'Site Interne', 'LinkedIn', 'Facebook', 'JobBoard', 'Cabinet', 'Réseaux sociaux', 'Autre'];

const champsSpeciaux = {
    motif: { label: "Motif / commentaire", type: 'textarea' },
    commentaire: { label: 'Commentaire', type: 'textarea' },
    canal_diffusion: { label: 'Canaux de diffusion', type: 'select-multiple', options: canaux },
    date_cloture: { label: 'Date de clôture', type: 'date' },
};

function afficherMessage(texte, type) {
    offreMessage.textContent = texte;
    offreMessage.className = type || '';
    offreMessage.hidden = false;
}

function peut(roles) {
    return session?.role === 'admin' || roles.includes(session?.role);
}

function peutGerer() {
    return peut(['rh', 'recruteur']);
}

function actionsPour(statut) {
    const modifier = { label: 'Modifier', href: `/recrutement/offre-form?id=${offre.id_offre}`, variant: 'button-secondary' };
    const publier = { label: "Publier l'offre", endpoint: 'publier', needs: ['canal_diffusion', 'date_cloture'], body: {}, variant: 'button-primary' };
    const suspendre = { label: 'Suspendre', endpoint: 'suspendre', needs: ['motif'], body: {}, variant: 'button-secondary' };
    const reprendre = { label: 'Reprendre', endpoint: 'reprendre', needs: [], body: {}, variant: 'button-primary' };
    const clore = { label: 'Clôturer', endpoint: 'clore', needs: [], body: {}, variant: 'button-secondary' };
    const annuler = { label: "Annuler l'offre", endpoint: 'annuler', needs: ['motif'], body: {}, variant: 'button-danger' };

    if (!peutGerer()) return [];

    switch (statut) {
        case 'Brouillon':
            return [modifier, publier, annuler];
        case 'Publiée':
            return [suspendre, clore, annuler];
        case 'Suspendue':
            return [reprendre, clore, annuler];
        default:
            return [];
    }
}

function rendreEntete() {
    offreHeading.innerHTML = `<div>
        <h1>${ui.escape(offre.titre || 'Offre')}</h1>
        <p class="muted">Offre n°${offre.id_offre}</p>
    </div>
    <div class="actions">${ui.status(offre.statut_designation || offre.statut)}</div>`;
}

function rendreDetail() {
    const lignes = [
        ['Titre', ui.escape(offre.titre || '—')],
        ['Poste', ui.escape(offre.poste || '—')],
        ['Service', ui.escape(offre.service || '—')],
        ['Département', ui.escape(offre.departement || '—')],
        ['Demande', offre.id_demande ? `<a href="/recrutement/demande?id=${offre.id_demande}">n°${offre.id_demande}</a>` : '—'],
        ['Canaux de diffusion', ui.escape(offre.canal_diffusion || '—')],
        ['Publiée le', ui.date(offre.date_publication)],
        ['Clôture', ui.date(offre.date_cloture)],
        ['Créée le', ui.date(offre.date_creation)],
        ['Candidatures', ui.escape(offre.nb_candidatures ?? 0)],
        ['Description', ui.escape(offre.description || '—')],
    ];

    offreDetail.innerHTML = `<div class="form-grid">${lignes.map(([label, valeur]) => `<p><strong>${label}</strong><br>${valeur}</p>`).join('')}</div>`;
}

function rendreCandidatures(rows) {
    if (!rows || !rows.length) {
        candidaturesBox.innerHTML = ui.state('Aucune candidature pour cette offre.');
        return;
    }

    candidaturesBox.innerHTML = `<div class="table-wrap"><table><thead><tr><th>N°</th><th>Candidat</th><th>Origine</th><th>Statut</th><th>Date</th></tr></thead><tbody>${rows
        .map(row => `<tr><td>${ui.escape(row.id_candidature)}</td><td>${ui.escape([row.prenom, row.nom].filter(Boolean).join(' ') || '—')}</td><td>${ui.escape(row.origine_candidature || '—')}</td><td>${ui.status(row.statut_designation || '—')}</td><td>${ui.date(row.date_candidature)}</td></tr>`)
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
    const actions = actionsPour(offre.statut_designation);
    if (!actions.length) {
        actionsBox.innerHTML = ui.state('Aucune action disponible pour ce statut ou votre rôle.');
        return;
    }

    actionsBox.innerHTML = `<div class="actions">${actions
        .map((action, index) => action.href
            ? `<a class="button ${action.variant || 'button-secondary'}" href="${ui.escape(action.href)}">${ui.escape(action.label)}</a>`
            : `<button class="button ${action.variant || 'button-secondary'}" type="button" data-action="${index}">${ui.escape(action.label)}</button>`)
        .join('')}</div><div id="action-panel"></div>`;

    actionsBox.querySelectorAll('button[data-action]').forEach(bouton => {
        bouton.addEventListener('click', () => ouvrirAction(actions[Number(bouton.dataset.action)]));
    });
}

function champHtml(nom) {
    const config = champsSpeciaux[nom] || { label: nom, type: 'text' };
    if (config.type === 'textarea') {
        return `<label class="field-wide">${ui.escape(config.label)}<textarea data-field="${ui.escape(nom)}"></textarea></label>`;
    }
    if (config.type === 'select-multiple') {
        const options = config.options.map(option => `<option value="${ui.escape(option)}">${ui.escape(option)}</option>`).join('');
        return `<label class="field-wide">${ui.escape(config.label)}<select multiple data-field="${ui.escape(nom)}" data-multiple="1">${options}</select></label>`;
    }
    return `<label>${ui.escape(config.label)}<input type="${config.type}" data-field="${ui.escape(nom)}"></label>`;
}

function ouvrirAction(action) {
    const panel = document.querySelector('#action-panel');
    const champs = (action.needs || []).map(champHtml).join('');

    panel.innerHTML = `<div class="card" style="margin-top:1rem">
        <h3>${ui.escape(action.label)}</h3>
        <div class="form-grid">${champs}</div>
        <div class="actions">
            <button class="button button-secondary" type="button" id="action-cancel">Annuler</button>
            <button class="button button-primary" type="button" id="action-confirm">Confirmer</button>
        </div>
    </div>`;

    const canal = panel.querySelector('[data-field="canal_diffusion"]');
    if (canal) {
        canal.querySelectorAll('option').forEach(option => {
            option.selected = (offre.canal_diffusion || '').split(',').map(value => value.trim()).includes(option.value);
        });
    }

    const cloture = panel.querySelector('[data-field="date_cloture"]');
    if (cloture) {
        const aujourdhui = new Date().toISOString().slice(0, 10);
        cloture.min = aujourdhui;
        if (offre.date_cloture) cloture.value = String(offre.date_cloture).slice(0, 10);
    }

    panel.querySelector('#action-cancel').addEventListener('click', () => { panel.innerHTML = ''; });
    panel.querySelector('#action-confirm').addEventListener('click', () => executerAction(action));
}

async function executerAction(action) {
    const panel = document.querySelector('#action-panel');
    const body = { ...(action.body || {}) };

    panel.querySelectorAll('[data-field]').forEach(field => {
        const nom = field.dataset.field;
        if (field.dataset.multiple) {
            const valeurs = Array.from(field.selectedOptions).map(option => option.value);
            if (valeurs.length) body[nom] = valeurs.join(',');
            return;
        }
        const valeur = field.value.trim();
        if (valeur === '') return;
        body[nom] = valeur;
    });

    const bouton = panel.querySelector('#action-confirm');
    bouton.disabled = true;
    offreMessage.hidden = true;

    try {
        await api.post(`/api/offres/${offre.id_offre}/${action.endpoint}`, body);
        ui.toast('Action effectuée.');
        await charger();
    } catch (error) {
        afficherMessage(error.message, 'error');
        bouton.disabled = false;
    }
}

async function charger() {
    offreDetail.innerHTML = ui.state('Chargement…');
    actionsBox.innerHTML = ui.state('Chargement…');

    try {
        const [sessionData, detail] = await Promise.all([
            auth.current(),
            api.get(`/api/offres/${offreId}`),
        ]);

        session = sessionData;
        offre = detail && detail.id_offre ? detail : null;

        if (!offre) {
            offreDetail.innerHTML = ui.state('Offre introuvable.', true);
            actionsBox.innerHTML = '';
            candidaturesBox.innerHTML = '';
            historiqueBox.innerHTML = '';
            return;
        }

        const [candidatures, historique] = await Promise.all([
            api.get(`/api/offres/${offreId}/candidatures`),
            api.get(`/api/offres/${offreId}/historique`),
        ]);

        document.querySelector('#user').textContent = `${session.identifiant} · ${session.role}`;
        rendreEntete();
        rendreDetail();
        rendreCandidatures(candidatures);
        rendreHistorique(historique);
        rendreActions();
    } catch (error) {
        offreDetail.innerHTML = ui.state(error.message, true);
        actionsBox.innerHTML = '';
    }
}

if (!offreId) {
    offreDetail.innerHTML = ui.state("Identifiant d'offre manquant.", true);
    actionsBox.innerHTML = '';
} else {
    charger();
}
