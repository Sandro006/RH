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
        .message { padding: 1rem; color: #52606d; }
        .error { color: #b42318; }
        @media (max-width: 760px) { .table-wrap { overflow-x: auto; } table { min-width: 850px; } }
    </style>
</head>
<body>
<main>
    <h1>Agenda des entretiens</h1>

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
    if (entretien.statut === 'Planifié') {
        const bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.textContent = 'Confirmer';
        bouton.addEventListener('click', () => confirmer(entretien.id_entretien));
        cellule.appendChild(bouton);
    } else {
        cellule.textContent = 'Aucune';
    }
    ligne.appendChild(cellule);
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

    const response = await fetch(`/api/entretiens/${id}/confirmer`, { method: 'POST' });
    if (!response.ok) {
        window.alert('La confirmation a échoué.');
        return;
    }
    await chargerEntretiens();
}

form.addEventListener('submit', event => { event.preventDefault(); chargerEntretiens(); });
document.querySelector('#reinitialiser').addEventListener('click', () => { form.reset(); chargerEntretiens(); });
boutonListe.addEventListener('click', () => changerVue('liste'));
boutonCalendrier.addEventListener('click', () => changerVue('calendrier'));
chargerEntretiens();
</script>
</body>
</html>