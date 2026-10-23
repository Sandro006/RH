const grilleList = document.querySelector('#list');
const grilleFilters = document.querySelector('#filters');

let grilles = [];

function remplirGrilleSelect(select, lignes, placeholder) {
    select.innerHTML = `<option value="">${placeholder}</option>`;
    lignes.forEach(ligne => select.append(new Option(ligne.libelle, ligne.id)));
}

async function chargerGrilleReferentiels() {
    try {
        const [departements, postes] = await Promise.all([
            api.get('/api/referentiels/departements'),
            api.get('/api/referentiels/postes'),
        ]);

        remplirGrilleSelect(grilleFilters.idDepartement, departements.donnees || [], 'Tous les départements');
        remplirGrilleSelect(grilleFilters.idPoste, postes.donnees || [], 'Tous les postes');
    } catch (error) {
        grilleList.innerHTML = ui.state(error.message, true);
    }
}

function grillesFiltrees() {
    const idDepartement = grilleFilters.idDepartement.value;
    const idPoste = grilleFilters.idPoste.value;
    const actif = grilleFilters.actif.value;

    return grilles.filter(grille =>
        (!idDepartement || String(grille.idDepartement) === idDepartement)
        && (!idPoste || String(grille.idPoste) === idPoste)
        && (actif === '' || String(grille.actif ? 1 : 0) === actif)
    );
}

function rendreGrille() {
    Table.render(grilleList, {
        rows: grillesFiltrees(),
        sort: { key: 'poste', order: 'asc' },
        empty: 'Aucune grille salariale trouvée.',
        columns: [
            { key: 'poste', label: 'Poste', render: row => ui.escape(row.poste || row.idPoste) },
            { key: 'service', label: 'Service', render: row => ui.escape(row.service || '—') },
            { key: 'departement', label: 'Département', render: row => ui.escape(row.departement || '—') },
            { key: 'rang', label: 'Rang', render: row => ui.escape(row.rang || '—') },
            { key: 'salaireMin', label: 'Salaire min', render: row => ui.money(row.salaireMin) },
            { key: 'salaireMax', label: 'Salaire max', render: row => ui.money(row.salaireMax) },
            { key: 'dateDebut', label: 'Du', render: row => ui.date(row.dateDebut) },
            { key: 'dateFin', label: 'Au', render: row => (row.dateFin ? ui.date(row.dateFin) : 'En cours') },
            { key: 'actif', label: 'État', render: row => ui.status(row.actif ? 'Active' : 'Historique') },
        ],
    });
}

async function chargerGrille() {
    grilleList.innerHTML = ui.state('Chargement…');

    try {
        const resultat = await api.get('/api/referentiels/grilles');
        grilles = resultat.donnees || [];
        rendreGrille();
    } catch (error) {
        grilleList.innerHTML = ui.state(error.message, true);
    }
}

grilleFilters.addEventListener('submit', event => {
    event.preventDefault();
    rendreGrille();
});

document.querySelector('#refresh')?.addEventListener('click', () => {
    chargerGrilleReferentiels();
    chargerGrille();
});

chargerGrilleReferentiels();
chargerGrille();
