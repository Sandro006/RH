const passwordForm = document.querySelector('#form');
const message = document.querySelector('#message');

passwordForm?.addEventListener('submit', async event => {
    event.preventDefault();

    const data = Object.fromEntries(new FormData(passwordForm));
    if (data.nouveauMotDePasse !== data.confirmation) {
        message.textContent = 'Les deux nouveaux mots de passe doivent être identiques.';
        message.className = 'error';
        message.hidden = false;
        return;
    }

    try {
        await api.patch('/api/auth/mot-de-passe', {
            ancienMotDePasse: data.ancienMotDePasse,
            nouveauMotDePasse: data.nouveauMotDePasse,
        });
        message.textContent = 'Mot de passe modifié avec succès.';
        message.className = 'success';
        message.hidden = false;
        setTimeout(() => { location.href = '/dashboard'; }, 800);
    } catch (error) {
        message.textContent = error.message;
        message.className = 'error';
        message.hidden = false;
    }
});
