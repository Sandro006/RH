/* eslint-env browser */

// Nouvelle demande de congé (Bloc 7, écran 2).
// - Types via GET /api/conges/types (fallback statique si l'API est indisponible).
// - Estimation jours ouvrés (lun–ven, demi-journées) : indicative, hors fériés.
//   Le calcul définitif (week-end + fériés) est fait côté serveur par CalendrierService.
// - RG18 : la RH peut saisir pour un salarié (id_salarie visible si rôle rh/admin).

(() => {
  const API_BASE = '/api/conges';
  const formEl = document.getElementById('conge-form');
  const typeEl = document.getElementById('id_type_conge');
  const debutEl = document.getElementById('date_debut');
  const finEl = document.getElementById('date_fin');
  const demiDebutEl = document.getElementById('demi_journee_debut');
  const demiFinEl = document.getElementById('demi_journee_fin');
  const motifEl = document.getElementById('motif');
  const justificatifEl = document.getElementById('justificatif_depose');
  const champSalarieEl = document.getElementById('champ-id-salarie');
  const idSalarieEl = document.getElementById('id_salarie');
  const estimationEl = document.getElementById('conge-estimation');
  const hintEl = document.getElementById('conge-justificatif-hint');
  const feedbackEl = document.getElementById('conge-feedback');
  const anneeSoldeEl = document.getElementById('annee_solde');
  const soldesListEl = document.getElementById('soldes-list');
  const soldeAnneeEl = document.getElementById('solde-annee');
  const soldeTtcEl = document.getElementById('solde-ttc');

  const TYPES_FALLBACK = [
    { id_type_conge: 0, code: 'ANNUEL', libelle: 'Congé annuel payé', justificatif_requis: false },
    { id_type_conge: 0, code: 'MALADIE', libelle: 'Congé maladie', justificatif_requis: true },
    { id_type_conge: 0, code: 'MATERNITE', libelle: 'Congé maternité', justificatif_requis: true },
    { id_type_conge: 0, code: 'PATERNITE', libelle: 'Congé paternité', justificatif_requis: true },
    { id_type_conge: 0, code: 'SANS_SOLDE', libelle: 'Congé sans solde', justificatif_requis: false },
    { id_type_conge: 0, code: 'FAMILIAL', libelle: 'Évènement familial', justificatif_requis: true },
    { id_type_conge: 0, code: 'FORMATION', libelle: 'Congé formation', justificatif_requis: true },
    { id_type_conge: 0, code: 'RECUP', libelle: 'Récupération', justificatif_requis: false },
  ];

  let typesParId = new Map();

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

  function remplirTypes(types) {
    typeEl.innerHTML = '<option value="">Sélectionner un type…</option>';
    typesParId = new Map();
    types.forEach((t) => {
      const option = document.createElement('option');
      option.value = t.id_type_conge > 0 ? String(t.id_type_conge) : t.code;
      option.textContent = `${t.libelle || t.code} (${t.code})`;
      option.dataset.code = t.code;
      option.dataset.justificatif = t.justificatif_requis ? '1' : '0';
      typeEl.appendChild(option);
      if (t.id_type_conge > 0) typesParId.set(t.id_type_conge, t);
    });
  }

  async function chargerTypes() {
    try {
      const types = await appelApi(`${API_BASE}/types`);
      if (Array.isArray(types) && types.length > 0) {
        remplirTypes(types);
        return;
      }
    } catch (_) {
      // fallback ci-dessous
    }
    remplirTypes(TYPES_FALLBACK);
  }

  async function chargerRole() {
    try {
      const session = await appelApi('/api/auth/session');
      const role = session?.utilisateur?.role;
      if (role === 'rh' || role === 'admin') {
        champSalarieEl?.classList.remove('hidden');
      }
    } catch (_) {
      // anonyme : on reste salarié par défaut côté serveur
    }
  }

  function estimationJoursOuvres() {
    const debut = debutEl.value;
    const fin = finEl.value;
    if (!debut || !fin || fin < debut) {
      estimationEl.textContent = 'Estimation : —';
      return null;
    }
    let nb = 0;
    const courant = new Date(`${debut}T00:00:00`);
    const finDate = new Date(`${fin}T00:00:00`);
    while (courant <= finDate) {
      const jour = courant.getDay(); // 0 dim, 6 sam
      if (jour >= 1 && jour <= 5) nb += 1;
      courant.setDate(courant.getDate() + 1);
    }
    if (demiDebutEl.checked && fin >= debut) nb -= 0.5;
    if (demiFinEl.checked && debut !== fin) nb -= 0.5;
    nb = Math.max(0, nb);
    estimationEl.textContent = `Estimation : ${String(nb).replace('.', ',')} jour(s) (lun–ven, hors fériés — calcul définitif côté serveur)`;
    return nb;
  }

  function majHintJustificatif() {
    const option = typeEl.selectedOptions[0];
    const code = option?.dataset?.code || '';
    const requis = option?.dataset?.justificatif === '1';
    const maladie = code === 'MALADIE';
    hintEl?.classList.toggle('hidden', !(requis && !maladie));
  }

  async function envoyer(evenement) {
    evenement.preventDefault();
    feedbackEl.innerHTML = '';

    const idTypeBrut = typeEl.value;
    if (!idTypeBrut) {
      feedbackEl.innerHTML = '<p class="error">Veuillez sélectionner un type de congé.</p>';
      return;
    }
    if (!debutEl.value || !finEl.value || finEl.value < debutEl.value) {
      feedbackEl.innerHTML = '<p class="error">Les dates sont invalides (fin doit suivre début).</p>';
      return;
    }

    const option = typeEl.selectedOptions[0];
    const codeType = option?.dataset?.code || '';
    const requisJustificatif = option?.dataset?.justificatif === '1';
    if (requisJustificatif && codeType !== 'MALADIE' && !justificatifEl.checked) {
      feedbackEl.innerHTML = '<p class="error">Un justificatif est requis pour ce type (RG08).</p>';
      return;
    }

    const estCode = !/^\d+$/.test(idTypeBrut);
    const corps = {
      [estCode ? 'type_code' : 'id_type_conge']: estCode ? codeType : Number(idTypeBrut),
      date_debut: debutEl.value,
      date_fin: finEl.value,
      demi_journee_debut: demiDebutEl.checked,
      demi_journee_fin: demiFinEl.checked,
      motif: motifEl.value.trim(),
      justificatif_depose: justificatifEl.checked,
    };

    const idSalarieAlternative = Number(idSalarieEl?.value);
    if (idSalarieAlternative > 0 && champSalarieEl && !champSalarieEl.classList.contains('hidden')) {
      corps.id_salarie = idSalarieAlternative;
    }

    try {
      await appelApi(`${API_BASE}/demandes`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(corps),
      });
      window.location.href = '/personnel/mes-conges';
    } catch (err) {
      feedbackEl.innerHTML = `<p class="error">Envoi impossible : ${escapeHtml(err.message)}</p>`;
    }
  }

  function formaterNombre(n) {
    if (n === null || n === undefined) return '—';
    const valeur = Number(n);
    return Number.isNaN(valeur) ? '—' : valeur.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  async function chargerSoldes() {
    if (!soldesListEl) return;
    const annee = Number(anneeSoldeEl?.value) || new Date().getFullYear();
    if (soldeAnneeEl) soldeAnneeEl.textContent = `(${annee})`;
    soldesListEl.innerHTML = '<p class="muted">Chargement…</p>';

    try {
      const data = await appelApi(`${API_BASE}/soldes?annee=${annee}`);
      const soldes = Array.isArray(data?.soldes) ? data.soldes : [];

      if (soldeTtcEl) {
        soldeTtcEl.textContent = data?.solde_tout_compte !== undefined && data?.solde_tout_compte !== null
          ? `Jours restants à indemniser au départ : ${formaterNombre(data.solde_tout_compte)} j`
          : '';
      }

      if (soldes.length === 0) {
        soldesListEl.innerHTML = '<p class="muted">Aucun solde pour cette année.</p>';
        return;
      }

      soldesListEl.innerHTML = soldes.map((s) => `
        <div class="solde-row">
          <span>${escapeHtml(s.code_type_conge ?? 'Congé')} — ${formaterNombre(s.disponible)} j dispo</span>
        </div>
      `).join('');
    } catch (err) {
      soldesListEl.innerHTML = `<p class="error">Impossible de charger les soldes : ${escapeHtml(err.message)}</p>`;
    }
  }

  function escapeHtml(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
  }

  if (anneeSoldeEl) {
    anneeSoldeEl.value = String(new Date().getFullYear());
    anneeSoldeEl.addEventListener('change', chargerSoldes);
  }

  [debutEl, finEl, demiDebutEl, demiFinEl].forEach((el) => {
    el?.addEventListener('change', estimationJoursOuvres);
    el?.addEventListener('input', estimationJoursOuvres);
  });
  typeEl.addEventListener('change', majHintJustificatif);
  formEl.addEventListener('submit', envoyer);

  chargerTypes();
  chargerRole();
  chargerSoldes();
  estimationJoursOuvres();
})();
