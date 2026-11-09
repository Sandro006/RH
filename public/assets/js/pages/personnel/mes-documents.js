/* eslint-env browser */

(() => {
  const API_BASE = '/api/personnel';
  const listEl = document.getElementById('documents-list');

  async function chargerDocuments() {
    listEl.innerHTML = '<p class="muted">Chargement…</p>';

    try {
      const response = await fetch(`${API_BASE}/mes-documents`, {
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        throw new Error(`Erreur ${response.status}`);
      }

      const documents = await response.json();

      if (!Array.isArray(documents) || documents.length === 0) {
        listEl.innerHTML = '<p class="muted">Aucun document dans votre dossier.</p>';
        return;
      }

      const html = documents.map((d) => {
        const niveau = d.niveau_confidentialite ?? 'interne';
        const dateDepot = d.date_depot ? new Date(d.date_depot).toLocaleDateString('fr-FR') : '—';
        return `
          <article class="document-row">
            <div class="document-info">
              <strong>${escapeHtml(d.nom_fichier ?? 'Document')}</strong>
              <span class="badge niveau-${niveau}">${escapeHtml(niveau)}</span>
              <span class="muted">Déposé le ${dateDepot}</span>
            </div>
            <a href="/api/documents/${d.id_document}/telecharger" class="button-secondary" download>Télécharger</a>
          </article>
        `;
      }).join('');

      listEl.innerHTML = html;
    } catch (err) {
      listEl.innerHTML = `<p class="error">Impossible de charger les documents : ${escapeHtml(err.message)}</p>`;
    }
  }

  function escapeHtml(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
  }

  chargerDocuments();
})();
