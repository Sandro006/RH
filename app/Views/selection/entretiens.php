<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agenda des entretiens</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; }
        body { margin: 0; background: #f4f6f8; color: #17202a; }
        main { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .toolbar, .table-wrap { background: #fff; border: 1px solid #dce1e6; border-radius: 8px; }
        .toolbar { display: flex; flex-wrap: wrap; gap: .75rem; padding: 1rem; margin: 1rem 0; }
        label { display: flex; flex-direction: column; gap: .25rem; font-size: .85rem; font-weight: 600; }
        input, select, button { min-height: 2.25rem; padding: .4rem .6rem; border: 1px solid #b8c1ca; border-radius: 4px; background: #fff; font: inherit; }
        button { cursor: pointer; background: #155eef; color: #fff; border-color: #155eef; font-weight: 600; }
        button.secondary { background: #fff; color: #155eef; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .75rem; border-bottom: 1px solid #edf0f2; text-align: left; }
        th { background: #f8fafb; font-size: .85rem; }
        .status { display: inline-block; padding: .2rem .5rem; border-radius: 999px; font-size: .8rem; font-weight: 700; }
        .status-planifie { background: #dbeafe; color: #1e40af; }
        .status-confirme { background: #dcfce7; color: #166534; }
        .status-reporte { background: #ffedd5; color: #9a3412; }
        .status-realise { background: #dcfce7; color: #166534; }
        .status-annule { background: #fee2e2; color: #991b1b; }
        .view-switcher { display: flex; gap: .5rem; margin: 1rem 0; }
        .view-switcher button.active { background: #0f3fa8; }
        .calendar-wrap { background: #fff; border: 1px solid #dce1e6; border-radius: 8px; overflow-x: auto; }
        .calendar { display: grid; grid-template-columns: repeat(7, minmax(150px, 1fr)); min-width: 1050px; }
        .calendar-day { min-height: 20rem; border-right: 1px solid #edf0f2; }
        .calendar-day:last-child { border-right: 0; }
        .calendar-day-header { padding: .75rem; background: #f8fafb; border-bottom: 1px solid #edf0f2; font-weight: 700; }
        .calendar-day-header small { display: block; color: #52606d; font-weight: 400; }
        .calendar-events { display: grid; gap: .5rem; padding: .75rem; }
        .calendar-event { padding: .6rem; border-left: 4px solid #155eef; border-radius: 4px; background: #eff6ff; font-size: .85rem; }
        .calendar-event.status-confirme { border-left-color: #16a34a; background: #f0fdf4; }
        .calendar-event.status-reporte { border-left-color: #ea580c; background: #fff7ed; }
        .calendar-event.status-realise { border-left-color: #16a34a; background: #f0fdf4; }
        .calendar-event.status-annule { border-left-color: #dc2626; background: #fef2f2; }
        .calendar-event strong, .calendar-event span { display: block; }
        .calendar-event span { color: #52606d; margin-top: .2rem; }
        .modal-backdrop { position: fixed; inset: 0; display: grid; place-items: center; padding: 1rem; background: rgba(23, 32, 42, .55); z-index: 10; }
        .modal { width: min(720px, 100%); max-height: 90vh; overflow-y: auto; background: #fff; border-radius: 8px; padding: 1.25rem; box-shadow: 0 1rem 3rem rgba(0, 0, 0, .25); }
        .modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
        .modal-header h2 { margin: 0; }
        .modal-close { background: transparent; color: #17202a; border-color: transparent; font-size: 1.25rem; padding: .2rem .5rem; }
        .detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem 1rem; margin: 1rem 0; }
        .detail-grid dt { color: #52606d; font-size: .85rem; }
        .detail-grid dd { margin: .15rem 0 0; font-weight: 600; }
        .modal-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem; }
        .modal textarea, .modal input { width: 100%; box-sizing: border-box; }
        .modal label { margin-top: .75rem; }
        .history { display: grid; gap: .5rem; padding: 0; list-style: none; }
        .history li { padding: .65rem; background: #f8fafb; border-left: 3px solid #b8c1ca; }
        .action-link { margin-right: .35rem; margin-bottom: .35rem; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem 1rem; }
        .form-grid label.full-width { grid-column: 1 / -1; }
        .required::after { content: ' *'; color: #b42318; }
        .message { padding: 1rem; color: #52606d; }
        .error { color: #b42318; }
        @media (max-width: 760px) { .table-wrap { overflow-x: auto; } table { min-width: 850px; } }
    </style>
</head>
<body>
<main>
    <h1>Agenda des entretiens</h1>
    <button type="button" id="ouvrir-planification">Planifier un entretien</button>

    <form id="filtres" class="toolbar">
        <label>Statut
            <select id="statut">
                <option value="">Tous</option>
                <option>Planifié</option>
                <option>Confirmé</option>
                <option>Reporté</option>
                <option>Réalisé</option>
                <option>Annulé</option>
            </select>
        </label>
        <label>Recruteur (ID)
            <input id="idRecruteur" type="number" min="1">
        </label>
        <label>Offre (ID)
            <input id="idOffre" type="number" min="1">
        </label>
        <label>Du
            <input id="dateDebut" type="date">
        </label>
        <label>Au
            <input id="dateFin" type="date">
        </label>
        <button type="submit">Filtrer</button>
        <button type="button" class="secondary" id="reinitialiser">Réinitialiser</button>
    </form>

    <div class="view-switcher" role="group" aria-label="Mode d'affichage">
        <button type="button" class="active" id="vue-liste">Liste</button>
        <button type="button" class="secondary" id="vue-calendrier">Calendrier</button>
    </div>

    <div id="message" class="message" role="status">Chargement...</div>
    <div class="table-wrap" id="conteneur-liste">
        <table>
            <thead><tr>
                <th>Date</th><th>Candidat</th><th>Offre</th><th>Recruteur</th>
                <th>Type</th><th>Statut</th><th>Action</th>
            </tr></thead>
            <tbody id="listeEntretiens"></tbody>
        </table>
    </div>
    <div class="calendar-wrap" id="conteneur-calendrier" hidden>
        <div class="calendar" id="calendrier"></div>
    </div>

    <div class="modal-backdrop" id="fiche-modal" hidden>
        <section class="modal" role="dialog" aria-modal="true" aria-labelledby="fiche-titre">
            <div class="modal-header">
                <h2 id="fiche-titre">Fiche entretien</h2>
                <button type="button" class="modal-close" id="fermer-fiche" aria-label="Fermer">×</button>
            </div>
            <div id="fiche-contenu" class="message">Chargement...</div>
        </section>
    </div>

    <div class="modal-backdrop" id="action-modal" hidden>
        <form class="modal" id="action-form">
            <div class="modal-header">
                <h2 id="action-titre">Action</h2>
                <button type="button" class="modal-close" id="fermer-action" aria-label="Fermer">×</button>
            </div>
            <div id="action-champs"></div>
            <div class="modal-actions">
                <button type="submit" id="valider-action">Valider</button>
                <button type="button" class="secondary" id="annuler-action">Annuler</button>
            </div>
        </form>
    </div>

    <div class="modal-backdrop" id="planification-modal" hidden>
        <form class="modal" id="planification-form">
            <div class="modal-header">
                <h2>Planifier un entretien</h2>
                <button type="button" class="modal-close" id="fermer-planification" aria-label="Fermer">×</button>
            </div>
            <p class="message">Les identifiants doivent correspondre aux références présentes en base.</p>
            <div class="form-grid">
                <label class="required">Candidature
                    <input type="number" name="idCandidature" min="1" required>
                </label>
                <label class="required">Recruteur
                    <input type="number" name="idRecruteur" min="1" required>
                </label>
                <label class="required">Date et heure
                    <input type="datetime-local" name="dateEntretien" id="date-planification" required>
                </label>
                <label class="required">Type d’entretien
                    <input type="number" name="typeEntretien" min="1" required>
                </label>
            </div>
            <div class="modal-actions">
                <button type="submit">Planifier</button>
                <button type="button" class="secondary" id="annuler-planification">Annuler</button>
            </div>
        </form>
    </div>
</main>
<script>
const form = document.querySelector('#filtres');
const message = document.querySelector('#message');
const tbody = document.querySelector('#listeEntretiens');
const conteneurListe = document.querySelector('#conteneur-liste');
const conteneurCalendrier = document.querySelector('#conteneur-calendrier');
const calendrier = document.querySelector('#calendrier');
const boutonListe = document.querySelector('#vue-liste');
const boutonCalendrier = document.querySelector('#vue-calendrier');
const ficheModal = document.querySelector('#fiche-modal');
const ficheContenu = document.querySelector('#fiche-contenu');
const actionModal = document.querySelector('#action-modal');
const actionForm = document.querySelector('#action-form');
const actionTitre = document.querySelector('#action-titre');
const actionChamps = document.querySelector('#action-champs');
const planificationModal = document.querySelector('#planification-modal');
const planificationForm = document.querySelector('#planification-form');
let actionEnCours = null;
let entretiens = [];

function valeur(id) { return document.querySelector(`#${id}`).value; }

function afficherCellule(ligne, texte) {
    const cellule = document.createElement('td');
    cellule.textContent = texte || '-';
    ligne.appendChild(cellule);
}

function afficherStatut(ligne, entretien) {
    const cellule = document.createElement('td');
    const badge = document.createElement('span');
    const classe = (entretien.statut || '').toLowerCase().replaceAll('é', 'e');
    badge.className = `status status-${classe}`;
    badge.textContent = entretien.statut || 'Inconnu';
    cellule.appendChild(badge);
    ligne.appendChild(cellule);
}

function afficherActions(ligne, entretien) {
    const cellule = document.createElement('td');
    ajouterAction(cellule, 'Ouvrir', () => ouvrirFiche(entretien.id_entretien), 'secondary');
    if (entretien.statut === 'Planifié') {
        ajouterAction(cellule, 'Confirmer', () => confirmer(entretien.id_entretien));
    }
    if (['Planifié', 'Confirmé'].includes(entretien.statut)) {
        ajouterAction(cellule, 'Reporter', () => ouvrirAction('reporter', entretien.id_entretien));
    }
    if (['Planifié', 'Confirmé', 'Reporté'].includes(entretien.statut)) {
        ajouterAction(cellule, 'Annuler', () => ouvrirAction('annuler', entretien.id_entretien), 'secondary');
    }
    ligne.appendChild(cellule);
}

function ajouterAction(conteneur, texte, action, classe = '') {
    const bouton = document.createElement('button');
    bouton.type = 'button';
    bouton.className = `action-link ${classe}`;
    bouton.textContent = texte;
    bouton.addEventListener('click', action);
    conteneur.appendChild(bouton);
}

function classeStatut(statut) {
    return (statut || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function afficherCalendrier() {
    calendrier.replaceChildren();
    const jours = new Map();
    entretiens.forEach(entretien => {
        const date = new Date(entretien.date_entretien);
        if (Number.isNaN(date.getTime())) return;
        const cle = date.toISOString().slice(0, 10);
        if (!jours.has(cle)) jours.set(cle, []);
        jours.get(cle).push(entretien);
    });

    const dates = [...jours.keys()].sort();
    if (!dates.length) {
        const vide = document.createElement('div');
        vide.className = 'message';
        vide.textContent = 'Aucun entretien à afficher dans le calendrier.';
        calendrier.appendChild(vide);
        return;
    }

    const debut = new Date(`${dates[0]}T00:00:00`);
    debut.setDate(debut.getDate() - ((debut.getDay() + 6) % 7));
    for (let i = 0; i < 7; i++) {
        const date = new Date(debut);
        date.setDate(debut.getDate() + i);
        const cle = date.toISOString().slice(0, 10);
        const jour = document.createElement('section');
        jour.className = 'calendar-day';

        const titre = document.createElement('div');
        titre.className = 'calendar-day-header';
        titre.textContent = date.toLocaleDateString('fr-FR', { weekday: 'long', day: '2-digit', month: '2-digit' });
        titre.appendChild(document.createElement('small')).textContent = date.toLocaleDateString('fr-FR', { year: 'numeric' });
        jour.appendChild(titre);

        const evenements = document.createElement('div');
        evenements.className = 'calendar-events';
        (jours.get(cle) || []).sort((a, b) => new Date(a.date_entretien) - new Date(b.date_entretien))
            .forEach(entretien => {
                const evenement = document.createElement('article');
                evenement.className = `calendar-event status-${classeStatut(entretien.statut)}`;
                const heure = new Date(entretien.date_entretien).toLocaleTimeString('fr-FR', {
                    hour: '2-digit',
                    minute: '2-digit',
                });
                const candidat = `${entretien.prenom_candidat || ''} ${entretien.nom_candidat || ''}`.trim() || 'Candidat inconnu';
                const titre = document.createElement('strong');
                titre.textContent = `${heure} — ${candidat}`;
                const statut = document.createElement('span');
                statut.textContent = entretien.statut || 'Statut inconnu';
                evenement.append(titre, statut);
                evenement.addEventListener('click', () => ouvrirFiche(entretien.id_entretien));
                evenement.tabIndex = 0;
                evenement.setAttribute('role', 'button');
                evenement.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') ouvrirFiche(entretien.id_entretien);
                });
                evenements.appendChild(evenement);
            });
        jour.appendChild(evenements);
        calendrier.appendChild(jour);
    }
}

function changerVue(vue) {
    const calendrierActif = vue === 'calendrier';
    conteneurListe.hidden = calendrierActif;
    conteneurCalendrier.hidden = !calendrierActif;
    boutonListe.classList.toggle('active', !calendrierActif);
    boutonCalendrier.classList.toggle('active', calendrierActif);
    boutonListe.classList.toggle('secondary', calendrierActif);
    boutonCalendrier.classList.toggle('secondary', !calendrierActif);
    if (calendrierActif) afficherCalendrier();
}

function fermerModal(modal) {
    modal.hidden = true;
}

function afficherTexte(parent, valeur, valeurParDefaut = '-') {
    const element = document.createElement('dd');
    element.textContent = valeur || valeurParDefaut;
    parent.appendChild(element);
}

function ajouterBoutonAction(parent, texte, action, classe = '') {
    const bouton = document.createElement('button');
    bouton.type = 'button';
    bouton.className = classe;
    bouton.textContent = texte;
    bouton.addEventListener('click', action);
    parent.appendChild(bouton);
}

function actionsPourFiche(parent, entretien) {
    const actions = document.createElement('div');
    actions.className = 'modal-actions';
    if (entretien.statut === 'Planifié') {
        ajouterBoutonAction(actions, 'Confirmer', () => confirmer(entretien.id_entretien));
    }
    if (['Planifié', 'Confirmé'].includes(entretien.statut)) {
        ajouterBoutonAction(actions, 'Reporter', () => ouvrirAction('reporter', entretien.id_entretien));
        ajouterBoutonAction(actions, 'Réaliser', () => ouvrirAction('realiser', entretien.id_entretien));
    }
    if (['Planifié', 'Confirmé', 'Reporté'].includes(entretien.statut)) {
        ajouterBoutonAction(actions, 'Annuler', () => ouvrirAction('annuler', entretien.id_entretien), 'secondary');
    }
    if (actions.children.length) parent.appendChild(actions);
}

async function ouvrirFiche(id) {
    ficheModal.hidden = false;
    ficheContenu.replaceChildren();
    const chargement = document.createElement('p');
    chargement.textContent = 'Chargement...';
    ficheContenu.appendChild(chargement);

    try {
        const response = await fetch(`/api/entretiens/${id}`);
        if (!response.ok) throw new Error('Impossible de charger la fiche.');
        const entretien = await response.json();
        ficheContenu.replaceChildren();

        const details = document.createElement('dl');
        details.className = 'detail-grid';
        [
            ['Candidat', `${entretien.prenom_candidat || ''} ${entretien.nom_candidat || ''}`.trim()],
            ['Offre', entretien.offre],
            ['Date', entretien.date_entretien ? new Date(entretien.date_entretien).toLocaleString('fr-FR') : null],
            ['Recruteur', entretien.recruteur],
            ['Type', entretien.type_entretien],
            ['Statut', entretien.statut],
            ['Compte rendu', entretien.compte_rendu],
        ].forEach(([libelle, valeur]) => {
            const titre = document.createElement('dt');
            titre.textContent = libelle;
            details.appendChild(titre);
            afficherTexte(details, valeur);
        });
        ficheContenu.appendChild(details);
        actionsPourFiche(ficheContenu, entretien);

        const historiqueTitre = document.createElement('h3');
        historiqueTitre.textContent = 'Historique';
        ficheContenu.appendChild(historiqueTitre);
        const historique = await fetch(`/api/entretiens/${id}/historique`);
        if (!historique.ok) throw new Error('Impossible de charger l’historique.');
        const lignes = await historique.json();
        const liste = document.createElement('ul');
        liste.className = 'history';
        lignes.forEach(ligne => {
            const element = document.createElement('li');
            const date = ligne.date_changement
                ? new Date(ligne.date_changement).toLocaleString('fr-FR')
                : '-';
            element.textContent = `${date} — ${ligne.statut_ancien || 'Aucun'} → ${ligne.statut_nouveau || '-'}${ligne.commentaire ? ` (${ligne.commentaire})` : ''}`;
            liste.appendChild(element);
        });
        if (!lignes.length) {
            const element = document.createElement('li');
            element.textContent = 'Aucun historique.';
            liste.appendChild(element);
        }
        ficheContenu.appendChild(liste);
    } catch (error) {
        ficheContenu.replaceChildren();
        const erreur = document.createElement('p');
        erreur.className = 'error';
        erreur.textContent = error.message;
        ficheContenu.appendChild(erreur);
    }
}

function ouvrirAction(action, id) {
    actionEnCours = { action, id };
    actionChamps.replaceChildren();
    actionTitre.textContent = {
        reporter: 'Reporter l’entretien',
        annuler: 'Annuler l’entretien',
        realiser: 'Réaliser l’entretien',
    }[action];

    if (action === 'reporter') {
        const dateLabel = document.createElement('label');
        dateLabel.textContent = 'Nouvelle date et heure';
        const date = document.createElement('input');
        date.type = 'datetime-local';
        date.name = 'dateEntretien';
        date.required = true;
        dateLabel.appendChild(date);
        actionChamps.appendChild(dateLabel);
    }

    if (action === 'realiser') {
        const compteRenduLabel = document.createElement('label');
        compteRenduLabel.className = 'required';
        compteRenduLabel.textContent = 'Compte rendu';
        const compteRendu = document.createElement('textarea');
        compteRendu.name = 'compteRendu';
        compteRendu.rows = 8;
        compteRendu.minLength = 10;
        compteRendu.maxLength = 5000;
        compteRendu.required = true;
        compteRendu.placeholder = 'Saisissez le compte rendu de l’entretien...';
        compteRenduLabel.appendChild(compteRendu);
        actionChamps.appendChild(compteRenduLabel);
    }

    if (action !== 'realiser') {
        const motifLabel = document.createElement('label');
        motifLabel.textContent = 'Motif (facultatif)';
        const motif = document.createElement('textarea');
        motif.name = 'motif';
        motif.rows = 3;
        motif.maxLength = 1000;
        motifLabel.appendChild(motif);
        actionChamps.appendChild(motifLabel);
    }
    actionModal.hidden = false;
}

async function executerAction(event) {
    event.preventDefault();
    if (!actionEnCours) return;
    const donnees = Object.fromEntries(new FormData(actionForm).entries());
    const { action, id } = actionEnCours;
    try {
        const response = await fetch(`/api/entretiens/${id}/${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(donnees),
        });
        if (!response.ok) throw new Error(`L’action « ${action} » a échoué.`);
        fermerModal(actionModal);
        fermerModal(ficheModal);
        await chargerEntretiens();
    } catch (error) {
        window.alert(error.message);
    }
}

async function planifier(event) {
    event.preventDefault();
    const donnees = Object.fromEntries(new FormData(planificationForm).entries());
    if (new Date(donnees.dateEntretien) <= new Date()) {
        window.alert('La date de l’entretien doit être dans le futur.');
        return;
    }
    try {
        const response = await fetch('/api/entretiens', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(donnees),
        });
        if (!response.ok) throw new Error('La planification a échoué.');
        fermerModal(planificationModal);
        planificationForm.reset();
        await chargerEntretiens();
    } catch (error) {
        window.alert(error.message);
    }
}

async function chargerEntretiens() {
    message.className = 'message';
    message.textContent = 'Chargement...';
    tbody.replaceChildren();
    const params = new URLSearchParams();
    ['statut', 'idRecruteur', 'idOffre', 'dateDebut', 'dateFin'].forEach(id => {
        if (valeur(id)) params.set(id, valeur(id));
    });

    try {
        const response = await fetch(`/api/entretiens?${params}`);
        if (!response.ok) throw new Error('Impossible de charger les entretiens.');
        entretiens = await response.json();
        message.textContent = entretiens.length ? `${entretiens.length} entretien(s)` : 'Aucun entretien trouvé.';
        entretiens.forEach(entretien => {
            const ligne = document.createElement('tr');
            afficherCellule(ligne, new Date(entretien.date_entretien).toLocaleString('fr-FR'));
            afficherCellule(ligne, `${entretien.prenom_candidat || ''} ${entretien.nom_candidat || ''}`.trim());
            afficherCellule(ligne, entretien.offre);
            afficherCellule(ligne, entretien.recruteur);
            afficherCellule(ligne, entretien.type_entretien);
            afficherStatut(ligne, entretien);
            afficherActions(ligne, entretien);
            tbody.appendChild(ligne);
        });
        if (!conteneurCalendrier.hidden) afficherCalendrier();
    } catch (error) {
        message.className = 'message error';
        message.textContent = error.message;
    }
}

async function confirmer(id) {
    const accord = window.confirm('Veux-tu vraiment confirmer cet entretien ?');
    if (!accord) return;

    try {
        const response = await fetch(`/api/entretiens/${id}/confirmer`, { method: 'POST' });
        if (!response.ok) throw new Error('La confirmation a échoué.');
        fermerModal(ficheModal);
        await chargerEntretiens();
    } catch (error) {
        window.alert(error.message);
    }
}

form.addEventListener('submit', event => { event.preventDefault(); chargerEntretiens(); });
document.querySelector('#reinitialiser').addEventListener('click', () => { form.reset(); chargerEntretiens(); });
boutonListe.addEventListener('click', () => changerVue('liste'));
boutonCalendrier.addEventListener('click', () => changerVue('calendrier'));
document.querySelector('#fermer-fiche').addEventListener('click', () => fermerModal(ficheModal));
document.querySelector('#fermer-action').addEventListener('click', () => fermerModal(actionModal));
document.querySelector('#annuler-action').addEventListener('click', () => fermerModal(actionModal));
actionForm.addEventListener('submit', executerAction);
document.querySelector('#ouvrir-planification').addEventListener('click', () => {
    const date = document.querySelector('#date-planification');
    const maintenant = new Date();
    maintenant.setMinutes(maintenant.getMinutes() - maintenant.getTimezoneOffset());
    date.min = maintenant.toISOString().slice(0, 16);
    planificationModal.hidden = false;
});
document.querySelector('#fermer-planification').addEventListener('click', () => fermerModal(planificationModal));
document.querySelector('#annuler-planification').addEventListener('click', () => fermerModal(planificationModal));
planificationForm.addEventListener('submit', planifier);
ficheModal.addEventListener('click', event => {
    if (event.target === ficheModal) fermerModal(ficheModal);
});
actionModal.addEventListener('click', event => {
    if (event.target === actionModal) fermerModal(actionModal);
});
planificationModal.addEventListener('click', event => {
    if (event.target === planificationModal) fermerModal(planificationModal);
});
chargerEntretiens();
</script>
</body>
</html>