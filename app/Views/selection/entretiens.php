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
        .status-final { background: #e5e7eb; color: #374151; }
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

    <div id="message" class="message" role="status">Chargement...</div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Date</th><th>Candidat</th><th>Offre</th><th>Recruteur</th>
                <th>Type</th><th>Statut</th><th>Action</th>
            </tr></thead>
            <tbody id="listeEntretiens"></tbody>
        </table>
    </div>
</main>
<script>
const form = document.querySelector('#filtres');
const message = document.querySelector('#message');
const tbody = document.querySelector('#listeEntretiens');

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
        const entretiens = await response.json();
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
chargerEntretiens();
</script>
</body>
</html>