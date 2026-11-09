/* eslint-env browser */

(() => {
  const select = document.getElementById('utilisateur');
  const btnReset = document.getElementById('btnReset');
  const messageEl = document.getElementById('message');

  // Modal résultat
  const modal = document.getElementById('modalResultat');
  const resultatIdentifiant = document.getElementById('resultatIdentifiant');
  const resultatMdp = document.getElementById('resultatMdp');
  const btnCopier = document.getElementById('btnCopierResultat');

  let utilisateurs = [];

  async function chargerUtilisateurs() {
    try {
      const r = await fetch('/api/utilisateurs/', {
        headers: { Accept: 'application/json' },
      });
      if (!r.ok) throw new Error(`Erreur ${r.status}`);
      const data = await r.json();
      utilisateurs = Array.isArray(data) ? data : (data.donnees || []);

      select.innerHTML = '<option value="">Sélectionner un utilisateur</option>';
      utilisateurs.forEach((u) => {
        const option = document.createElement('option');
        option.value = u.idUtilisateur;
        option.textContent = `${u.identifiant} ${u.employeNom ? `· ${u.employeNom}` : ''} ${u.role ? `(${u.role})` : ''}`;
        select.appendChild(option);
      });
    } catch (err) {
      afficherMessage(`Impossible de charger les utilisateurs : ${err.message}`);
    }
  }

  function afficherMessage(texte, type = 'error') {
    messageEl.innerHTML = `<div class="${type === 'success' ? 'success' : 'error'}">${texte}</div>`;
    setTimeout(() => { messageEl.innerHTML = ''; }, 5000);
  }

  btnReset.addEventListener('click', async () => {
    const id = parseInt(select.value, 10);
    if (!id) {
      afficherMessage('Veuillez sélectionner un utilisateur.');
      return;
    }

    try {
      const r = await fetch(`/api/utilisateurs/${id}/reinitialiser-mot-de-passe`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
      });

      if (!r.ok) {
        const err = await r.json().catch(() => ({}));
        throw new Error(err.message || `Erreur ${r.status}`);
      }

      const data = await r.json();
      const u = utilisateurs.find((x) => x.idUtilisateur === id);

      resultatIdentifiant.textContent = `Compte : ${u ? u.identifiant : '—'}`;
      resultatMdp.textContent = data.motDePasseTemporaire || '—';
      modal.classList.remove('hidden');

      afficherMessage('Mot de passe temporaire généré avec succès.', 'success');
    } catch (err) {
      afficherMessage(`Échec : ${err.message}`);
    }
  });

  // Modal
  window.fermerModalResultat = function () {
    modal.classList.add('hidden');
  };

  modal.addEventListener('click', (e) => {
    if (e.target === modal) fermerModalResultat();
  });

  btnCopier.addEventListener('click', () => {
    const valeur = resultatMdp.textContent;
    if (valeur && valeur !== '—') {
      navigator.clipboard.writeText(valeur).then(() => {
        btnCopier.textContent = 'Copié !';
        setTimeout(() => { btnCopier.textContent = 'Copier'; }, 1500);
      });
    }
  });

  chargerUtilisateurs();
})();
