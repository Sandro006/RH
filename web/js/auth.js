const auth = {
    async current() {
        let session;
        try { session = await api.get('/api/auth/session'); } catch (error) {
            if (error.code === 'CHANGEMENT_MOT_DE_PASSE_REQUIS') location.href = '/web/pages/profil/mot-de-passe.html';
            throw error;
        }
        sessionStorage.setItem('user', JSON.stringify(session.utilisateur || session));
        return session.utilisateur || session;
    },
    async logout() { await api.post('/api/auth/logout'); sessionStorage.removeItem('user'); location.href = '/web/login.html'; },
    cached() { try { return JSON.parse(sessionStorage.getItem('user') || 'null'); } catch { return null; } }
};
