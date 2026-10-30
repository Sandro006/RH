const onboardingId = new URLSearchParams(location.search).get('id');
const form = document.querySelector('#onboarding-form');
const cancelBtn = document.querySelector('#cancel');

async function loadOnboarding() {
    if (!onboardingId) {
        document.querySelector('#form-container').innerHTML = ui.state('Identifiant onboarding manquant.', true);
        return;
    }

    try {
        const data = await api.get(`/api/onboardings/${onboardingId}`);
        document.querySelector('#id_onboarding').value = data.id_onboarding;
        document.querySelector('#date_debut').value = data.date_debut || '';
        document.querySelector('#date_fin').value = data.date_fin || '';
    } catch (error) {
        document.querySelector('#form-container').innerHTML = ui.state(error.message, true);
    }
}

form.addEventListener('submit', async event => {
    event.preventDefault();
    
    const payload = {
        date_debut: document.querySelector('#date_debut').value,
        date_fin: document.querySelector('#date_fin').value || null,
    };

    try {
        await api.patch(`/api/onboardings/${onboardingId}`, payload);
        ui.toast('Onboarding modifié avec succès.');
        location.href = `/personnel/onboarding?id=${encodeURIComponent(onboardingId)}`;
    } catch (error) {
        ui.error(error);
    }
});

cancelBtn.addEventListener('click', () => {
    location.href = `/personnel/onboarding?id=${encodeURIComponent(onboardingId)}`;
});

loadOnboarding();
