/* eslint-env browser */

// Validations des congés (Bloc 7, écran 3).
// Manager : demandes EN_ATTENTE_MANAGER de son service.
// RH / admin : demandes EN_ATTENTE_RH.
// Valider -> POST .../valider ; Refuser exige un motif (RG17).

(() => {
  const API_BASE = '/api/conges';
  const listEl = document.getElementById('a-valider-list');
  const consigneEl = document.getElementById('a-valider-consigne');

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
    const valeur = Number(n);
    return Number.isNaN(valeur) ? '—' : valeur.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  const STATUT_LABELS = {
    EN_ATTENTE_MANAGER: 'En attente manager',
    EN_ATTENTE_RH: 'En attente RH',
  };

  function estVrai(valeur) {
    return valeur === true || valeur === 1 || valeur === '1' || valeur === 't' || valeur === 'true';
  }

  function infosLigne(d) {
    const pieces = [
      `${d.nom ?? ''} ${d.prenom ?? ''}`.trim() || `Salarié #${d.id_salarie ?? ''}`,
      d.matricule ? `(${d.matricule})` : '',
      d.nom_service ? d.nom_service : '',
      `Du ${formaterDate(d.date_debut)} au ${formaterDate(d.date_fin)}`,
      `${formaterNombre(d.nb_jours)} j`,
      d.date_demande ? `demandée le ${formaterDate(d.date_demande)}` : '',
      d.jours_attente !== undefined && d.jours_attente !== null ? `(${d.jours_attente} j en attente)` : '',
      d.solde_disponible_demandeur !== undefined && d.solde_disponible_demandeur !== null
        ? `solde dispo : ${formaterNombre(d.solde_disponible_demandeur)} j`
        : '',
    ].filter(Boolean);

    if (estVrai(d.justificatif_requis)) {
      pieces.push(estVrai(d.justificatif_present) ? 'justificatif joint' : 'justificatif manquant');
    }

    return pieces.join(' · ');
  }

  async function charger() {
    listEl.className = 'state';
    listEl.innerHTML = '<p class="muted">Chargement…</p>';

    try {
      const demandes = await appelApi(`${API_BASE}/a-valider`);

      if (!Array.isArray(demandes) || demandes.length === 0) {
        consigneEl.textContent = '';
        listEl.className = 'state';
        listEl.innerHTML = '<p class="muted">Aucune demande en attente.</p>';
        return;
      }

      consigneEl.textContent = `${demandes.length} demande(s) en attente.`;

      listEl.className = 'demande-list';
      listEl.innerHTML = demandes.map((d) => `
        <article class="demande-row">
          <div class="demande-info">
            <strong>#${d.id_demande_conge} — ${escapeHtml(d.code_type_conge ?? 'Congé')} ${d.libelle_type_conge ? `(${escapeHtml(d.libelle_type_conge)})` : ''}</strong>
            <span>${escapeHtml(infosLigne(d))}</span>
            <span class="badge statut-badge statut-en-cours">${escapeHtml(STATUT_LABELS[d.code_statut] ?? 'En attente de validation')}</span>
          </div>
          <div class="demande-actions">
            <a class="button button-secondary detail-btn" href="/personnel/conges-demande?id=${d.id_demande_conge}">Détail</a>
            ${d.peut_valider === false
              ? '<span class="muted">Validation par un autre valideur (demande personnelle)</span>'
              : `<button type="button" class="button-primary valider-btn" data-id="${d.id_demande_conge}">Valider</button>
                 <button type="button" class="button-secondary refuser-btn" data-id="${d.id_demande_conge}">Refuser</button>`}
          </div>
        </article>
      `).join('');

      listEl.querySelectorAll('.valider-btn').forEach((bouton) => {
        bouton.addEventListener('click', async () => {
          if (!window.confirm('Valider cette demande ?')) return;
          bouton.disabled = true;
          try {
            await appelApi(`${API_BASE}/demandes/${bouton.dataset.id}/valider`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({}),
            });
            charger();
          } catch (err) {
            bouton.disabled = false;
            listEl.className = 'state';
            listEl.innerHTML = `<p class="error">Validation impossible : ${escapeHtml(err.message)}</p>`;
          }
        });
      });

      listEl.querySelectorAll('.refuser-btn').forEach((bouton) => {
        bouton.addEventListener('click', async () => {
          const motif = window.prompt('Motif du refus (obligatoire) :');
          if (motif === null) return;
          if (motif.trim() === '') {
            listEl.className = 'state';
            listEl.innerHTML = '<p class="error">Le motif du refus est obligatoire.</p>';
            return;
          }
          bouton.disabled = true;
          try {
            await appelApi(`${API_BASE}/demandes/${bouton.dataset.id}/refuser`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ motif: motif.trim() }),
            });
            charger();
          } catch (err) {
            bouton.disabled = false;
            listEl.className = 'state';
            listEl.innerHTML = `<p class="error">Refus impossible : ${escapeHtml(err.message)}</p>`;
          }
        });
      });
    } catch (err) {
      listEl.className = 'state';
      listEl.innerHTML = `<p class="error">Impossible de charger les validations : ${escapeHtml(err.message)}</p>`;
    }
  }

  function escapeHtml(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
  }

  charger();
})();
