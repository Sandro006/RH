/* eslint-env browser */

// Soldes (RH) — Bloc 7, écran 5.
// - GET /api/conges/soldes/tous , GET /api/conges/alertes
// - POST /api/conges/soldes/ajuster , POST /api/conges/cloturer-annee

(() => {
  const API_BASE = '/api/conges';
  const alertesEl = document.getElementById('alertes-list');
  const soldesEl = document.getElementById('soldes-list');
  const filtresEl = document.getElementById('soldes-filtres');
  const anneeEl = document.getElementById('annee');
  const typeEl = document.getElementById('type_conge');
  const rechercheEl = document.getElementById('recherche');
  const ajustementForm = document.getElementById('ajustement-form');
  const ajustementAnneeEl = document.getElementById('ajustement-annee');
  const ajustementSalarieEl = document.getElementById('ajustement-salarie'); // caché (id résolu)
  const ajustementSalarieTexteEl = document.getElementById('ajustement-salarie-texte');
  const clotureForm = document.getElementById('cloture-form');
  const clotureAnneeEl = document.getElementById('cloture-annee');
  const feedbackEl = document.getElementById('conges-rh-feedback');

  async function appelApi(url, options = {}) {
    const response = await fetch(url, {
      headers: { Accept: 'application/json', ...(options.headers || {}) },
      ...options,
    });
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

  function formaterNombre(n) {
    if (n === null || n === undefined) return '—';
    const v = Number(n);
    return Number.isNaN(v) ? '—' : v.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function escapeHtml(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
  }

  async function chargerEmployes() {
    const datalist = document.getElementById('employes-datalist');
    if (!datalist) return;

    try {
      const reponse = await appelApi('/api/referentiels/employes');
      const employes = Array.isArray(reponse?.donnees) ? reponse.donnees : [];
      const liste = employes
        .filter((e) => e.idSalarie !== null && e.idSalarie !== undefined)
        .map((e) => ({
          idSalarie: e.idSalarie,
          libelle: [e.libelle, e.poste].filter(Boolean).join(' — '),
        }));

      datalist.innerHTML = liste
        .map((e) => `<option value="${escapeHtml(e.libelle)}" data-id-salarie="${e.idSalarie}"></option>`)
        .join('');

      const resoudre = () => {
        const valeur = ajustementSalarieTexteEl.value.trim().toLowerCase();
        const trouve = liste.find((e) => e.libelle.toLowerCase() === valeur);
        ajustementSalarieEl.value = trouve ? trouve.idSalarie : '';
      };
      ajustementSalarieTexteEl.addEventListener('input', resoudre);
      ajustementSalarieTexteEl.addEventListener('change', resoudre);
    } catch (_) {
      // sans la liste, la saisie d'id reste possible
    }
  }

  async function chargerTypes() {
    try {
      const types = await appelApi(`${API_BASE}/types`);
      [typeEl, document.getElementById('ajustement-type')].forEach((select, i) => {
        if (!select) return;
        const actuel = select.value;
        select.innerHTML = i === 0 ? '<option value="">Tous</option>' : '<option value="">Sélectionner…</option>';
        (Array.isArray(types) ? types : []).forEach((t) => {
          const o = document.createElement('option');
          o.value = String(t.id_type_conge);
          o.textContent = `${t.libelle || t.code} (${t.code})`;
          select.appendChild(o);
        });
        select.value = actuel;
      });
    } catch (_) {
      // types non bloquants
    }
  }

  async function chargerAlertes() {
    alertesEl.innerHTML = '<p class="muted">Chargement…</p>';

    try {
      const alertes = await appelApi(`${API_BASE}/alertes`);
      if (!Array.isArray(alertes) || alertes.length === 0) {
        alertesEl.innerHTML = '<p class="muted">Aucune alerte.</p>';
        return;
      }
      alertesEl.innerHTML = alertes.map((a) => `
        <article class="solde-row">
          <div class="solde-info">
            <strong>${escapeHtml(`${a.prenom ?? ''} ${a.nom ?? ''}`.trim())}</strong>
            <span class="badge statut-badge statut-bloquee">${escapeHtml(a.type_alerte ?? 'alerte')}</span>
            <span class="muted">${escapeHtml(a.code_type_conge ?? '')} — ${formaterNombre(a.disponible)} j (${a.annee ?? ''})</span>
          </div>
        </article>
      `).join('');
    } catch (err) {
      alertesEl.innerHTML = `<p class="error">Alertes indisponibles : ${escapeHtml(err.message)}</p>`;
    }
  }

  async function chargerSoldes() {
    soldesEl.innerHTML = '<p class="muted">Chargement…</p>';

    try {
      const params = new URLSearchParams();
      if (anneeEl.value) params.set('annee', anneeEl.value);
      if (typeEl.value) params.set('id_type_conge', typeEl.value);
      if (rechercheEl.value.trim()) params.set('recherche', rechercheEl.value.trim());

      const qs = params.toString();
      const soldes = await appelApi(`${API_BASE}/soldes/tous${qs ? `?${qs}` : ''}`);

      if (!Array.isArray(soldes) || soldes.length === 0) {
        soldesEl.innerHTML = '<p class="muted">Aucun solde.</p>';
        return;
      }

      soldesEl.innerHTML = soldes.map((s) => `
        <article class="solde-row">
          <div class="solde-info">
            <strong>${escapeHtml(`${s.prenom ?? ''} ${s.nom ?? ''}`.trim())}</strong>
            <span class="muted">${escapeHtml(s.matricule ?? '')} · ${escapeHtml(s.code_type_conge ?? '')} · ${escapeHtml(s.libelle_type_conge ?? '')}</span>
            <span class="muted">Droit : ${formaterNombre(s.droit_acquis)} · Report : ${formaterNombre(s.report)} · Pris : ${formaterNombre(s.pris)} · En attente : ${formaterNombre(s.en_attente)}</span>
          </div>
          <span class="badge">${formaterNombre(s.disponible)} j dispo</span>
        </article>
      `).join('');
    } catch (err) {
      soldesEl.innerHTML = `<p class="error">Impossible de charger les soldes : ${escapeHtml(err.message)}</p>`;
    }
  }

  function feedback(message, erreur = false) {
    feedbackEl.innerHTML = `<p class="${erreur ? 'error' : 'muted'}">${escapeHtml(message)}</p>`;
  }

  ajustementForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    feedback('');

    if (!ajustementSalarieEl.value) {
      feedback('Sélectionnez un salarié dans la liste.', true);
      return;
    }

    try {
      await appelApi(`${API_BASE}/soldes/ajuster`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id_salarie: Number(document.getElementById('ajustement-salarie').value),
          id_type_conge: document.getElementById('ajustement-type').value,
          annee: Number(document.getElementById('ajustement-annee').value),
          nb_jours: Number(document.getElementById('ajustement-jours').value),
          commentaire: document.getElementById('ajustement-commentaire').value,
        }),
      });
      feedback('Solde ajusté.');
      chargerSoldes();
    } catch (err) {
      feedback(err.message, true);
    }
  });

  clotureForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    feedback('');
    if (!window.confirm(`Clôturer l’année ${clotureAnneeEl.value || new Date().getFullYear()} ? (report + expiration)`)) return;
    try {
      await appelApi(`${API_BASE}/cloturer-annee`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ annee: Number(clotureAnneeEl.value) || new Date().getFullYear() }),
      });
      feedback('Clôture terminée.');
      chargerSoldes();
      chargerAlertes();
    } catch (err) {
      feedback(err.message, true);
    }
  });

  filtresEl.addEventListener('submit', (e) => {
    e.preventDefault();
    chargerSoldes();
  });

  anneeEl.value = String(new Date().getFullYear());
  ajustementAnneeEl.value = String(new Date().getFullYear());
  clotureAnneeEl.value = String(new Date().getFullYear());

  chargerTypes();
  chargerEmployes();
  chargerAlertes();
  chargerSoldes();
})();
