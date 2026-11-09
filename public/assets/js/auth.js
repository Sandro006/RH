const auth = {
    async current() {
        let session;
        try { session = await api.get('/api/auth/session'); } catch (error) {
            if (error.code === 'CHANGEMENT_MOT_DE_PASSE_REQUIS') location.href = '/profil/mot-de-passe';
            throw error;
        }
        const user = session.utilisateur || session;
        sessionStorage.setItem('user', JSON.stringify(user));
        if (user.doitChangerMotDePasse && !location.pathname.endsWith('/profil/mot-de-passe')) {
            location.href = '/profil/mot-de-passe';
            throw new Error('Vous devez changer votre mot de passe avant de continuer');
        }
        return user;
    },
    async logout() { await api.post('/api/auth/logout'); sessionStorage.removeItem('user'); location.href = '/login'; },
    cached() { try { return JSON.parse(sessionStorage.getItem('user') || 'null'); } catch { return null; } }
};
