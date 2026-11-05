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
    const donnees = grillesFiltrees();
    
    if (donnees.length === 0) {
        grilleList.innerHTML = '<div class="state">Aucune grille salariale trouvée.</div>';
        return;
    }

    // Grouper par département
    const parDepartement = donnees.reduce((acc, grille) => {
        const dept = grille.departement || 'Sans département';
        if (!acc[dept]) {
            acc[dept] = [];
        }
        acc[dept].push(grille);
        return acc;
    }, {});

    let html = '<div class="grille-departements">';
    
    for (const [departement, grilles] of Object.entries(parDepartement)) {
        html += `
            <div class="grille-departement">
                <h3 class="grille-departement-title">${ui.escape(departement)}</h3>
                <div class="grille-postes">
        `;
        
        grilles.forEach(grille => {
            const etatClass = grille.actif ? 'active' : 'historique';
            const etatLabel = grille.actif ? 'Active' : 'Historique';
            const periode = grille.dateFin 
                ? `${ui.date(grille.dateDebut)} - ${ui.date(grille.dateFin)}`
                : `${ui.date(grille.dateDebut)} - En cours`;
            
            html += `
                <div class="grille-poste ${etatClass}">
                    <div class="grille-poste-header">
                        <span class="grille-poste-titre">${ui.escape(grille.poste || grille.idPoste)}</span>
                        <span class="grille-poste-rang">${ui.escape(grille.rang || '—')}</span>
                    </div>
                    <div class="grille-poste-service">${ui.escape(grille.service || '—')}</div>
                    <div class="grille-poste-salaire">
                        <span class="salaire-min">${ui.money(grille.salaireMin)}</span>
                        <span class="salaire-separator">—</span>
                        <span class="salaire-max">${ui.money(grille.salaireMax)}</span>
                    </div>
                    <div class="grille-poste-periode">${periode}</div>
                    <div class="grille-poste-etat">
                        <span class="badge ${grille.actif ? 'success' : 'warning'}">${etatLabel}</span>
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    html += '</div>';
    grilleList.innerHTML = html;
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
