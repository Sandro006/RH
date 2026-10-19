const api = (() => {
    async function request(url, options = {}) {
        const headers = { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(options.headers || {}) };
        const token = sessionStorage.getItem('token');
        if (token) headers.Authorization = `Bearer ${token}`;
        const response = await fetch(url, { ...options, headers });
        if (response.status === 401) {
            window.location.href = '/login.html';
            throw new Error('Session expirée.');
        }
        const text = await response.text();
        let data = null;
        try { data = text ? JSON.parse(text) : null; } catch { data = text; }
        if (!response.ok) throw new Error(data?.message || data?.error || `Erreur HTTP ${response.status}`);
        return data;
    }
    return {
        get: url => request(url),
        post: (url, body = {}) => request(url, { method: 'POST', body: JSON.stringify(body) }),
        patch: (url, body = {}) => request(url, { method: 'PATCH', body: JSON.stringify(body) }),
        del: url => request(url, { method: 'DELETE' })
    };
})();
