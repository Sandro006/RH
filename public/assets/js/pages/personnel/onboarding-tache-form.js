const tacheId = new URLSearchParams(location.search).get('id');
const onboardingId = new URLSearchParams(location.search).get('onboarding');
const form = document.querySelector('#tache-form');
const cancelBtn = document.querySelector('#cancel');

async function loadTache() {
    if (!tacheId) {
        document.querySelector('#form-container').innerHTML = ui.state('Identifiant de tâche manquant.', true);
        return;
    }

    try {
        const tache = await api.get(`/api/onboardings/taches/${tacheId}`);
        document.querySelector('#id_tache').value = tache.id_tache;
        document.querySelector('#id_onboarding').value = tache.id_onboarding || onboardingId;
        document.querySelector('#intitule').value = tache.intitule || '';
        document.querySelector('#description').value = tache.description || '';
        document.querySelector('#responsable').value = tache.responsable || '';
        document.querySelector('#date_echeance').value = tache.date_echeance || '';
    } catch (error) {
        document.querySelector('#form-container').innerHTML = ui.state(error.message, true);
    }
}

form.addEventListener('submit', async event => {
    event.preventDefault();
    
    const payload = {
        intitule: document.querySelector('#intitule').value,
        description: document.querySelector('#description').value || null,
        responsable: document.querySelector('#responsable').value || null,
        date_echeance: document.querySelector('#date_echeance').value || null,
    };

    try {
        await api.patch(`/api/onboardings/taches/${tacheId}`, payload);
        ui.toast('Tâche modifiée avec succès.');
        location.href = `/personnel/onboarding?id=${encodeURIComponent(document.querySelector('#id_onboarding').value)}`;
    } catch (error) {
        ui.error(error);
    }
});

cancelBtn.addEventListener('click', () => {
    location.href = `/personnel/onboarding?id=${encodeURIComponent(document.querySelector('#id_onboarding').value)}`;
});

loadTache();
