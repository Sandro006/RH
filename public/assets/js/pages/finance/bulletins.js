/* eslint-env browser */

// Gestion paie — bulletins (Finance/RH).
// Calculer : POST /api/paie/bulletins/calculer { id_salarie, mois }
// Liste : GET /api/paie/bulletins?mois=
(globalThis.BulletinPaiePage = (() => {
  const API_BASE = '/api/paie';
  const listEl = document.getElementById('bulletins-list');
  const resultatEl = document.getElementById('bulletin-resultat');
  const formBulletin = document.getElementById('form-bulletin');
  const salarieRecherche = document.getElementById('salarie-recherche');
  const salarieIdEl = document.getElementById('id_salarie');
  const moisEl = document.getElementById('mois');
  const filtresEl = document.getElementById('filtres-bulletins');
  const moisListeEl = document.getElementById('mois-liste');
  const elementRecherche = document.getElementById('element-salarie-recherche');
  const elementIdEl = document.getElementById('element-id_salarie');
  const elementForm = document.getElementById('form-element');
  const elementFeedback = document.getElementById('element-feedback');
  const previewEl = document.getElementById('bulletin-preview');
  const previewBodyEl = document.getElementById('bulletin-preview-body');
  const previewPrintEl = document.getElementById('bulletin-preview-print');

  const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
  const TYPE_ELEMENT = { PRIME: 'Prime', HEURE_SUP: 'Heures supplémentaires', RETTENUE: 'Retenue', AVANTAGE: 'Avantage', AUTRE: 'Autre' };

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

  function formaterMontant(n) {
    if (n === null || n === undefined) return '—';
    const v = Number(n);
    return Number.isNaN(v) ? '—' : v.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function escapeHtml(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
  }

  // Listes d’employés (GET /api/referentiels/employes) dans les 2 datalists.
  async function chargerEmployes() {
    try {
      const reponse = await appelApi('/api/referentiels/employes');
      const employes = Array.isArray(reponse?.donnees) ? reponse.donnees : [];
      const liste = employes
        .filter((e) => e.idSalarie !== null && e.idSalarie !== undefined)
        .map((e) => ({ idSalarie: e.idSalarie, libelle: [e.libelle, e.poste].filter(Boolean).join(' — ') }));

      const datalists = [
        document.getElementById('employes-datalist'),
        document.getElementById('employes-datalist-2'),
      ].filter(Boolean);

      datalists.forEach((datalist) => {
        datalist.innerHTML = liste
          .map((e) => `<option value="${escapeHtml(e.libelle)}" data-id-salarie="${e.idSalarie}"></option>`)
          .join('');
      });

      const lier = (inputTexte, inputCache) => {
        if (!inputTexte || !inputCache) return;
        const resoudre = () => {
          const valeur = inputTexte.value.trim().toLowerCase();
          const trouve = liste.find((e) => e.libelle.toLowerCase() === valeur);
          inputCache.value = trouve ? trouve.idSalarie : '';
        };
        inputTexte.addEventListener('input', resoudre);
        inputTexte.addEventListener('change', resoudre);
      };

      lier(salarieRecherche, salarieIdEl);
      lier(elementRecherche, elementIdEl);
    } catch (_) {
      // sans la liste, la saisie d'id reste possible
    }
  }

  function rendreBulletin(b) {
    if (!b) return '<p class="muted">Bulletin introuvable.</p>';
    const valide = b.date_validation ? ' (validé)' : '';
    return `
      <article class="solde-row">
        <div class="solde-info">
          <strong>${escapeHtml(b.mois || '')}${valide}</strong>
          <span class="muted">Brut <strong>${formaterMontant(b.brut)}</strong> · Net <strong>${formaterMontant(b.net)}</strong> · Patronal ${formaterMontant(b.cotisations_patronal)}</span>
          <span class="muted">Base ${formaterMontant(b.salaire_base)} · Éléments ${formaterMontant(b.elements_paie)} · Retenue congés ${formaterMontant(b.retenue_conges)} · Cot. employé ${formaterMontant(b.cotisations_employe)}</span>
          <span class="muted">Jours payés ${formaterNombre(b.jours_paye)} · Sans solde ${formaterNombre(b.jours_sans_solde)} · Injustifiés ${formaterNombre(b.jours_injustifies)} (${b.jours_ouvres_mois} j ouvrés)</span>
        </div>
        <div class="bulletin-actions">
          <button type="button" class="button-secondary apercu-btn" data-id="${b.id_bulletin}"><i class="fa-solid fa-eye"></i> Aperçu</button>
          ${b.date_validation ? '<span class="badge">validé</span>' : `<button type="button" class="button-primary valider-btn" data-id="${b.id_bulletin}">Valider</button>`}
        </div>
      </article>
    `;
  }

  function formaterNombre(n) {
    if (n === null || n === undefined) return '—';
    const v = Number(n);
    return Number.isNaN(v) ? '—' : v.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function formaterMois(iso) {
    const m = String(iso ?? '').match(/^(\d{4})-(\d{2})/);
    if (!m) return escapeHtml(iso ?? '');
    return `${MOIS[Number(m[2]) - 1] ?? m[2]} ${m[1]}`;
  }

  // `valeur` = montant numérique brut (jamais pré-formaté : formaterMontant
  // refait Number() dessus et "43 000" devient NaN -> '—'). `signe` = '+'/'-'.
  function ligneMontant(libelle, valeur, classe, signe = '') {
    return `<tr class="${classe ?? ''}"><td>${escapeHtml(libelle)}</td><td class="bulletin-montant">${signe}${formaterMontant(valeur)}</td></tr>`;
  }

  function rendreApercu(detail) {
    const b = detail?.bulletin;
    if (!b) return '<p class="muted">Bulletin introuvable.</p>';
    const s = detail.salarie ?? {};
    const synthese = detail.synthese ?? {};
    const elements = Array.isArray(detail.elements) ? detail.elements : [];
    const gains = elements.filter((e) => Number(e.montant) >= 0);
    const retenuesManuelles = elements.filter((e) => Number(e.montant) < 0);
    const nom = `${s.prenom ?? ''} ${s.nom ?? ''}`.trim() || `Salarié #${b.id_salarie ?? ''}`;
    const valide = b.date_validation ? '<span class="badge">validé</span>' : '<span class="badge statut-badge statut-bloquee">non validé</span>';

    const lignesGains = gains
      .map((e) => ligneMontant(`${TYPE_ELEMENT[e.type_element] ?? e.type_element ?? 'Élément'} — ${e.libelle ?? ''}`, e.montant, 'gain', '+'))
      .join('');
    const lignesRetenuesManuelles = retenuesManuelles
      .map((e) => ligneMontant(`${TYPE_ELEMENT[e.type_element] ?? 'Retenue'} — ${e.libelle ?? ''}`, e.montant, 'retenue'))
      .join('');

    const retenueSansSolde = Number(b.jours_sans_solde) > 0
      ? ligneMontant(`Retenue congés sans solde (${formaterNombre(b.jours_sans_solde)} j × ${formaterMontant(synthese.taux_journalier)} / j)`, synthese.retenue_sans_solde, 'retenue', '-')
      : '';
    const retenueInjustifies = Number(b.jours_injustifies) > 0
      ? ligneMontant(`Retenue absences injustifiées (${formaterNombre(b.jours_injustifies)} j × ${formaterMontant(synthese.taux_journalier)} / j)`, synthese.retenue_injustifies, 'retenue', '-')
      : '';
    const blocRetenueConges = (retenueSansSolde || retenueInjustifies)
      ? `${retenueSansSolde}${retenueInjustifies}${ligneMontant('Sous-total retenue congés', b.retenue_conges, 'sous-total', '-')}`
      : ligneMontant('Retenue congés (aucun jour sans solde ni injustifié)', 0, 'sous-total');

    const sommeElements = gains.reduce((acc, e) => acc + Number(e.montant), 0) + retenuesManuelles.reduce((acc, e) => acc + Number(e.montant), 0);
    const alerteElements = Math.abs(sommeElements - Number(b.elements_paie)) > 0.01
      ? '<p class="bulletin-alerte"><i class="fa-solid fa-triangle-exclamation"></i> Des éléments variables ont été saisis ou modifiés après le calcul — recalculez le bulletin pour les intégrer.</p>'
      : '';

    return `
      <div class="bulletin-doc">
        <div class="bulletin-doc-head">
          <div>
            <strong>Bulletin de paie — ${escapeHtml(formaterMois(b.mois))}</strong>
            <span>${escapeHtml(nom)} · Matricule ${escapeHtml(s.matricule ?? '—')}</span>
          </div>
          <div class="bulletin-doc-meta">
            ${valide}
            <span class="muted">Calculé le ${escapeHtml(b.date_calcul ?? '—')}</span>
          </div>
        </div>

        <table class="bulletin-table">
          <thead><tr><th>Description</th><th>Montant</th></tr></thead>
          <tbody>
            <tr class="section"><td colspan="2"><i class="fa-solid fa-arrow-trend-up"></i> Gains</td></tr>
            ${ligneMontant(`Salaire de base (prorata, ${b.jours_ouvres_mois} jours ouvrés)`, b.salaire_base, 'gain')}
            ${ligneMontant(`Jours payés — ${formaterNombre(b.jours_paye)} j (sans retenue)`, null, 'info')}
            ${lignesGains}
            ${synthese.total_gains_elements ? ligneMontant('Sous-total gains (éléments variables)', synthese.total_gains_elements, 'sous-total', '+') : ''}

            <tr class="section"><td colspan="2"><i class="fa-solid fa-arrow-trend-down"></i> Retenues</td></tr>
            ${blocRetenueConges}
            ${lignesRetenuesManuelles}
            ${synthese.total_retenues_manuelles ? ligneMontant('Sous-total retenues manuelles', synthese.total_retenues_manuelles, 'sous-total') : ''}
            ${ligneMontant(`Cotisations employé (${formaterNombre(b.taux_employe_applique)} % du brut)`, b.cotisations_employe, 'retenue', '-')}

            <tr class="section"><td colspan="2"><i class="fa-solid fa-calculator"></i> Totaux</td></tr>
            ${ligneMontant('Brut (= base + éléments − retenue congés)', b.brut, 'total')}
            ${ligneMontant('Net (= brut − cotisations employé)', b.net, 'total net')}
            ${ligneMontant(`Cotisations patronal (${formaterNombre(b.taux_patronal_applique)} % — coût employeur, non déduit)`, b.cotisations_patronal, 'info')}
          </tbody>
        </table>

        ${alerteElements}
        <p class="muted bulletin-formule">Taux journalier : ${formaterMontant(synthese.taux_journalier)} (= base ${formaterMontant(b.salaire_base)} ÷ ${b.jours_ouvres_mois} jours ouvrés). Les demi-journées comptent 1 jour dans la retenue congés.</p>
      </div>
    `;
  }

  async function ouvrirApercu(idBulletin) {
    if (!previewEl || !previewBodyEl) return;
    previewEl.classList.remove('hidden');
    previewBodyEl.innerHTML = '<p class="muted">Chargement de l’aperçu…</p>';
    try {
      const detail = await appelApi(`${API_BASE}/bulletins/${idBulletin}/detail`);
      previewBodyEl.innerHTML = rendreApercu(detail);
    } catch (err) {
      previewBodyEl.innerHTML = `<p class="error">Aperçu impossible : ${escapeHtml(err.message)}</p>`;
    }
  }

  function fermerApercu() {
    if (previewEl) previewEl.classList.add('hidden');
  }

  function lierBoutonsApercu(container) {
    container.querySelectorAll('.apercu-btn').forEach((bouton) => {
      bouton.addEventListener('click', () => ouvrirApercu(bouton.dataset.id));
    });
  }

  function lierBoutonsValider(container) {
    container.querySelectorAll('.valider-btn').forEach((bouton) => {
      bouton.addEventListener('click', async () => {
        if (!window.confirm('Valider ce bulletin ? Il ne pourra plus être recalculé.')) return;
        bouton.disabled = true;
        try {
          await appelApi(`${API_BASE}/bulletins/${bouton.dataset.id}/valider`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
          });
          chargerListe();
        } catch (err) {
          bouton.disabled = false;
          listEl.innerHTML = `<p class="error">Validation impossible : ${escapeHtml(err.message)}</p>`;
        }
      });
    });
  }

  async function calculer() {
    const idSalarie = Number(salarieIdEl.value);
    const mois = moisEl.value;

    if (!idSalarie) {
      resultatEl.innerHTML = '<p class="error">Sélectionnez un salarié dans la liste.</p>';
      return;
    }
    if (!mois) {
      resultatEl.innerHTML = '<p class="error">Choisissez le mois (YYYY-MM).</p>';
      return;
    }

    resultatEl.innerHTML = '<p class="muted">Calcul en cours…</p>';

    try {
      const bulletin = await appelApi(`${API_BASE}/bulletins/calculer`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_salarie: idSalarie, mois }),
      });
      resultatEl.innerHTML = rendreBulletin(bulletin);
      lierBoutonsValider(resultatEl);
      lierBoutonsApercu(resultatEl);
    } catch (err) {
      resultatEl.innerHTML = `<p class="error">Calcul impossible : ${escapeHtml(err.message)}</p>`;
    }
  }

  async function chargerListe() {
    listEl.innerHTML = '<p class="muted">Chargement…</p>';

    try {
      const mois = moisListeEl.value;
      if (!mois) {
        listEl.innerHTML = '<p class="muted">Choisissez un mois pour lister.</p>';
        return;
      }
      const bulletins = await appelApi(`${API_BASE}/bulletins?mois=${mois}`);

      if (!Array.isArray(bulletins) || bulletins.length === 0) {
        listEl.innerHTML = '<p class="muted">Aucun bulletin pour ce mois.</p>';
        return;
      }

      listEl.innerHTML = bulletins.map((b) => rendreBulletin(b)).join('');
      lierBoutonsValider(listEl);
      lierBoutonsApercu(listEl);
    } catch (err) {
      listEl.innerHTML = `<p class="error">Impossible de charger les bulletins : ${escapeHtml(err.message)}</p>`;
    }
  }

  function feedbackElement(message, erreur = false) {
    elementFeedback.innerHTML = `<p class="${erreur ? 'error' : 'muted'}">${escapeHtml(message)}</p>`;
  }

  elementForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    feedbackElement('');

    if (!elementIdEl.value) {
      feedbackElement('Sélectionnez un salarié dans la liste.', true);
      return;
    }

    try {
      await appelApi(`${API_BASE}/elements`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id_salarie: Number(elementIdEl.value),
          mois: document.getElementById('element-mois').value,
          type_element: document.getElementById('element-type').value,
          libelle: document.getElementById('element-libelle').value.trim(),
          montant: Number(document.getElementById('element-montant').value),
          commentaire: null,
        }),
      });
      feedbackElement('Élément saisi.');
      elementForm.reset();
    } catch (err) {
      feedbackElement(err.message, true);
    }
  });

  formBulletin.addEventListener('submit', (e) => {
    e.preventDefault();
    calculer();
  });

  if (previewEl) {
    previewEl.querySelectorAll('[data-close-preview]').forEach((bouton) => {
      bouton.addEventListener('click', fermerApercu);
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') fermerApercu();
    });
  }
  if (previewPrintEl) {
    previewPrintEl.addEventListener('click', () => window.print());
  }

  filtresEl.addEventListener('submit', (e) => {
    e.preventDefault();
    chargerListe();
  });

  const anneCourante = new Date().getFullYear();
  const moisCourant = String(new Date().getMonth() + 1).padStart(2, '0');
  moisEl.value = `${anneCourante}-${moisCourant}`;
  moisListeEl.value = `${anneCourante}-${moisCourant}`;
  document.getElementById('element-mois').value = `${anneCourante}-${moisCourant}`;

  chargerEmployes();
  chargerListe();
  return {};
})());
