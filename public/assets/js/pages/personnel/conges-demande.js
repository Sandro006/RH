/* eslint-env browser */

// Détail d'une demande de congé (Bloc 7).
// Affiche la demande complète, ses validations et les salariés qui occupent
// le même emploi (poste) ou relèvent du même service que le demandeur,
// avec leurs indisponibilités sur la période demandée.

(() => {
  const idDemande = new URLSearchParams(location.search).get('id');
  const sousTitre = document.getElementById('heading-sous-titre');
  const headingActions = document.getElementById('heading-actions');
  const detailEl = document.getElementById('demande-detail');
  const traitementEl = document.getElementById('demande-traitement');
  const actionsEl = document.getElementById('actions');
  const colleguesConsigne = document.getElementById('collegues-consigne');
  const colleguesEl = document.getElementById('collegues-table');

  function nombre(valeur) {
    if (valeur === null || valeur === undefined || valeur === '') return '—';
    const n = Number(valeur);
    return Number.isNaN(n)
      ? '—'
      : n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function texte(valeur) {
    return valeur === null || valeur === undefined || String(valeur).trim() === ''
      ? '—'
      : ui.escape(String(valeur));
  }

  function estVrai(valeur) {
    return valeur === true || valeur === 1 || valeur === '1' || valeur === 't' || valeur === 'true';
  }

  function demiJournee(flag) {
    return estVrai(flag) ? ' (demi-journée)' : '';
  }

  function blocSolde(solde) {
    if (!solde) {
      return '<p class="muted">Aucun solde enregistré pour ce type de congé sur l\'année de la demande.</p>';
    }

    const disponible = Number(solde.disponible);

    return `
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Année</th><th>Droit acquis</th><th>Report</th><th>Pris</th>
              <th>En attente</th><th>Disponible</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>${nombre(solde.annee)}</td>
              <td>${nombre(solde.droit_acquis)}</td>
              <td>${nombre(solde.report)}</td>
              <td>${nombre(solde.pris)}</td>
              <td>${nombre(solde.en_attente)}</td>
              <td><strong>${nombre(solde.disponible)}</strong> ${disponible < 0 ? '<span class="badge danger">négatif</span>' : ''}</td>
            </tr>
          </tbody>
        </table>
      </div>`;
  }

  function blocCollegues(data) {
    const demandeur = data.poste_demandeur;
    const collegues = Array.isArray(data.collegues) ? data.collegues : [];

    if (!demandeur) {
      colleguesConsigne.textContent = '';
      colleguesEl.className = 'state';
      colleguesEl.textContent = "Le demandeur n'est rattaché à aucun poste : aucun collègue à comparer.";
      return;
    }

    colleguesConsigne.textContent =
      `Référence : ${demandeur.poste} — ${demandeur.service} (${demandeur.departement}). ` +
      `${collegues.length} salarié(s) au même emploi ou dans le même service.`;

    if (collegues.length === 0) {
      colleguesEl.className = 'state';
      colleguesEl.textContent = 'Aucun autre salarié ne partage cet emploi ou ce service.';
      return;
    }

    const lignes = collegues.map((c) => {
      const role = c.meme_poste
        ? '<span class="badge primary">Même emploi</span>'
        : '<span class="badge">Même service</span>';

      const indispos = c.indisponibilites.length === 0
        ? '<span class="badge success">Disponible</span>'
        : c.indisponibilites.map((i) => {
            const ton = i.source === 'absence' ? 'danger' : 'warning';
            const libelle = i.source === 'absence'
              ? `${i.libelle}${estVrai(i.justifiee) ? '' : ' (non justifiée)'}`
              : i.libelle;
            return `<span class="badge ${ton}">${ui.escape(libelle)} · du ${ui.date(i.debut)} au ${ui.date(i.fin)}</span>`;
          }).join(' ');

      return `
        <tr>
          <td>
            <strong>${ui.escape(c.nom)} ${ui.escape(c.prenom)}</strong><br>
            <small class="muted">${ui.escape(c.matricule)}</small>
          </td>
          <td>${ui.escape(c.poste)}</td>
          <td>${ui.escape(c.service)}<br><small class="muted">${ui.escape(c.departement)}</small></td>
          <td>${role}</td>
          <td>${indispos}</td>
        </tr>`;
    }).join('');

    colleguesEl.className = 'table-wrap';
    colleguesEl.innerHTML = `
      <table class="data-table">
        <thead>
          <tr>
            <th>Salarié</th>
            <th>Emploi (poste)</th>
            <th>Service</th>
            <th>Lien</th>
            <th>Indisponibilités sur la période</th>
          </tr>
        </thead>
        <tbody>${lignes}</tbody>
      </table>`;
  }

  function rendreDetail(data) {
    const d = data.demande;
    const poste = data.poste_demandeur;

    sousTitre.textContent =
      `Demande #${d.id_demande_conge} · ${d.nom} ${d.prenom} · ${d.libelle_type_conge}`;

    const lignes = [
      ['Statut', ui.status(d.libelle_statut || d.code_statut)],
      ['Salarié', `${ui.escape(d.nom)} ${ui.escape(d.prenom)} <small class="muted">(${texte(d.matricule)})</small>`],
      ['Type de congé', `${ui.escape(d.libelle_type_conge)} <small class="muted">(${ui.escape(d.code_type_conge)})</small>`],
      ['Période', `du ${ui.date(d.date_debut)} au ${ui.date(d.date_fin)}${demiJournee(d.demi_journee_debut)}${demiJournee(d.demi_journee_fin)}`],
      ['Jours ouvrés décomptés', `${nombre(d.nb_jours)} jour(s)`],
      ['Date de la demande', `${ui.date(d.date_demande)}${d.jours_attente !== null && d.jours_attente !== undefined ? ` <span class="badge ${Number(d.jours_attente) > 7 ? 'warning' : ''}">${nombre(d.jours_attente)} j d'attente</span>` : ''}`],
      ['Emploi (poste)', texte(poste ? poste.poste : null)],
      ['Service', texte(poste ? poste.service : d.nom_service)],
      ['Département', texte(poste ? poste.departement : null)],
      ['Motif', texte(d.motif)],
      ['Justificatif', estVrai(data.justificatif_requis)
        ? (estVrai(data.justificatif_present)
            ? '<span class="badge success">Joint</span>'
            : '<span class="badge warning">Requis · non joint</span>')
        : '<span class="muted">Non requis pour ce type de congé</span>'],
      ['Validation manager', d.date_validation_manager
        ? `${ui.escape(d.nom_manager_valideur || '—')} le ${ui.date(d.date_validation_manager)}${d.commentaire_manager ? ` — ${ui.escape(d.commentaire_manager)}` : ''}`
        : '<span class="muted">En attente</span>'],
      ['Validation RH', d.date_validation_rh
        ? `${ui.escape(d.nom_rh_valideur || '—')} le ${ui.date(d.date_validation_rh)}${d.commentaire_rh ? ` — ${ui.escape(d.commentaire_rh)}` : ''}`
        : '<span class="muted">En attente</span>'],
    ];

    if (d.motif_refus) {
      lignes.push(['Motif du refus', `<span class="badge danger">${ui.escape(d.motif_refus)}</span>`]);
    }
    if (d.date_annulation) {
      lignes.push(['Annulée le', ui.date(d.date_annulation)]);
    }

    detailEl.innerHTML = `
      <div class="form-grid">
        ${lignes.map(([label, valeur]) => `<p><strong>${label}</strong><br>${valeur}</p>`).join('')}
      </div>
      <h2>Solde du demandeur</h2>
      ${blocSolde(data.solde)}`;

    headingActions.innerHTML = `
      <a class="button button-secondary" href="/personnel/conges-a-valider">Retour à la liste</a>`;

    if (data.peut_valider) {
      traitementEl.classList.remove('hidden');
      actionsEl.innerHTML = `
        <button type="button" class="button-primary" id="valider-btn">Valider la demande</button>
        <button type="button" class="button-secondary" id="refuser-btn">Refuser</button>`;
      brancherActions(d);
    } else {
      traitementEl.classList.add('hidden');
    }
  }

  function brancherActions(demande) {
    const valider = document.getElementById('valider-btn');
    const refuser = document.getElementById('refuser-btn');

    valider?.addEventListener('click', async () => {
      if (!window.confirm('Valider cette demande ?')) return;
      valider.disabled = true;
      try {
        await api.post(`/api/conges/demandes/${demande.id_demande_conge}/valider`, {});
        ui.toast('Demande validée');
        charger();
      } catch (erreur) {
        valider.disabled = false;
        ui.error(erreur);
      }
    });

    refuser?.addEventListener('click', async () => {
      const motif = window.prompt('Motif du refus (obligatoire) :');
      if (motif === null) return;
      if (motif.trim() === '') {
        ui.toast('Le motif du refus est obligatoire.');
        return;
      }
      refuser.disabled = true;
      try {
        await api.post(`/api/conges/demandes/${demande.id_demande_conge}/refuser`, { motif: motif.trim() });
        ui.toast('Demande refusée');
        charger();
      } catch (erreur) {
        refuser.disabled = false;
        ui.error(erreur);
      }
    });
  }

  async function charger() {
    if (!idDemande) {
      sousTitre.textContent = '';
      detailEl.className = 'state state-error';
      detailEl.textContent = 'Aucune demande sélectionnée (paramètre id manquant).';
      colleguesEl.className = 'state';
      colleguesEl.textContent = '';
      return;
    }

    detailEl.className = 'card';
    detailEl.textContent = 'Chargement…';
    colleguesEl.className = 'state';
    colleguesEl.textContent = 'Chargement…';

    try {
      const data = await api.get(`/api/conges/demandes/${idDemande}/detail`);
      rendreDetail(data);
      blocCollegues(data);
    } catch (erreur) {
      detailEl.className = 'state state-error';
      detailEl.textContent = `Impossible de charger la demande : ${erreur.message}`;
      colleguesEl.className = 'state';
      colleguesEl.textContent = '';
      sousTitre.textContent = '';
    }
  }

  charger();
})();
