/* eslint-env browser */

// Absences (RH) — Bloc 7, écran 6.
// GET /api/conges/absences (filtres) + POST /api/conges/absences (saisie).

(() => {
  const API_BASE = '/api/conges';
  const listEl = document.getElementById('absences-list');
  const formEl = document.getElementById('absence-form');
  const filtresEl = document.getElementById('absences-filtres');
  const feedbackEl = document.getElementById('absence-feedback');
  const filtreSalarieEl = document.getElementById('filtre-salarie'); // caché (id résolu)
  const filtreSalarieTexteEl = document.getElementById('filtre-salarie-texte');
  const salarieTexteEl = document.getElementById('salarie-recherche');
  const salarieIdEl = document.getElementById('id_salarie'); // caché (id résolu)
  const filtreTypeEl = document.getElementById('filtre-type');
  const filtreJustifieeEl = document.getElementById('filtre-justifiee');

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

  function formaterDate(iso) {
    if (!iso) return '—';
    const date = new Date(iso.length <= 10 ? `${iso}T00:00:00` : iso);
    return Number.isNaN(date.getTime()) ? iso : date.toLocaleDateString('fr-FR');
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

  // Liste employés (GET /api/referentiels/employes → {id employe, libelle, poste, idSalarie}).
  // Remplit les <datalist> et résout le nom tapé -> id_salarie dans les champs cachés.
  async function chargerEmployes() {
    const datalists = [
      document.getElementById('employes-datalist'),
      document.getElementById('employes-datalist-filtre'),
    ].filter(Boolean);

    try {
      const reponse = await appelApi('/api/referentiels/employes');
      const employes = Array.isArray(reponse?.donnees) ? reponse.donnees : [];
      const liste = employes
        .filter((e) => e.idSalarie !== null && e.idSalarie !== undefined)
        .map((e) => ({
          idSalarie: e.idSalarie,
          libelle: [e.libelle, e.poste].filter(Boolean).join(' — '),
        }));

      datalists.forEach((datalist) => {
        datalist.innerHTML = liste
          .map((e) => `<option value="${escapeHtml(e.libelle)}" data-id-salarie="${e.idSalarie}"></option>`)
          .join('');
      });

      const lier = (inputTexte, inputCache) => {
        if (!inputTexte || !inputCache) return;
        inputTexte.addEventListener('input', () => {
          const valeur = inputTexte.value.trim().toLowerCase();
          const trouve = liste.find((e) => e.libelle.toLowerCase() === valeur);
          inputCache.value = trouve ? trouve.idSalarie : '';
        });
        inputTexte.addEventListener('change', () => {
          const valeur = inputTexte.value.trim().toLowerCase();
          const trouve = liste.find((e) => e.libelle.toLowerCase() === valeur);
          inputCache.value = trouve ? trouve.idSalarie : '';
        });
      };

      lier(salarieTexteEl, salarieIdEl);
      lier(filtreSalarieTexteEl, filtreSalarieEl);
    } catch (_) {
      // sans la liste, l'utilisateur peut taper un id dans le champ
    }
  }

  async function charger() {
    listEl.innerHTML = '<p class="muted">Chargement…</p>';

    try {
      const params = new URLSearchParams();
      if (filtreSalarieEl.value) params.set('id_salarie', filtreSalarieEl.value);
      if (filtreTypeEl.value) params.set('code_type_absence', filtreTypeEl.value);
      if (filtreJustifieeEl.value !== '') params.set('justifiee', filtreJustifieeEl.value);

      const qs = params.toString();
      const absences = await appelApi(`${API_BASE}/absences${qs ? `?${qs}` : ''}`);

      if (!Array.isArray(absences) || absences.length === 0) {
        listEl.innerHTML = '<p class="muted">Aucune absence.</p>';
        return;
      }

      listEl.innerHTML = absences.map((a) => `
        <article class="absence-row">
          <div class="absence-info">
            <strong>${escapeHtml(`${a.prenom ?? ''} ${a.nom ?? ''}`.trim() || `Salarié #${a.id_salarie ?? ''}`)}</strong>
            <span class="muted">${escapeHtml(a.matricule ?? '')}</span>
            <span class="badge statut-badge ${a.justifiee ? 'statut-realisee' : 'statut-bloquee'}">${a.justifiee ? 'justifiée' : 'non justifiée'}</span>
            <span class="muted">Du ${formaterDate(a.date_debut)} au ${formaterDate(a.date_fin)} ${formaterNombre(a.nb_jours)} j</span>
            <span class="muted">${escapeHtml(a.libelle_type_absence ?? a.code_type_absence ?? '')}</span>
            ${a.commentaire ? `<span>${escapeHtml(a.commentaire)}</span>` : ''}
          </div>
        </article>
      `).join('');
    } catch (err) {
      listEl.innerHTML = `<p class="error">Impossible de charger les absences : ${escapeHtml(err.message)}</p>`;
    }
  }

  formEl.addEventListener('submit', async (e) => {
    e.preventDefault();
    feedbackEl.innerHTML = '';

    const idSalarie = Number(document.getElementById('id_salarie').value);
    const debut = document.getElementById('date_debut').value;
    const fin = document.getElementById('date_fin').value;

    if (!idSalarie || !debut || !fin || fin < debut) {
      feedbackEl.innerHTML = '<p class="error">Salarié et dates valides sont requis.</p>';
      return;
    }

    try {
      await appelApi(`${API_BASE}/absences`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id_salarie: idSalarie,
          code_type_absence: document.getElementById('code_type_absence').value,
          date_debut: debut,
          date_fin: fin,
          justifiee: document.getElementById('justifiee').checked,
          commentaire: document.getElementById('commentaire').value.trim() || null,
        }),
      });
      feedbackEl.innerHTML = '<p class="muted">Absence saisie.</p>';
      formEl.reset();
      charger();
    } catch (err) {
      feedbackEl.innerHTML = `<p class="error">Saisie impossible : ${escapeHtml(err.message)}</p>`;
    }
  });

  filtresEl.addEventListener('submit', (e) => {
    e.preventDefault();
    charger();
  });

  chargerEmployes();
  charger();
})();
