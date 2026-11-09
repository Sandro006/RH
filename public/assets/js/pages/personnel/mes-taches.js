/* eslint-env browser */

(() => {
  const API_BASE = '/api/personnel';
  const progressionEl = document.getElementById('progression-badge');
  const listEl = document.getElementById('taches-list');

  const STATUT_LABELS = {
    1: 'À faire',
    2: 'En cours',
    3: 'Bloquée',
    4: 'Réalisée',
    5: 'Annulée',
  };

  const STATUT_CLASSES = {
    1: 'statut-a-faire',
    2: 'statut-en-cours',
    3: 'statut-bloquee',
    4: 'statut-realisee',
    5: 'statut-annulee',
  };

  async function chargerStatuts() {
    try {
      const response = await fetch('/api/referentiels/statuts/onboarding_tache', {
        headers: { Accept: 'application/json' },
      });
      if (response.ok) {
        const statuts = await response.json();
        statuts.forEach((s) => {
          STATUT_LABELS[s.id] = s.designation;
        });
      }
    } catch (_) {
      // On utilise les libellés par défaut en cas d'échec
    }
  }

  async function chargerTaches() {
    progressionEl.innerHTML = '<span class="muted">Chargement…</span>';
    listEl.innerHTML = '<p class="muted">Chargement…</p>';

    await chargerStatuts();

    try {
      const response = await fetch(`${API_BASE}/mes-taches`, {
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        throw new Error(`Erreur ${response.status}`);
      }

      const data = await response.json();

      if (!data || !data.taches || data.taches.length === 0) {
        progressionEl.innerHTML = '<span class="muted">Aucun onboarding en cours.</span>';
        listEl.innerHTML = '<p class="muted">Aucune tâche planifiée pour votre intégration.</p>';
        return;
      }

      const total = data.taches.length;
      const realisees = data.taches.filter((t) => t.statut === 4).length;
      const pourcentage = total === 0 ? 0 : Math.round((realisees / total) * 100);

      progressionEl.innerHTML = `
        <strong>Progression : ${pourcentage}%</strong> (${realisees} / ${total} tâche${total > 1 ? 's' : ''} réalisée${total > 1 ? 's' : ''})
      `;

      const html = data.taches.map((t) => {
        const statutId = (t.statut !== undefined && t.statut !== null) ? parseInt(t.statut, 10) : 1;
        const label = STATUT_LABELS[statutId] ?? 'Statut inconnu';
        const classe = STATUT_CLASSES[statutId] ?? '';
        const echeance = t.date_echeance ? new Date(t.date_echeance).toLocaleDateString('fr-FR') : '—';
        return `
          <article class="tache-row">
            <div class="tache-info">
              <strong>${escapeHtml(t.intitule ?? 'Tâche')}</strong>
              ${t.description ? `<p class="muted">${escapeHtml(t.description)}</p>` : ''}
              <span class="badge statut-badge ${classe}">${escapeHtml(label)}</span>
              <span class="muted">Échéance : ${echeance}</span>
            </div>
          </article>
        `;
      }).join('');

      listEl.innerHTML = html;
    } catch (err) {
      progressionEl.innerHTML = '<span class="error">Erreur de chargement</span>';
      listEl.innerHTML = `<p class="error">Impossible de charger les tâches : ${escapeHtml(err.message)}</p>`;
    }
  }

  function escapeHtml(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
  }

  chargerTaches();
})();
