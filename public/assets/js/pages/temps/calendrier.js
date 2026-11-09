const role = document.querySelector('#role');
const mois = document.querySelector('#mois');
const calendrier = document.querySelector('#calendrier');
const message = document.querySelector('#message');
const planning = document.querySelector('#planning');
const nomsRoles = {admin: 'Administrateur', rh: 'Ressources humaines', manager: 'Manager', recruteur: 'Recruteur', direction: 'Direction', controle_gestion: 'Contrôle de gestion', salarie: 'Salarié'};
let salariesParRole = [];
let selectionJour = '';
let requeteCalendrier = 0;
const maintenant = new Date();
mois.value = `${maintenant.getFullYear()}-${String(maintenant.getMonth() + 1).padStart(2, '0')}`;
function choisirJour(jour) {
    selectionJour = jour;
    planning.elements.jour.value = jour;
    calendrier.querySelectorAll('button[data-jour]').forEach(el => {
        const actif = el.dataset.jour === jour;
        el.classList.toggle('selectionne', actif);
        el.setAttribute('aria-pressed', String(actif));
    });
}
async function charger() {
    const requete = ++requeteCalendrier;
    calendrier.replaceChildren(); message.textContent = '';
    if (!role.value || !mois.value) return;
    const salaries = salariesParRole.filter(row => row.role === role.value);
    const moisSelectionne = mois.value;
    try {
        const resultats = await Promise.all(salaries.map(s => api.get(`/api/temps/calendrier?salarie=${encodeURIComponent(s.id_salarie)}&mois=${encodeURIComponent(moisSelectionne)}`)));
        if (requete !== requeteCalendrier) return;
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
            if (presents) ligne(`${presents} présence(s) pointée(s)`, 'presence');
            if (anomalies) ligne(`${anomalies} pointage(s) à vérifier`, 'alerte');
            calendrier.append(bouton);
        }
        message.textContent = `${salaries.length} salarié(s) lié(s) à un compte actif pour ce rôle.`;
    } catch (error) { if (requete === requeteCalendrier) message.textContent = error.message; }
}
role.addEventListener('change', () => { selectionJour = ''; planning.elements.jour.value = ''; charger(); });
mois.addEventListener('change', () => { selectionJour = ''; planning.elements.jour.value = ''; charger(); });
planning.addEventListener('submit', async event => {
    event.preventDefault();
    if (!role.value) { message.textContent = 'Sélectionnez un rôle.'; return; }
    const selection = role.value;
    try {
        const donnees = Object.fromEntries(new FormData(planning)); donnees.role = selection;
        const resultat = await api.post('/api/temps/planning-role', donnees);
        if (role.value === selection) await charger();
        message.textContent = `${resultat.crees} planning(s) créé(s) sur ${resultat.total}. ${resultat.existants} planning(s) déjà existant(s) conservé(s).`;
    } catch (error) { message.textContent = error.message; }
});
(async () => {
    try {
        const lignes = await api.get('/api/temps/roles-salaries');
        const uniques = new Map(); lignes.forEach(ligne => uniques.set(`${ligne.role}:${ligne.id_salarie}`, ligne));
        salariesParRole = [...uniques.values()];
        role.replaceChildren(new Option('Sélectionner un rôle', ''));
        [...new Set(salariesParRole.map(ligne => ligne.role))].forEach(valeur => role.add(new Option(nomsRoles[valeur] || valeur, valeur)));
        if (!salariesParRole.length) message.textContent = 'Aucun salarié lié à un compte actif.';
    } catch (error) { message.textContent = error.message; }
})();
