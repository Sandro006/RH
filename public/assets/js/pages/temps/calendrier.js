const salarie = document.querySelector('#salarie');
const mois = document.querySelector('#mois');
const calendrier = document.querySelector('#calendrier');
const message = document.querySelector('#message');
const planning = document.querySelector('#planning');
const pointage = document.querySelector('#pointage');
const localDate = new Date();
mois.value = `${localDate.getFullYear()}-${String(localDate.getMonth() + 1).padStart(2, '0')}`;
let jourSelectionne = '';
function afficherErreur(e) { message.textContent = e.message; }
function duree(n) { return `${Math.floor(n / 60)}h${String(n % 60).padStart(2, '0')}`; }
function choisirJour(jour) {
    jourSelectionne = jour;
    planning.elements.jour.value = jour;
    pointage.elements.instant.value = `${jour}T09:00`;
    calendrier.querySelectorAll('button[data-jour]').forEach(el => {
        const actif = el.dataset.jour === jour;
        el.classList.toggle('selectionne', actif);
        el.setAttribute('aria-pressed', String(actif));
    });
}
async function charger() {
    calendrier.replaceChildren(); message.textContent = ''; donneesCalendrier = null; estimer();
    if (!salarie.value || !mois.value) return;
    try {
        const data = await api.get(`/api/temps/calendrier?salarie=${encodeURIComponent(salarie.value)}&mois=${encodeURIComponent(mois.value)}`);
        donneesCalendrier = data; estimer();
        for (const nom of ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']) { const el = document.createElement('div'); el.className = 'entete'; el.textContent = nom; calendrier.append(el); }
        const [annee, m] = mois.value.split('-').map(Number);
        const premier = new Date(annee, m - 1, 1); const decalage = (premier.getDay() + 6) % 7;
        for (let i = 0; i < decalage; i++) calendrier.append(document.createElement('div'));
        const nombre = new Date(annee, m, 0).getDate();
        for (let i = 1; i <= nombre; i++) {
            const jour = `${mois.value}-${String(i).padStart(2, '0')}`; const d = data.jours[jour];
            const bouton = document.createElement('button'); bouton.type = 'button'; bouton.dataset.jour = jour;
            bouton.setAttribute('aria-label', `Jour ${i}`); bouton.setAttribute('aria-pressed', String(jourSelectionne === jour));
            bouton.classList.toggle('selectionne', jourSelectionne === jour); bouton.addEventListener('click', () => choisirJour(jour));
            const numero = document.createElement('span'); numero.className = 'temps-jour-numero'; numero.textContent = i; bouton.append(numero);
            const ajouterLigne = (texte, classe = '') => { const ligne = document.createElement('span'); ligne.className = `temps-ligne ${classe}`; ligne.textContent = texte; bouton.append(ligne); };
            if (d) {
                if (d.anomalies.length) bouton.classList.add('anomalie');
                if (d.pointages.some(p => p.type === 'entree')) ajouterLigne('Présence pointée', 'presence');
                else if (d.planning && jour < new Date().toLocaleDateString('fr-CA')) ajouterLigne('Présence à vérifier', 'alerte');
                else if (d.planning) ajouterLigne('Journée planifiée', 'presence');
                if (d.planning) { ajouterLigne(`${d.planning.debut.slice(0, 5)} – ${d.planning.fin.slice(0, 5)}`, 'horaire'); ajouterLigne(d.planning.regime); }
                d.pointages.forEach(p => ajouterLigne(`${p.type === 'entree' ? 'Entrée' : 'Sortie'} · ${p.instant.slice(11, 16)}`));
                if (d.pointages.length) {
                    ajouterLigne(`Travaillé : ${duree(d.minutes_travaillees)}`, 'bilan');
                    if (d.retard_minutes) ajouterLigne(`Retard : ${duree(d.retard_minutes)}`, 'bilan');
                    if (d.heures_supplementaires_minutes) ajouterLigne(`Suppl. : ${duree(d.heures_supplementaires_minutes)}`, 'bilan');
                }
                d.anomalies.forEach(anomalie => ajouterLigne(anomalie, 'alerte'));
            }
            calendrier.append(bouton);
        }
    } catch (e) { afficherErreur(e); }
}
salarie.addEventListener('change', charger); mois.addEventListener('change', charger);
for (const [form, endpoint] of [[planning, 'planning'], [pointage, 'pointage']]) form.addEventListener('submit', async e => {
    e.preventDefault();
    if (!salarie.value) { message.textContent = 'Sélectionnez un salarié.'; return; }
    try { const data = Object.fromEntries(new FormData(form)); data.id_salarie = salarie.value; await api.post(`/api/temps/${endpoint}`, data); await charger(); message.textContent = 'Enregistrement effectué.'; } catch (err) { afficherErreur(err); }
});
(async () => { try { const rows = await api.get('/api/salaries'); salarie.replaceChildren(new Option('Sélectionner un salarié', '')); rows.forEach(row => salarie.add(new Option(`${row.prenom || ''} ${row.nom || ''} · ${row.matricule || ''}`, row.id_salarie))); } catch (e) { afficherErreur(e); } })();

let donneesCalendrier = null;
const estimationResultat = document.querySelector('#estimation-resultat');
const formTaux = document.querySelector('#form-taux');
const tauxMessage = document.querySelector('#taux-message');
const listeTaux = document.querySelector('#liste-taux');
const nombreFr = valeur => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(valeur);
const normaliser = valeur => valeur.trim().toLocaleLowerCase('fr');
function estimer() {
    estimationResultat.replaceChildren();
    const texte = (message, classe = '') => { const el = document.createElement('p'); el.className = classe; el.textContent = message; estimationResultat.append(el); };
    if (!donneesCalendrier) { texte('Sélectionnez un salarié et un mois pour commencer.'); return; }
    const joursSup = Object.entries(donneesCalendrier.jours).filter(([, d]) => Number(d.heures_supplementaires_minutes) > 0);
    if (!joursSup.length) { texte('Aucune heure supplémentaire calculée pour ce mois.'); return; }
    let minutes = 0; const totaux = new Map(), exclus = [], details = [];
    for (const [jour, data] of joursSup) {
        const supp = Number(data.heures_supplementaires_minutes), date = jour.split('-').reverse().join('/');
        if (data.anomalies.length) { exclus.push(`${date} : pointage à vérifier`); continue; }
        const taux = donneesCalendrier.taux.find(t => normaliser(t.regime) === normaliser(data.planning.regime) && t.date_debut <= jour);
        if (!taux) { exclus.push(`${date} : aucun taux applicable pour « ${data.planning.regime} »`); continue; }
        const montant = supp / 60 * Number(taux.taux_horaire); minutes += supp;
        totaux.set(taux.devise, (totaux.get(taux.devise) || 0) + montant);
        details.push(`${date} : ${duree(supp)} × ${nombreFr(Number(taux.taux_horaire))} ${taux.devise}/h = ${nombreFr(montant)} ${taux.devise} (${taux.regime}, taux du ${taux.date_debut.split('-').reverse().join('/')})`);
    }
    if (details.length) {
        const titre = document.createElement('strong'); titre.textContent = `Montant brut estimé : ${Array.from(totaux, ([devise, montant]) => `${nombreFr(montant)} ${devise}`).join(' + ')}`; estimationResultat.append(titre);
        texte(`Pour ${duree(minutes)} prises en compte.`);
        const liste = document.createElement('ul'); details.forEach(detail => { const li = document.createElement('li'); li.textContent = detail; liste.append(li); }); estimationResultat.append(liste);
    } else texte('Montant indisponible : aucune journée ne peut être calculée avec les données actuelles.', 'avertissement');
    if (exclus.length) texte(`Journées exclues : ${exclus.join(' ; ')}.`, 'avertissement');
}
async function chargerTaux() {
    try {
        const taux = await api.get('/api/temps/taux'); listeTaux.replaceChildren();
        const datalist = document.querySelector('#regimes-taux'); datalist.replaceChildren();
        [...new Set(taux.map(t => t.regime))].forEach(regime => datalist.append(new Option(regime, regime)));
        if (!taux.length) { listeTaux.textContent = 'Aucun taux enregistré. Ajoutez une première ligne.'; return; }
        const table = document.createElement('table');
        const entete = document.createElement('tr'); ['Régime', 'À partir du', 'Taux par heure'].forEach(t => { const th = document.createElement('th'); th.textContent = t; entete.append(th); }); table.append(entete);
        taux.forEach(t => { const tr = document.createElement('tr'); [t.regime, t.date_debut.split('-').reverse().join('/'), `${nombreFr(Number(t.taux_horaire))} ${t.devise}`].forEach(val => { const td = document.createElement('td'); td.textContent = val; tr.append(td); }); table.append(tr); }); listeTaux.append(table);
    } catch (e) { tauxMessage.textContent = e.message; }
}
formTaux.addEventListener('submit', async e => {
    e.preventDefault(); tauxMessage.textContent = '';
    try { await api.post('/api/temps/taux', Object.fromEntries(new FormData(formTaux))); formTaux.reset(); await chargerTaux(); if (salarie.value) await charger(); tauxMessage.textContent = 'Taux enregistré.'; }
    catch (err) { tauxMessage.textContent = err.message; }
});
chargerTaux();
