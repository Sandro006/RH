const propositionId = Number(new URLSearchParams(location.search).get('id'));
const propositionValid = Number.isInteger(propositionId) && propositionId > 0;
const propositionHeading = document.querySelector('#heading');
const propositionContent = document.querySelector('#content');
const STATUTS_NEGOCIABLES = ['Envoyée', 'En négociation'];
const STATUTS_REVENDICATION_OUVERTS = ['Soumise', 'En discussion'];

let proposition = null;
let propositionTab = 'details';
let naturesRevendication = null;

function statutProposition() {
    return proposition?.statut_designation || '';
}

function nomCandidat() {
    return [proposition?.prenom, proposition?.nom].filter(Boolean).join(' ') || `Candidature #${proposition?.id_candidature || ''}`;
}

async function executerAction(url, body, message) {
    try {
        await api.post(url, body ?? {});
        ui.toast(message);
        await chargerProposition();
    } catch (error) {
        ui.error(error);
    }
}

function renderHeading() {
    const actions = ['<a class="button button-secondary" href="/selection/propositions">Retour</a>'];

    if (statutProposition() === 'À envoyer') {
        actions.push('<button class="button button-primary" type="button" id="envoyer-proposition">Envoyer au candidat</button>');
    }
    if (statutProposition() === 'Acceptée') {
        actions.push(`<a class="button button-primary" href="/personnel/salarie-form?idCandidature=${encodeURIComponent(proposition.id_candidature)}">Créer le salarié</a>`);
    }
    if (STATUTS_NEGOCIABLES.includes(statutProposition())) {
        actions.push('<button class="button button-secondary" type="button" id="expirer-proposition">Marquer expirée</button>');
    }

    propositionHeading.innerHTML = `
        <div>
            <h1>${ui.escape(nomCandidat())}</h1>
            <p class="muted">${ui.escape(proposition.offre || '—')}</p>
        </div>
        <span>${ui.badge(statutProposition())}</span>
        <div class="actions">${actions.join('')}</div>`;

    document.querySelector('#envoyer-proposition')?.addEventListener('click', () =>
        executerAction(`/api/propositions/${propositionId}/envoyer`, {}, 'Proposition envoyée.')
    );

    document.querySelector('#expirer-proposition')?.addEventListener('click', () => {
        if (!confirm('Marquer cette proposition comme expirée ?')) return;
        executerAction(`/api/propositions/${propositionId}/expirer`, {}, 'Proposition marquée expirée.');
    });
}

function renderDetails() {
    const p = proposition;
    const reponse = p.date_reponse || p.reponse
        ? `${ui.date(p.date_reponse)}${p.reponse ? ` — ${ui.escape(p.reponse)}` : ''}`
        : '—';

    let html = `<div class="form-grid">
        <p><strong>Candidat</strong><br>${ui.escape(nomCandidat())}</p>
        <p><strong>Email</strong><br>${ui.escape(p.email || '—')}</p>
        <p><strong>Offre</strong><br>${ui.escape(p.offre || '—')}</p>
        <p><strong>Rémunération proposée</strong><br>${ui.money(p.remuneration_proposee)}</p>
        <p><strong>Embauche prévue</strong><br>${ui.date(p.date_embauche_proposee)}</p>
        <p><strong>Date de la proposition</strong><br>${ui.date(p.date_proposition)}</p>
        <p class="field-wide"><strong>Réponse</strong><br>${reponse}</p>
    </div>`;

    if (STATUTS_NEGOCIABLES.includes(statutProposition())) {
        html += `<h2>Réponse du candidat</h2>
            <p class="muted">La réponse est bloquée tant que des revendications sont ouvertes.</p>
            <form id="reponse-form" class="form-grid">
                <label>Décision
                    <select name="accepte">
                        <option value="1">Accepter</option>
                        <option value="0">Refuser</option>
                    </select>
                </label>
                <label class="field-wide">Commentaire
                    <textarea name="commentaire" maxlength="3000" placeholder="Réponse du candidat…"></textarea>
                </label>
                <div class="actions field-wide">
                    <button class="button-primary" type="submit">Enregistrer la réponse</button>
                </div>
            </form>`;
    }

    if (statutProposition() === 'En négociation') {
        html += `<h2>Contre-proposition</h2>
            <form id="contre-proposition-form" class="form-grid">
                <label>Rémunération proposée (AR)
                    <input name="remunerationProposee" type="number" min="0" step="0.01" required value="${ui.escape(p.remuneration_proposee)}">
                </label>
                <label>Date d'embauche proposée
                    <input name="dateEmbaucheProposee" type="date" value="${ui.escape(p.date_embauche_proposee || '')}">
                </label>
                <div class="actions field-wide">
                    <button class="button-primary" type="submit">Envoyer la contre-proposition</button>
                </div>
            </form>`;
    }

    propositionContent.innerHTML = html;

    document.querySelector('#reponse-form')?.addEventListener('submit', event => {
        event.preventDefault();
        const form = event.currentTarget;
        executerAction(`/api/propositions/${propositionId}/repondre`, {
            accepte: form.accepte.value === '1',
            commentaire: form.commentaire.value.trim() || null,
        }, 'Réponse enregistrée.');
    });

    document.querySelector('#contre-proposition-form')?.addEventListener('submit', event => {
        event.preventDefault();
        const form = event.currentTarget;
        executerAction(`/api/propositions/${propositionId}/contre-proposer`, {
            remunerationProposee: Number(form.remunerationProposee.value),
            dateEmbaucheProposee: form.dateEmbaucheProposee.value || null,
        }, 'Contre-proposition envoyée.');
    });
}

async function chargerNatures() {
    if (naturesRevendication) return naturesRevendication;
    const response = await api.get('/api/referentiels/natures-revendication');
    naturesRevendication = response?.donnees || response?.data || [];
    return naturesRevendication;
}

function ligneRevendication(row) {
    const ouverte = STATUTS_REVENDICATION_OUVERTS.includes(row.statut);
    const actions = ouverte
        ? `<div class="actions">
                <select data-decision="${row.id_revendication}">
                    <option value="Acceptée">Acceptée</option>
                    <option value="Refusée">Refusée</option>
                    <option value="En discussion">En discussion</option>
                </select>
                <input data-reponse="${row.id_revendication}" placeholder="Réponse">
                <button class="button-secondary" type="button" data-repondre="${row.id_revendication}">Répondre</button>
                <button class="button-secondary" type="button" data-retirer="${row.id_revendication}">Retirer</button>
            </div>`
        : ui.escape(row.reponse || '—');

    return `<tr>
        <td>${ui.date(row.date_creation)}</td>
        <td>${ui.escape(row.sens || '—')}</td>
        <td>${ui.escape(row.nature || '—')}</td>
        <td>${ui.escape(row.valeur_demandee || '—')}</td>
        <td>${ui.badge(row.statut)}</td>
        <td>${ui.escape(row.commentaire || '—')}</td>
        <td>${actions}</td>
    </tr>`;
}

async function renderNegociation() {
    const [rows, natures] = await Promise.all([
        api.get(`/api/propositions/${propositionId}/revendications`),
        chargerNatures(),
    ]);

    let html = '';
    if (STATUTS_NEGOCIABLES.includes(statutProposition())) {
        html += `<h2>Nouvelle revendication</h2>
            <form id="revendication-form" class="form-grid">
                <label>Origine
                    <select name="sens">
                        <option value="CANDIDAT">Candidat</option>
                        <option value="ENTREPRISE">Entreprise</option>
                    </select>
                </label>
                <label>Nature
                    <select name="nature" required>
                        ${natures.map(nature => `<option value="${ui.escape(nature.libelle)}">${ui.escape(nature.libelle)}</option>`).join('')}
                    </select>
                </label>
                <label>Valeur demandée
                    <input name="valeurDemandee" placeholder="Montant, date, avantage…">
                </label>
                <label class="field-wide">Commentaire
                    <textarea name="commentaire" maxlength="3000"></textarea>
                </label>
                <div class="actions field-wide">
                    <button class="button-primary" type="submit">Ajouter la revendication</button>
                </div>
            </form>`;
    }

    html += '<h2>Fil de négociation</h2>';
    html += rows.length
        ? `<div class="table-wrap"><table class="data-table"><thead><tr>
                <th>Date</th><th>Origine</th><th>Nature</th><th>Valeur demandée</th><th>Statut</th><th>Commentaire</th><th>Actions</th>
            </tr></thead><tbody>${rows.map(ligneRevendication).join('')}</tbody></table></div>`
        : ui.state('Aucune revendication.');

    propositionContent.innerHTML = html;

    document.querySelector('#revendication-form')?.addEventListener('submit', event => {
        event.preventDefault();
        const form = event.currentTarget;
        executerAction(`/api/propositions/${propositionId}/revendications`, {
            sens: form.sens.value,
            nature: form.nature.value,
            valeurDemandee: form.valeurDemandee.value.trim() || null,
            commentaire: form.commentaire.value.trim() || null,
        }, 'Revendication enregistrée.');
    });

    propositionContent.querySelectorAll('[data-repondre]').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.dataset.repondre;
            const decision = propositionContent.querySelector(`[data-decision="${id}"]`).value;
            const reponse = propositionContent.querySelector(`[data-reponse="${id}"]`).value.trim() || null;
            executerAction(`/api/revendications/${id}/repondre`, { decision, reponse }, 'Réponse enregistrée.');
        });
    });

    propositionContent.querySelectorAll('[data-retirer]').forEach(button => {
        button.addEventListener('click', () => {
            if (!confirm('Retirer cette revendication ?')) return;
            executerAction(`/api/revendications/${button.dataset.retirer}/retirer`, {}, 'Revendication retirée.');
        });
    });
}

async function renderHistorique() {
    const rows = await api.get(`/api/propositions/${propositionId}/historique`);

    propositionContent.innerHTML = rows.length
        ? `<div class="table-wrap"><table class="data-table"><thead><tr>
                <th>Date</th><th>Ancien statut</th><th>Nouveau statut</th><th>Commentaire</th>
            </tr></thead><tbody>${rows.map(row => `<tr>
                <td>${ui.date(row.date_changement)}</td>
                <td>${ui.badge(row.statut_ancien_designation || '')}</td>
                <td>${ui.badge(row.statut_nouveau_designation || '')}</td>
                <td>${ui.escape(row.commentaire || '—')}</td>
            </tr>`).join('')}</tbody></table></div>`
        : ui.state('Aucun changement de statut.');
}

async function renderTab(tab) {
    if (!propositionValid) return;
    propositionTab = tab;

    if (tab === 'details') { renderDetails(); return; }

    propositionContent.innerHTML = ui.state('Chargement…');
    try {
        if (tab === 'negociation') { await renderNegociation(); return; }
        await renderHistorique();
    } catch (error) {
        propositionContent.innerHTML = ui.state(error.message, true);
    }
}

async function chargerProposition() {
    if (!propositionValid) {
        propositionContent.innerHTML = `${ui.state("Identifiant de proposition manquant. Choisissez une proposition dans la liste.", true)}<div class="actions"><a class="button button-secondary" href="/selection/propositions">Retour à la liste</a></div>`;
        document.querySelectorAll('[data-tab]').forEach(button => { button.disabled = true; });
        return;
    }

    try {
        proposition = await api.get(`/api/propositions/${propositionId}`);
        renderHeading();
        await renderTab(propositionTab);
    } catch (error) {
        propositionContent.innerHTML = ui.state(error.message, true);
        document.querySelectorAll('[data-tab]').forEach(button => { button.disabled = true; });
    }
}

document.querySelectorAll('[data-tab]').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('[data-tab]').forEach(item => item.classList.remove('active'));
    button.classList.add('active');
    renderTab(button.dataset.tab);
}));

chargerProposition();
