/* eslint-env browser */

// Planning des congés (Bloc 7, écran 4) — calendrier mensuel.
// GET /api/conges/planning?id_service=&debut=&fin= -> v_planning_absences.
// La grille affiche 1 mois (lun–dim) ; navigation mois précédent/suivant.

(() => {
  const API_BASE = '/api/conges';
  const listEl = document.getElementById('planning-list');
  const calEl = document.getElementById('calendrier');
  const titreEl = document.getElementById('cal-titre');
  const btnMoinsEl = document.getElementById('cal-moins');
  const btnPlusEl = document.getElementById('cal-plus');
  const btnAujEl = document.getElementById('cal-auj');
  const formEl = document.getElementById('planning-filtres');
  const serviceEl = document.getElementById('planning-service'); // caché (id résolu)
  const serviceTexteEl = document.getElementById('planning-service-texte');
  const debutEl = document.getElementById('planning-debut');
  const finEl = document.getElementById('planning-fin');

  const JOURS = ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'];
  const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

  let moisCourant = new Date();
  moisCourant.setDate(1);

  async function appelApi(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
      let message = `Erreur ${response.status}`;
      try {
        const corps = await response.json();
        if (corps && (corps.message || corps.error)) message = corps.message || corps.error;
      } catch (_) {
        // non JSON
      }
      throw new Error(message);
    }
    return response.json();
  }

  // Services (GET /api/referentiels/services → {id service, libelle, departement}).
  // Remplit le <datalist> et résout le nom tapé -> id_service dans le champ caché.
  async function chargerServices() {
    const datalist = document.getElementById('services-datalist');
    if (!datalist) return;

    try {
      const reponse = await appelApi('/api/referentiels/services');
      const services = Array.isArray(reponse?.donnees) ? reponse.donnees : [];
      const liste = services.map((s) => ({
        idService: s.id,
        libelle: [s.libelle, s.departement].filter(Boolean).join(' — '),
      }));

      datalist.innerHTML = liste
        .map((s) => `<option value="${escapeHtml(s.libelle)}" data-id-service="${s.idService}"></option>`)
        .join('');

      const resoudre = () => {
        const valeur = serviceTexteEl.value.trim().toLowerCase();
        const trouve = liste.find((s) => s.libelle.toLowerCase() === valeur);
        serviceEl.value = trouve ? trouve.idService : '';
      };
      serviceTexteEl.addEventListener('input', resoudre);
      serviceTexteEl.addEventListener('change', resoudre);
    } catch (_) {
      // sans la liste, le service de l'utilisateur est utilisé par défaut côté API
    }
  }

  function isoDuJour(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  function classeType(code) {
    switch ((code ?? '').toUpperCase()) {
      case 'CONGE': return 'conge';
      case 'MALADIE': return 'maladie';
      case 'ABSENCE_INJUSTIFIEE': return 'injustifiee';
      default: return 'autre';
    }
  }

  function formaterDate(iso) {
    if (!iso) return '—';
    const date = new Date(`${iso}T00:00:00`);
    return Number.isNaN(date.getTime()) ? iso : date.toLocaleDateString('fr-FR');
  }

  function debutFinMois(date) {
    const a = new Date(date.getFullYear(), date.getMonth(), 1);
    const b = new Date(date.getFullYear(), date.getMonth() + 1, 0);
    return [isoDuJour(a), isoDuJour(b)];
  }

  function synchroFiltres() {
    const [debut, fin] = debutFinMois(moisCourant);
    debutEl.value = debut;
    finEl.value = fin;
    titreEl.textContent = `${MOIS[moisCourant.getMonth()]} ${moisCourant.getFullYear()}`;
  }

  async function charger() {
    listEl.innerHTML = '<p class="muted">Chargement…</p>';

    try {
      const params = new URLSearchParams();
      const service = Number(serviceEl.value);
      if (service > 0) params.set('id_service', String(service));
      params.set('debut', debutEl.value || debutFinMois(moisCourant)[0]);
      params.set('fin', finEl.value || debutFinMois(moisCourant)[1]);

      const lignes = await appelApi(`${API_BASE}/planning?${params.toString()}`);

      const parJour = new Map();
      (Array.isArray(lignes) ? lignes : []).forEach((l) => {
        const jour = l.jour ?? '';
        if (!parJour.has(jour)) parJour.set(jour, []);
        parJour.get(jour).push(l);
      });

      rendreCalendrier(parJour);
      rendreListe(parJour);
    } catch (err) {
      calEl.innerHTML = `<p class="error">Impossible de charger le planning : ${escapeHtml(err.message)}</p>`;
      listEl.innerHTML = '';
    }
  }

  function rendreCalendrier(parJour) {
    const aujourdHui = isoDuJour(new Date());
    const premierDuMois = new Date(moisCourant.getFullYear(), moisCourant.getMonth(), 1);
    const nbJours = new Date(moisCourant.getFullYear(), moisCourant.getMonth() + 1, 0).getDate();
    // Index lun=0 .. dim=6 (JS getDay : dim=0...sam=6)
    const decalage = (premierDuMois.getDay() + 6) % 7;

    let html = JOURS.map((j) => `<div class="cal-head">${j}</div>`).join('');

    for (let i = 0; i < decalage; i += 1) {
      html += '<div class="cal-cell vide" aria-hidden="true"></div>';
    }

    for (let jour = 1; jour <= nbJours; jour += 1) {
      const date = new Date(moisCourant.getFullYear(), moisCourant.getMonth(), jour);
      const iso = isoDuJour(date);
      const veille = [0, 6].includes(date.getDay());
      const absents = parJour.get(iso) ?? [];
      const liste = absents.map((a) => `
        <span class="cal-chip ${classeType(a.code_type_absence)}" title="${escapeHtml(`${a.prenom ?? ''} ${a.nom ?? ''}`.trim())} — ${escapeHtml(a.libelle_type_absence ?? '')}">
          ${escapeHtml(`${a.prenom?.[0] ?? ''}${a.nom?.[0] ?? ''}` || `${a.prenom ?? ''} ${a.nom ?? ''}`.trim())}
        </span>
      `).join('');

      html += `
        <div class="cal-cell${veille ? ' we' : ''}${iso === aujourdHui ? ' ajd' : ''}" role="gridcell">
          <span class="cal-jour">${jour}</span>
          ${liste}
        </div>
      `;
    }

    calEl.innerHTML = html;
  }

  function rendreListe(parJour) {
    if (parJour.size === 0) {
      listEl.innerHTML = '<p class="muted">Aucune absence ce mois-ci.</p>';
      return;
    }

    listEl.innerHTML = [...parJour.entries()]
      .sort((a, b) => (a[0] < b[0] ? -1 : 1))
      .map(([jour, absents]) => `
        <article class="planning-jour">
          <strong>${escapeHtml(formaterDate(jour))}</strong>
          ${absents.map((a) => `
            <div class="planning-row">
              <span>${escapeHtml(`${a.prenom ?? ''} ${a.nom ?? ''}`.trim() || `Salarié #${a.id_salarie ?? ''}`)} — ${escapeHtml(a.libelle_type_absence ?? a.code_type_absence ?? 'Absent')}</span>
              ${a.justifiee ? '<span class="badge">justifiée</span>' : '<span class="badge statut-badge statut-bloquee">à régulariser</span>'}
            </div>
          `).join('')}
        </article>
      `).join('');
  }

  function escapeHtml(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
  }

  function moisDecaler(delta) {
    moisCourant = new Date(moisCourant.getFullYear(), moisCourant.getMonth() + delta, 1);
    synchroFiltres();
    charger();
  }

  btnMoinsEl.addEventListener('click', () => moisDecaler(-1));
  btnPlusEl.addEventListener('click', () => moisDecaler(1));
  btnAujEl.addEventListener('click', () => {
    const aujourdHui = new Date();
    moisCourant = new Date(aujourdHui.getFullYear(), aujourdHui.getMonth(), 1);
    synchroFiltres();
    charger();
  });

  formEl.addEventListener('submit', (evenement) => {
    evenement.preventDefault();
    if (debutEl.value) {
      const d = new Date(`${debutEl.value}T00:00:00`);
      if (!Number.isNaN(d.getTime())) {
        moisCourant = new Date(d.getFullYear(), d.getMonth(), 1);
      }
    }
    synchroFiltres();
    charger();
  });

  synchroFiltres();
  chargerServices();
  charger();
})();
