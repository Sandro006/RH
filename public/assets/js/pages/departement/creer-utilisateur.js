/* eslint-env browser */

(async () => {
  const form = document.getElementById('formCreerUtilisateur');
  const salarieSelect = document.getElementById('idSalarie');
  const posteSelect = document.getElementById('idPoste');
  const messageEl = document.getElementById('message');

  let salaries = [];
  let postes = [];

  function libelleSalarie(s) {
    const nom = [s.prenom, s.nom].filter(Boolean).join(' ').trim();
    return `${s.matricule ? `${s.matricule} — ` : ''}${nom || `Salarié #${s.id_salarie}`}`.trim();
  }

  async function chargerSalaries() {
    try {
      const r = await fetch('/api/salaries', {
        headers: { Accept: 'application/json' },
      });
      if (!r.ok) throw new Error(`Erreur ${r.status}`);
      const data = await r.json();
      salaries = Array.isArray(data) ? data : (data.donnees || []);

      salarieSelect.innerHTML = '<option value="">Sélectionner un salarié</option>';
      salaries.forEach((s) => {
        const option = document.createElement('option');
        option.value = s.id_salarie;
        option.textContent = libelleSalarie(s);
        salarieSelect.appendChild(option);
      });
    } catch (err) {
      messageEl.innerHTML = `<div class="error">Impossible de charger les salariés : ${err.message}</div>`;
    }
  }

  async function chargerPostes() {
    try {
      const r = await fetch('/api/referentiels/postes', {
        headers: { Accept: 'application/json' },
      });
      if (!r.ok) throw new Error(`Erreur ${r.status}`);
      const data = await r.json();
      postes = Array.isArray(data) ? data : (data.donnees || []);

      posteSelect.innerHTML = '<option value="">Sélectionner un poste</option>';
      postes.forEach((p) => {
        const option = document.createElement('option');
        option.value = p.id;
        option.textContent = p.libelle;
        posteSelect.appendChild(option);
      });
    } catch (err) {
      messageEl.innerHTML = `<div class="error">Impossible de charger les postes : ${err.message}</div>`;
    }
  }

  salarieSelect.addEventListener('change', () => {
    const id = parseInt(salarieSelect.value, 10);
    const s = salaries.find((sal) => parseInt(sal.id_salarie, 10) === id);
    const info = document.getElementById('salarieInfo');
    if (info) info.textContent = s ? `Matricule : ${s.matricule || '—'} · Statut : ${s.statut_designation || '—'}` : '';
  });

  function afficherMessage(texte, type = 'error') {
    messageEl.innerHTML = `<div class="${type === 'success' ? 'success' : 'error'}">${texte}</div>`;
    setTimeout(() => { messageEl.innerHTML = ''; }, 5000);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    messageEl.innerHTML = '';

    const donnees = {
      identifiant: document.getElementById('identifiant').value.trim(),
      motDePasse: document.getElementById('motDePasse').value,
      role: document.getElementById('role').value,
      idSalarie: salarieSelect.value ? parseInt(salarieSelect.value, 10) : null,
      idPoste: posteSelect.value ? parseInt(posteSelect.value, 10) : null,
      actif: true,
    };

    if (!donnees.identifiant || !donnees.motDePasse || !donnees.role || !donnees.idSalarie || !donnees.idPoste) {
      afficherMessage('Tous les champs sont requis.');
      return;
    }
    if (donnees.motDePasse.length < 8) {
      afficherMessage('Le mot de passe doit contenir au moins 8 caractères.');
      return;
    }

    try {
      const r = await fetch('/api/utilisateurs/', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(donnees),
      });

      if (!r.ok) {
        const err = await r.json().catch(() => ({}));
        throw new Error(err.message || `Erreur ${r.status}`);
      }

      const data = await r.json();
      afficherMessage(`Utilisateur "${donnees.identifiant}" créé avec succès (ID ${data.idUtilisateur}).`, 'success');
      form.reset();
      if (document.getElementById('salarieInfo')) document.getElementById('salarieInfo').textContent = '';
    } catch (err) {
      afficherMessage(`Échec : ${err.message}`);
    }
  });

  await Promise.all([chargerSalaries(), chargerPostes()]);
})();
