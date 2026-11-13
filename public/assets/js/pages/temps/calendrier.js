const role = document.querySelector('#role');
const mois = document.querySelector('#mois');
const filtreSalarie = document.querySelector('#filtre-salarie');
const heuresForm = document.querySelector('#heures-form');
const heuresMessage = document.querySelector('#heures-message');
const calendrier = document.querySelector('#calendrier');
const message = document.querySelector('#message');
const planning = document.querySelector('#planning');
const planningMessage = document.querySelector('#planning-message');
const nomsRoles = {admin: 'Administrateur', rh: 'Ressources humaines', manager: 'Manager', recruteur: 'Recruteur', direction: 'Direction', controle_gestion: 'Contrôle de gestion', salarie: 'Salarié'};
let salariesParRole = [];
let selectionJour = '';
let requeteCalendrier = 0;
let detailDonnees = [];
const detailJour = document.querySelector('#detail-jour');
const detailContenu = document.querySelector('#detail-contenu');
const maintenant = new Date();
mois.value = `${maintenant.getFullYear()}-${String(maintenant.getMonth() + 1).padStart(2, '0')}`;
function choisirJour(jour) {
    selectionJour = jour;
    detailJour.hidden = false; document.querySelector('#detail-titre').textContent = 'Détail du ' + jour.split('-').reverse().join('/'); detailContenu.replaceChildren();
    if (!detailDonnees.length) detailContenu.textContent = 'Aucun salarié pour cette sélection.';
    for (const item of detailDonnees) {
        const bloc = document.createElement('div'); const titre = document.createElement('strong'); titre.textContent = (item.salarie.prenom || '') + ' ' + (item.salarie.nom || '') + ' · ' + (item.salarie.matricule || ''); bloc.append(titre);
        const ajouter = texte => { const p = document.createElement('p'); p.textContent = texte; bloc.append(p); };
        const d = item.data.jours[jour];
        if (d && d.planning) ajouter('Planning : ' + d.planning.debut.slice(0,5) + '–' + d.planning.fin.slice(0,5));
        if (d && d.pointages.length) d.pointages.forEach(p => ajouter((p.type === 'entree' ? 'Entrée : ' : 'Sortie : ') + p.instant.slice(11,16)));
        else ajouter('Aucun pointage enregistré.');
        if (d) { if (d.pointages.length) ajouter('Temps travaillé : ' + Math.floor(d.minutes_travaillees / 60) + ' h ' + String(d.minutes_travaillees % 60).padStart(2,'0')); d.anomalies.forEach(a => ajouter('À vérifier : ' + a)); }
        if (!item.data.absences_disponibles) ajouter('Données d’absence indisponibles.');
        else { const abs = item.data.absences.filter(a => a.date_debut <= jour && a.date_fin >= jour); ajouter(abs.length ? 'Absence enregistrée (' + abs.length + ').' : 'Aucune absence enregistrée.'); }
        detailContenu.append(bloc);
    }
    planning.elements.jour.value = jour; heuresForm.elements.jour.value = jour;
    calendrier.querySelectorAll('button[data-jour]').forEach(el => {
        const actif = el.dataset.jour === jour;
        el.classList.toggle('selectionne', actif);
        el.setAttribute('aria-pressed', String(actif));
    });
}
async function charger() {
    const requete = ++requeteCalendrier;
    calendrier.replaceChildren(); message.textContent = ''; detailJour.hidden = true; detailDonnees = [];
    if (!role.value || !mois.value) return;
    const salaries = salariesParRole.filter(row => row.role === role.value && (!filtreSalarie.value || String(row.id_salarie) === filtreSalarie.value));
    const moisSelectionne = mois.value;
    try {
        const resultats = await Promise.all(salaries.map(s => api.get(`/api/temps/calendrier?salarie=${encodeURIComponent(s.id_salarie)}&mois=${encodeURIComponent(moisSelectionne)}`)));
        if (requete !== requeteCalendrier) return;
        detailDonnees = resultats.map((data, i) => ({data, salarie: salaries[i]}));
        for (const nom of ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']) { const el = document.createElement('div'); el.className = 'entete'; el.textContent = nom; calendrier.append(el); }
        const [annee, m] = moisSelectionne.split('-').map(Number);
        const decalage = (new Date(annee, m - 1, 1).getDay() + 6) % 7;
        for (let i = 0; i < decalage; i++) calendrier.append(document.createElement('div'));
        for (let i = 1; i <= new Date(annee, m, 0).getDate(); i++) {
            const jour = `${moisSelectionne}-${String(i).padStart(2, '0')}`;
            const donnees = resultats.map(r => r.jours[jour]).filter(Boolean);
            const plans = donnees.filter(d => d.planning);
            const presents = donnees.filter(d => d.pointages.some(p => p.type === 'entree')).length;
            const anomalies = donnees.filter(d => d.anomalies.length).length;
            const bouton = document.createElement('button'); bouton.type = 'button'; bouton.dataset.jour = jour;
            bouton.setAttribute('aria-label', `Jour ${i}`); bouton.setAttribute('aria-pressed', String(selectionJour === jour));
            bouton.classList.toggle('selectionne', selectionJour === jour); bouton.classList.toggle('anomalie', anomalies > 0);
            bouton.addEventListener('click', () => choisirJour(jour));
            const numero = document.createElement('span'); numero.className = 'temps-jour-numero'; numero.textContent = i; bouton.append(numero);
            const ligne = (texte, classe) => { const el = document.createElement('span'); el.className = `temps-ligne ${classe || ''}`; el.textContent = texte; bouton.append(el); };
            if (plans.length) {
                ligne(`${plans.length} / ${salaries.length} salarié(s) planifié(s)`, 'horaire');
                const horaires = [...new Set(plans.map(d => `${d.planning.debut.slice(0, 5)}–${d.planning.fin.slice(0, 5)} · ${d.planning.regime}`))];
                horaires.forEach(h => ligne(h));
            }
            const suppl = donnees.reduce((n, d) => n + Number(d.heures_supplementaires_minutes || 0), 0);
            if (suppl) ligne(`Heures supplémentaires : ${Math.floor(suppl / 60)}h${String(suppl % 60).padStart(2, '0')} (total du rôle)`, 'bilan');
            const saisies = donnees.reduce((n, d) => n + Number(d.heures_saisies_minutes || 0), 0);
            if (saisies) ligne(`Heures saisies : ${Math.floor(saisies / 60)}h${String(saisies % 60).padStart(2, '0')}`, 'bilan');
            if (presents) ligne(`${presents} présence(s) pointée(s)`, 'presence');
            if (anomalies) ligne(`${anomalies} pointage(s) à vérifier`, 'alerte');
            calendrier.append(bouton);
        }
        message.textContent = `${salaries.length} salarié(s) lié(s) à un compte actif pour ce rôle.`;
    } catch (error) { if (requete === requeteCalendrier) message.textContent = error.message; }
}
role.addEventListener('change', () => { filtreSalarie.replaceChildren(new Option('Tous les salariés du rôle', '')); salariesParRole.filter(row => row.role === role.value).forEach(row => filtreSalarie.add(new Option(`${row.prenom || ''} ${row.nom || ''} · ${row.matricule || ''}`, row.id_salarie))); selectionJour = ''; planning.elements.jour.value = ''; charger(); });
filtreSalarie.addEventListener('change', charger);
mois.addEventListener('change', () => { selectionJour = ''; planning.elements.jour.value = ''; charger(); });
planning.addEventListener('submit', async event => {
    event.preventDefault();
    if (!role.value) { planningMessage.textContent = 'Sélectionnez un rôle en haut du calendrier.'; return; }
    const selection = role.value;
    try {
        const donnees = Object.fromEntries(new FormData(planning)); donnees.role = selection; donnees.mode = event.submitter?.value || 'mois'; if (donnees.jour.slice(0, 7) !== mois.value) throw new Error('La date doit appartenir au mois affiché.');
        const resultat = await api.post('/api/temps/planning-role', donnees);
        if (role.value === selection) await charger();
        planningMessage.textContent = `${resultat.crees} planning(s) enregistré(s) sur ${resultat.total}. ${resultat.existants} planning(s) existant(s) conservé(s).`;
    } catch (error) { message.textContent = error.message; }
});
(async () => {
    try {
        const lignes = await api.get('/api/temps/roles-salaries');
        const uniques = new Map(); lignes.forEach(ligne => uniques.set(`${ligne.role}:${ligne.id_salarie}`, ligne));
        salariesParRole = [...uniques.values()];
        role.replaceChildren(new Option('Sélectionner un rôle', ''));
        [...new Set([...salariesParRole.map(ligne => ligne.role), 'salarie'])].forEach(valeur => role.add(new Option(nomsRoles[valeur] || valeur, valeur)));
        if (!salariesParRole.length) message.textContent = 'Aucun salarié lié à un compte actif.';
    } catch (error) { message.textContent = error.message; }
})();

heuresForm.addEventListener('submit', async event => {
    event.preventDefault(); heuresMessage.textContent = '';
    if (!filtreSalarie.value) { heuresMessage.textContent = 'Sélectionnez un salarié dans le filtre.'; return; }
    try {
        const data = Object.fromEntries(new FormData(heuresForm)); data.id_salarie = filtreSalarie.value;
        if (data.jour.slice(0, 7) !== mois.value) throw new Error('Choisissez une date du mois affiché.');
        await api.post('/api/temps/heures-saisies', data); await charger(); heuresMessage.textContent = 'Heures enregistrées.';
    } catch (error) { heuresMessage.textContent = error.message; }
});
