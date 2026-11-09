/* eslint-env browser */

(() => {
  const API_BASE = '/api/conges';
  const soldesListEl = document.getElementById('soldes-list');
  const soldesAnneeEl = document.getElementById('soldes-annee');
  const soldeTtcEl = document.getElementById('solde-ttc');
  const demandesListEl = document.getElementById('demandes-list');
  const filtreAnneeEl = document.getElementById('filtre-annee');
  const filtresForm = document.getElementById('filtres-demandes');
  const filtreStatutEl = document.getElementById('filtre-statut');

  const STATUT_LABELS = {
    EN_ATTENTE_MANAGER: 'En attente manager',
    EN_ATTENTE_RH: 'En attente RH',
    VALIDEE: 'Validée',
    REFUSEE: 'Refusée',
    ANNULEE: 'Annulée',
  };

  const STATUT_CLASSES = {
    EN_ATTENTE_MANAGER: 'statut-en-cours',
    EN_ATTENTE_RH: 'statut-en-cours',
    VALIDEE: 'statut-realisee',
    REFUSEE: 'statut-bloquee',
    ANNULEE: 'statut-annulee',
  };

  function anneeCourante() {
    return new Date().getFullYear();
  }

  async function appelApi(url, options = {}) {
    const response = await fetch(url, {
      headers: { Accept: 'application/json', ...(options.headers || {}) },
      ...options,
    });

    if (!response.ok) {
      let message = `Erreur ${response.status}`;
      try {
        const corps = await response.json();
        if (corps && (corps.message || corps.error)) {
          message = corps.message || corps.error;
        }
      } catch (_) {
        // réponse non JSON
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
    const valeur = Number(n);
    return Number.isNaN(valeur) ? '—' : valeur.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  async function chargerSoldes() {
    const annee = Number(filtreAnneeEl?.value) || anneeCourante();
    soldesAnneeEl.textContent = `(${annee})`;
    soldesListEl.innerHTML = '<p class="muted">Chargement…</p>';

    try {
      const data = await appelApi(`${API_BASE}/soldes?annee=${annee}`);
      const soldes = Array.isArray(data?.soldes) ? data.soldes : [];

      soldeTtcEl.textContent = data?.solde_tout_compte !== undefined && data?.solde_tout_compte !== null
        ? `Jours restants à indemniser au départ : ${formaterNombre(data.solde_tout_compte)} j`
        : '';

      if (soldes.length === 0) {
        soldesListEl.innerHTML = '<p class="muted">Aucun solde pour cette année.</p>';
        return;
      }

      soldesListEl.innerHTML = soldes.map((s) => `
        <article class="solde-row">
          <div class="solde-info">
            <strong>${escapeHtml(s.code_type_conge ?? 'Congé')}</strong>
            <span>${escapeHtml(s.libelle_type_conge ?? '')}</span>
            <span class="muted">Droit : ${formaterNombre(s.droit_acquis)} · Report : ${formaterNombre(s.report)} · Pris : ${formaterNombre(s.pris)} · En attente : ${formaterNombre(s.en_attente)}</span>
          </div>
          <span class="badge">${formaterNombre(s.disponible)} j disponible${Number(s.disponible) > 1 ? 's' : ''}</span>
        </article>
      `).join('');
    } catch (err) {
      soldesListEl.innerHTML = `<p class="error">Impossible de charger les soldes : ${escapeHtml(err.message)}</p>`;
    }
  }

  async function chargerDemandes() {
    demandesListEl.innerHTML = '<p class="muted">Chargement…</p>';

    try {
      const params = new URLSearchParams();
      const statut = filtreStatutEl?.value;
      if (statut) params.set('statut', statut);
      const annee = Number(filtreAnneeEl?.value);
      if (annee) params.set('annee', String(annee));

      const qs = params.toString();
      const demandes = await appelApi(`${API_BASE}/demandes${qs ? `?${qs}` : ''}`);

      if (!Array.isArray(demandes) || demandes.length === 0) {
        demandesListEl.innerHTML = '<p class="muted">Aucune demande de congé.</p>';
        return;
      }

      demandesListEl.innerHTML = demandes.map((d) => {
        const statutCode = d.code_statut ?? '';
        const label = STATUT_LABELS[statutCode] ?? (d.libelle_statut ?? statutCode);
        const classe = STATUT_CLASSES[statutCode] ?? '';
        const peutAnnuler = ['EN_ATTENTE_MANAGER', 'EN_ATTENTE_RH', 'VALIDEE'].includes(statutCode);
        return `
          <article class="demande-row">
            <div class="demande-info">
              <strong>${escapeHtml(d.code_type_conge ?? 'Congé')} — ${escapeHtml(d.libelle_type_conge ?? '')}</strong>
              <span class="muted">Du ${formaterDate(d.date_debut)} au ${formaterDate(d.date_fin)} ${d.demi_journee_debut ? '· matin (demi)' : ''}${d.demi_journee_fin ? '· après-midi (demi)' : ''}</span>
              <span class="muted">${formaterNombre(d.nb_jours)} jour(s) · demandée le ${formaterDate(d.date_demande)}</span>
              ${d.motif ? `<span>${escapeHtml(d.motif)}</span>` : ''}
              ${d.motif_refus ? `<span class="error">Refus : ${escapeHtml(d.motif_refus)}</span>` : ''}
              <span class="badge statut-badge ${classe}">${escapeHtml(label)}</span>
            </div>
            ${peutAnnuler ? `<button type="button" class="button-secondary annuler-btn" data-id="${d.id_demande_conge}">Annuler</button>` : ''}
          </article>
        `;
      }).join('');

      demandesListEl.querySelectorAll('.annuler-btn').forEach((bouton) => {
        bouton.addEventListener('click', async () => {
          if (!window.confirm('Annuler cette demande ?')) return;
          bouton.disabled = true;
          try {
            await appelApi(`${API_BASE}/demandes/${bouton.dataset.id}/annuler`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({}),
            });
            chargerDemandes();
            chargerSoldes();
          } catch (err) {
            bouton.disabled = false;
            demandesListEl.innerHTML = `<p class="error">Annulation impossible : ${escapeHtml(err.message)}</p>`;
          }
        });
      });
    } catch (err) {
      demandesListEl.innerHTML = `<p class="error">Impossible de charger les demandes : ${escapeHtml(err.message)}</p>`;
    }
  }

  function escapeHtml(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
  }

  if (filtreAnneeEl) {
    filtreAnneeEl.value = String(anneeCourante());
    filtreAnneeEl.addEventListener('change', () => {
      chargerSoldes();
      chargerDemandes();
    });
  }

  filtresForm?.addEventListener('submit', (evenement) => {
    evenement.preventDefault();
    chargerDemandes();
  });

  chargerSoldes();
  chargerDemandes();
})();
