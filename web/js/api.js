const api = { 
    async request(url, options = {}) { 
        const token = sessionStorage.getItem('token'); 
        const r = await fetch(url, { 
            ...options, headers: { 'Content-Type': 'application/json', 
            ...(token ? { Authorization: `Bearer ${token}` } : {}), 
            ...(options.headers || {}) } }); 
            
            if (r.status === 401) { 
                sessionStorage.removeItem('token'); 
                location.href = '../login.html'; throw Error('Session expirée') 
            } 
            const body = await r.json().catch(() => ({})); 
            
            if (!r.ok) throw Error(body.error || 'Une erreur est survenue'); 
            return body.data ?? body 
        }, 
        get(url) { return this.request(url) }, 
        post(url, body = {}) { 
            return this.request(url, { method: 'POST', body: JSON.stringify(body) }) 
        }, 
        patch(url, body = {}) { 
            return this.request(url, { method: 'PATCH', body: JSON.stringify(body) }) 
        },
        delete(url) { return this.request(url, { method: 'DELETE' }) }
        
};
