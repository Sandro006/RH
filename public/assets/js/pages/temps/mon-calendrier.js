const monMois = document.querySelector('#mon-mois');
const monCalendrier = document.querySelector('#mon-calendrier');
const monMessage = document.querySelector('#mon-message');
const aujourdHuiMadagascar = new Intl.DateTimeFormat('en-CA', {timeZone: 'Indian/Antananarivo', year: 'numeric', month: '2-digit'}).format(new Date());
monMois.value = aujourdHuiMadagascar;
async function chargerMonCalendrier() {
    monCalendrier.replaceChildren(); monMessage.textContent = '';
    if (!monMois.value) return;
    try {
        const data = await api.get(`/api/temps/mon-calendrier?mois=${encodeURIComponent(monMois.value)}`);
        for (const nom of ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']) { const el = document.createElement('div'); el.className = 'mon-entete'; el.textContent = nom; monCalendrier.append(el); }
        const [annee, mois] = monMois.value.split('-').map(Number);
        const decalage = (new Date(annee, mois - 1, 1).getDay() + 6) % 7;
        for (let i = 0; i < decalage; i++) monCalendrier.append(document.createElement('div'));
        const duree = n => `${Math.floor(n / 60)}h${String(n % 60).padStart(2, '0')}`;
        for (let jour = 1; jour <= new Date(annee, mois, 0).getDate(); jour++) {
            const cle = `${monMois.value}-${String(jour).padStart(2, '0')}`, dataJour = data.jours[cle];
            const caseJour = document.createElement('div'); caseJour.className = 'mon-jour';
            const numero = document.createElement('strong'); numero.textContent = jour; caseJour.append(numero);
            const ajouter = (valeur, alerte = false) => { const el = document.createElement('span'); el.textContent = valeur; if (alerte) el.className = 'mon-alerte'; caseJour.append(el); };
            if (dataJour) {
                if (dataJour.anomalies.length) caseJour.classList.add('anomalie');
                if (dataJour.planning) ajouter(`Prévu : ${dataJour.planning.debut.slice(0, 5)}–${dataJour.planning.fin.slice(0, 5)}`);
                dataJour.pointages.forEach(p => ajouter(`${p.type === 'entree' ? 'Entrée' : 'Sortie'} : ${p.instant.slice(11, 16)}`));
                if (dataJour.pointages.length) ajouter(`Travaillé : ${duree(dataJour.minutes_travaillees)}`);
                dataJour.anomalies.forEach(a => ajouter(a, true));
            }
            monCalendrier.append(caseJour);
        }
    } catch (error) { monMessage.textContent = error.message; }
}
monMois.addEventListener('change', chargerMonCalendrier);
chargerMonCalendrier();
