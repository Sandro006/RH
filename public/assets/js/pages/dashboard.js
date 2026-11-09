async function chargerDashboard() {
    try {
        const [session, data] = await Promise.all([auth.current(), api.get('/api/dashboard')]);
        document.querySelector('#user').textContent = `${session.identifiant} · ${session.role}`;

        rendreProfil(data.utilisateur);

        if (data.role === 'salarie') {
            adapterDashboardSalarie(data);
        }

        const valeurs = data.indicateurs || {};
        Object.keys(valeurs).forEach(key => {
            const element = document.querySelector(`[data-metric="${key}"]`);
            if (element) {
                const valeur = valeurs[key];
                element.textContent = typeof valeur === 'number' ? Number(valeur).toLocaleString('fr-FR', { maximumFractionDigits: 2 }) : String(valeur);
            }
        });

        const actions = (data.actionsEnAttente || []).flat();
        document.querySelector('#actions').innerHTML = actions.length
            ? `<div class="actions-grid">${actions.map(action => `
                <a class="action-card" href="${ui.escape(action.lien || '#')}">
                    <div class="action-count">${Number(action.nombre || 0)}</div>
                    <div class="action-label">${ui.escape(action.libelle || 'Action')}</div>
                </a>
            `).join('')}</div>`
            : '<div class="state">Aucune action en attente. Votre équipe est à jour.</div>';
    } catch (error) {
        document.querySelector('#actions').innerHTML = ui.state(error.message, true);
    }
}

// Affiche les infos de l'utilisateur connecté (nom, poste, service…) en-tête du tableau de bord.
function rendreProfil(utilisateur) {
    if (!utilisateur) return;
    const set = (id, valeur) => {
        const el = document.getElementById(id);
        if (el) el.textContent = valeur || '—';
    };

    const nom = utilisateur.nomComplet || utilisateur.nom || utilisateur.identifiant;
    set('profil-nom', nom);
    const initiales = String(nom || '?').trim().split(/\s+/).slice(0, 2).map((mot) => mot[0]).join('').toUpperCase() || '?';
    const avatar = document.getElementById('profil-initiales');
    if (avatar) avatar.textContent = initiales;
    const poste = [utilisateur.poste, utilisateur.service ? `— ${utilisateur.service}` : ''].filter(Boolean).join(' ');
    set('profil-poste', poste || utilisateur.role);
    set('profil-matricule', utilisateur.matricule);
    set('profil-service', utilisateur.service);
    set('profil-departement', utilisateur.departement);
    set('profil-email', utilisateur.email);
    set('profil-telephone', utilisateur.telephone);
    set('profil-role', utilisateur.role);
}

// Tableau de bord salarié « Mon service » : cartes personnelles (congés + service).
function adapterDashboardSalarie(data) {
    const LABELS = {
        demandesEnAttente: 'Mes demandes en attente',
        offresPubliees: 'Jours disponibles',
        candidatures: "Absents aujourd'hui (mon service)",
        tachesOnboarding: 'Mes tâches d’onboarding',
    };
    const CARTE_A_MASQUER = ['entretiensAVenir', 'propositionsEnCours', 'documentsExpirant', 'documentsExpires'];

    Object.entries(LABELS).forEach(([key, label]) => {
        document.querySelectorAll(`[data-metric="${key}"]`).forEach((element) => {
            const petit = element.closest('.metric')?.querySelector('small');
            if (petit) petit.textContent = label;
        });
    });

    CARTE_A_MASQUER.forEach((key) => {
        document.querySelectorAll(`[data-metric="${key}"]`).forEach((element) => {
            const carte = element.closest('.metric');
            if (carte) carte.style.display = 'none';
        });
    });

    if (data.indicateurs?.service) {
        const sousTitre = document.querySelector('.subtitle');
        if (sousTitre) sousTitre.textContent = `Votre service : ${data.indicateurs.service}.`;
    }
}

chargerDashboard();
