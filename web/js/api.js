const api = {
    async request(url, options = {}) {
        const headers = { Accept: 'application/json', ...(options.headers || {}) };
        if (!(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, { credentials: 'same-origin', ...options, headers });
        const body = await response.json().catch(() => ({}));
        if (response.status === 401) {
            sessionStorage.removeItem('user');
            if (!location.pathname.endsWith('/login.html')) location.href = '/web/login.html';
            throw new Error('Session expirée');
        }
        if (!response.ok) { const error = new Error(body.message || body.error || 'Une erreur est survenue.'); error.code = body.error; error.status = response.status; throw error; }
        return body.data ?? body;
    },
    get(url) { return this.request(url); },
    post(url, body = {}) { return this.request(url, { method: 'POST', body: JSON.stringify(body) }); },
    patch(url, body = {}) { return this.request(url, { method: 'PATCH', body: JSON.stringify(body) }); },
    delete(url) { return this.request(url, { method: 'DELETE' }); }
};
