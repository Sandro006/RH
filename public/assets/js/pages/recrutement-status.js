async function synchroniserStatutsRecrutement() {
    const path = location.pathname;
    const type = path.includes('offres') ? 'offres' : path.includes('candidatures') ? 'candidatures' : null;
    if (!type) return;

    try {
        const response = await api.get(`/api/${type}`);
        const data = Array.isArray(response) ? response : (response.data || []);
        const rows = document.querySelectorAll('#list tbody tr');
        data.forEach((item, index) => {
            const cells = rows[index]?.children;
            if (!cells) return;
            if (type === 'offres') {
                cells[4].innerHTML = ui.status(item.statut_designation || item.statut || '—');
            } else {
                cells[1].textContent = item.offre_titre || item.id_offre || '—';
                cells[3].innerHTML = ui.status(item.statut_designation || item.statut || '—');
            }
        });
    } catch (_) {
        // Le chargement principal affiche déjà l'erreur éventuelle.
    }
}

synchroniserStatutsRecrutement();
document.querySelector('#refresh')?.addEventListener('click', () => {
    setTimeout(synchroniserStatutsRecrutement, 150);
});
