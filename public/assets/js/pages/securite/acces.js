/* eslint-env browser */

(() => {
  const API = '/api/utilisateurs';
  const container = document.getElementById('listeUtilisateurs');
  const searchInput = document.getElementById('searchInput');
  const roleFilter = document.getElementById('roleFilter');
  const actifFilter = document.getElementById('actifFilter');
  const btnRefresh = document.getElementById('btnRefresh');

  // Modal mot de passe temporaire
  const modal = document.getElementById('modalMdp');
  const mdpEmploye = document.getElementById('mdpEmploye');
  const mdpValeur = document.getElementById('mdpValeur');
  const btnCopier = document.getElementById('btnCopierMdp');

  let timerDebounce = null;

  const ROLE_LABELS = {
    admin: 'Administrateur',
    rh: 'RH',
    manager: 'Manager',
    recruteur: 'Recruteur',
    direction: 'Direction',
    controle_gestion: 'Contrôle de gestion',
    salarie: 'Salarié'
  };

  function escapeHtml(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
  }

  function chargerUtilisateurs() {
    const params = new URLSearchParams();
    const q = searchInput.value.trim();
    const role = roleFilter.value;
    const actif = actifFilter.value;

    if (q) params.set('q', q);
    if (role) params.set('role', role);
    if (actif !== '') params.set('actif', actif);
    params.set('page', '1');
    params.set('parPage', '100');

    container.innerHTML = '<p class="muted">Chargement…</p>';

    fetch(`${API}?${params.toString()}`, {
      headers: { Accept: 'application/json' }
    })
      .then(async (r) => {
        if (!r.ok) {
          const err = await r.json().catch(() => ({}));
          throw new Error(err.message || `Erreur ${r.status}`);
        }
        return r.json();
      })
      .then((data) => {
        const lignes = Array.isArray(data) ? data : (data.donnees || []);
        afficherListe(lignes);
      })
      .catch((err) => {
        container.innerHTML = `<p class="error">Erreur : ${escapeHtml(err.message)}</p>`;
      });
  }

  function afficherListe(lignes) {
    if (!lignes || lignes.length === 0) {
      container.innerHTML = '<p class="muted">Aucun utilisateur trouvé.</p>';
      return;
    }

    const lignesHTML = lignes.map((u) => {
      const actifClasse = u.actif ? 'badge-actif' : 'badge-inactif';
      const actifLibelle = u.actif ? 'Actif' : 'Inactif';
      const roleLibelle = ROLE_LABELS[u.role] || u.role;
      const employe = u.employeNom ? ` · ${escapeHtml(u.employeNom)}` : '';
      const mdpForce = u.doit_changer_mot_de_passe ? ' <span class="badge mdp-force">Changement obligatoire</span>' : '';

      const btnActif = u.actif
        ? `<button class="button-danger btn-action" onclick="toggleActif(${u.idUtilisateur}, false)" title="Désactiver">Désactiver</button>`
        : `<button class="button-success btn-action" onclick="toggleActif(${u.idUtilisateur}, true)" title="Activer">Activer</button>`;

      const btnReset = `<button class="button-primary btn-action" onclick="resetMotDePasse(${u.idUtilisateur}, '${escapeHtml(u.identifiant)}')" title="Réinitialiser le mot de passe">Réinitialiser MDP</button>`;

      return `
        <tr class="utilisateur-row">
          <td>
            <strong>${escapeHtml(u.identifiant)}</strong>${mdpForce}
            <br><span class="muted">${employe}</span>
          </td>
          <td><span class="badge role-badge">${escapeHtml(roleLibelle)}</span></td>
          <td><span class="badge ${actifClasse}">${actifLibelle}</span></td>
          <td class="actions">
            ${btnActif}
            ${btnReset}
          </td>
        </tr>
      `;
    }).join('');

    container.innerHTML = `
      <table class="table-utilisateurs">
        <thead>
          <tr>
            <th>Identifiant / Employé</th>
            <th>Rôle</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>${lignesHTML}</tbody>
      </table>
    `;
  }

  window.toggleActif = async function (id, activer) {
    const action = activer ? 'activer' : 'desactiver';
    try {
      const r = await fetch(`${API}/${id}/${action}`, {
        method: 'POST',
        headers: { Accept: 'application/json' }
      });
      if (!r.ok) throw new Error(`Erreur ${r.status}`);
      chargerUtilisateurs();
    } catch (err) {
      alert(`Impossible de ${action} l'utilisateur : ${err.message}`);
    }
  };

  window.resetMotDePasse = async function (id, identifiant) {
    try {
      const r = await fetch(`${API}/${id}/reinitialiser-mot-de-passe`, {
        method: 'POST',
        headers: { Accept: 'application/json' }
      });
      if (!r.ok) {
        const err = await r.json().catch(() => ({}));
        throw new Error(err.message || `Erreur ${r.status}`);
      }
      const data = await r.json();

      mdpEmploye.textContent = `Compte : ${escapeHtml(identifiant)}`;
      mdpValeur.textContent = data.motDePasseTemporaire || '—';
      modal.classList.remove('hidden');
    } catch (err) {
      alert(`Impossible de réinitialiser le mot de passe : ${err.message}`);
    }
  };

  window.fermerModalMdp = function () {
    modal.classList.add('hidden');
  };

  btnCopier.addEventListener('click', () => {
    const valeur = mdpValeur.textContent;
    if (valeur && valeur !== '—') {
      navigator.clipboard.writeText(valeur).then(() => {
        btnCopier.textContent = 'Copié !';
        setTimeout(() => { btnCopier.textContent = 'Copier'; }, 1500);
      });
    }
  });

  // Recherche avec debounce
  searchInput.addEventListener('input', () => {
    clearTimeout(timerDebounce);
    timerDebounce = setTimeout(chargerUtilisateurs, 300);
  });

  roleFilter.addEventListener('change', chargerUtilisateurs);
  actifFilter.addEventListener('change', chargerUtilisateurs);
  btnRefresh.addEventListener('click', chargerUtilisateurs);

  // Fermer le modal en cliquant à l'extérieur
  modal.addEventListener('click', (e) => {
    if (e.target === modal) fermerModalMdp();
  });

  chargerUtilisateurs();
})();
