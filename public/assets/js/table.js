const Table = {
    render(container, options = {}) {
        const element = typeof container === 'string' ? document.querySelector(container) : container;
        if (!element) return;

        const columns = options.columns || [];
        const rows = Array.isArray(options.rows) ? [...options.rows] : [];
        const state = options.sort || {};
        const empty = options.empty || 'Aucune donnée disponible.';

        if (rows.length === 0) {
            element.innerHTML = ui.state(empty);
            return;
        }

        if (!options.onSort && state.key) {
            const column = columns.find(item => item.key === state.key);
            const direction = state.order === 'desc' ? -1 : 1;
            rows.sort((a, b) => {
                const left = column?.value ? column.value(a) : a[state.key];
                const right = column?.value ? column.value(b) : b[state.key];
                if (left == null && right == null) return 0;
                if (left == null) return -1 * direction;
                if (right == null) return 1 * direction;
                if (typeof left === 'number' && typeof right === 'number') return (left - right) * direction;
                return String(left).localeCompare(String(right), 'fr', { numeric: true, sensitivity: 'base' }) * direction;
            });
        }

        const entetes = columns.map(column => {
            const sortable = column.sort !== false;
            const tri = sortable ? (state.key === column.key ? state.order : '') : '';
            const attribut = sortable
                ? ` data-sort="${ui.escape(column.key)}" tabindex="0" role="button" aria-sort="${tri === 'asc' ? 'ascending' : tri === 'desc' ? 'descending' : 'none'}"`
                : '';
            const indicateur = sortable
                ? `<span class="sort-indicator" aria-hidden="true">${tri === 'asc' ? '▲' : tri === 'desc' ? '▼' : '↕'}</span>`
                : '';
            return `<th class="${ui.escape(column.className || '')}"${attribut}>${ui.escape(column.label || column.key)}${indicateur}</th>`;
        }).join('');

        const corps = rows.map((row, index) => {
            const cellules = columns.map(column => {
                const valeur = column.render ? column.render(row) : row[column.key];
                const contenu = column.render
                    ? valeur
                    : ui.escape(valeur == null || valeur === '' ? '—' : valeur);
                return `<td class="${ui.escape(column.cellClass || '')}">${contenu}</td>`;
            }).join('');
            return `<tr data-index="${index}">${cellules}</tr>`;
        }).join('');

        element.innerHTML = `<div class="table-wrap"><table class="data-table"><thead><tr>${entetes}</tr></thead><tbody>${corps}</tbody></table></div>`;

        if (options.onSort) {
            element.querySelectorAll('th[data-sort]').forEach(th => {
                const activer = () => {
                    const key = th.dataset.sort;
                    const order = state.key === key && state.order === 'asc' ? 'desc' : 'asc';
                    options.onSort(key, order);
                };
                th.addEventListener('click', activer);
                th.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        activer();
                    }
                });
            });
        }

        if (options.onRowClick) {
            element.querySelectorAll('tbody tr').forEach(tr => {
                tr.addEventListener('click', event => {
                    if (event.target.closest('a, button, input, select, textarea')) return;
                    const row = rows[Number(tr.dataset.index)];
                    if (row) options.onRowClick(row);
                });
            });
        }
    },

    query(endpoint, state = {}) {
        const parameters = new URLSearchParams();
        Object.entries(state).forEach(([key, value]) => {
            if (value !== '' && value != null) parameters.set(key, value);
        });
        const query = parameters.toString();
        return query ? `${endpoint}?${query}` : endpoint;
    }
};

ui.table = Table.render;
