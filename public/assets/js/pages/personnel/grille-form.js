const grilleForm = document.querySelector('#grille-form');

if (grilleForm) {
    const urlParams = new URLSearchParams(location.search);
    const idGrille = urlParams.get('id') || '';
    const mode = urlParams.get('mode') || 'create';
    const isEdit = mode === 'edit' && Boolean(idGrille);

    function remplirSelect(select, lignes, placeholder, valeurFn, labelFn) {
        if (!select) return;
        const options = (lignes || []).map(ligne => {
            const valeur = valeurFn(ligne);
            const label = labelFn(ligne);
            if (valeur === undefined || valeur === null || valeur === '') return null;
            return `<option value="${valeur}">${ui.escape(String(label ?? valeur))}</option>`;
        }).filter(Boolean);
        select.innerHTML = `<option value="">${ui.escape(placeholder)}</option>` + options.join('');
    }

    function postesOptions(lignes) {
        return (lignes || []).map(ligne => ({ valeur: ligne.id ?? ligne.id_poste, label: ligne.libelle ?? ligne.intitule }));
    }

    function rangsOptions(lignes) {
        return (lignes || []).map(ligne => ({ valeur: ligne.id, label: ligne.libelle ?? ligne.designation }));
    }

    async function chargerReferentiels() {
        const [postes, rangs] = await Promise.all([
            api.get('/api/referentiels/postes'),
            api.get('/api/referentiels/rangs'),
        ]);

        const posteSelect = document.querySelector('#idPoste');
        const rangSelect = document.querySelector('#rang');

        remplirSelect(
            posteSelect,
            postesOptions(postes.donnees || postes.data || []),
            'Sélectionner un poste',
            option => option.valeur,
            option => option.label
        );
        remplirSelect(
            rangSelect,
            rangsOptions(rangs.donnees || rangs.data || []),
            'Sélectionner un rang',
            option => option.valeur,
            option => option.label
        );
    }

    function determinerActif(g) {
        if (g?.actif !== undefined && g?.actif !== null) return String(g.actif ? 1 : 0);
        const aujourdhui = new Date().toISOString().slice(0, 10);
        const debut = g?.date_debut ?? g?.dateDebut ?? '';
        const fin = g?.date_fin ?? g?.dateFin ?? '';
        if (!g?.date_debut && !g?.dateDebut) return '1';
        return (debut <= aujourdhui && (!fin || fin >= aujourdhui)) ? '1' : '0';
    }

    async function chargerGrillePourEdition() {
        if (!isEdit) return;
        const grille = await api.get(`/api/finance/grilles-salariales/${encodeURIComponent(idGrille)}`);
        const g = grille.donnees || grille.data || {};

        const setValue = (selector, valeur) => {
            const champ = document.querySelector(selector);
            if (champ) champ.value = valeur ?? '';
        };

        setValue('#idPoste', g.id_poste ?? g.idPoste ?? '');
        setValue('#rang', g.rang ?? g.idRang ?? '');
        setValue('#salaireMin', g.salaire_min ?? g.salaireMin ?? '');
        setValue('#salaireMax', g.salaire_max ?? g.salaireMax ?? '');
        setValue('#dateDebut', g.date_debut ?? g.dateDebut ?? '');
        setValue('#dateFin', g.date_fin ?? g.dateFin ?? '');
        setValue('[name="actif"]', determinerActif(g));
    }

    async function initialiser() {
        try {
            await chargerReferentiels();
        } catch (error) {
            ui.error(error);
        }

        if (isEdit) {
            try {
                await chargerGrillePourEdition();
            } catch (error) {
                ui.error(`Impossible de charger la grille : ${error?.message || error}`);
            }
        }
    }

    grilleForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(grilleForm);
        const dateFin = formData.get('dateFin') || null;

        const body = {
            id_poste: Number(formData.get('idPoste')),
            rang: Number(formData.get('rang')),
            salaire_min: Number(formData.get('salaireMin')),
            salaire_max: Number(formData.get('salaireMax')),
            date_debut: formData.get('dateDebut') || '',
            date_fin: dateFin === '' ? null : dateFin,
        };

        try {
            if (isEdit) {
                await api.patch(`/api/finance/grilles-salariales/${encodeURIComponent(idGrille)}`, body);
            } else {
                await api.post('/api/finance/grilles-salariales', body);
            }
            ui.toast('Grille salariale enregistrée.');
            location.href = '/personnel/grille';
        } catch (error) {
            ui.error(error);
        }
    });

    initialiser();
}
