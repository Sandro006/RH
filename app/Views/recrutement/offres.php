<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Offres d’emploi</title>
    <link rel="stylesheet" href="/web/css/base.css">
    <link rel="stylesheet" href="/web/css/layout.css">
</head>

<body>
    <main>
        <div class="breadcrumb">Recrutement / Offres</div>
        <h1>Offres d’emploi</h1>
        <section class="card">
            <form id="filters" class="filters"><select name="statut">
                    <option value="">Tous les statuts</option>
                    <option>Brouillon</option>
                    <option>Publiée</option>
                    <option>Suspendue</option>
                    <option>Clôturée</option>
                </select><input name="id_demande" placeholder="Demande"><input name="id_poste"
                    placeholder="Poste"><button>Rechercher</button></form>
        </section>
        <section class="card">
            <div data-error class="error" hidden></div>
            <table class="table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Titre</th>
                        <th>Canal</th>
                        <th>Publication</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="rows">
                    <tr>
                        <td colspan="6" class="empty">Chargement…</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>
    <script src="/web/js/api.js"></script>
    <script src="/web/js/ui.js"></script>
    <script>async function load() { try { const xs = await api.get('/api/offres?' + new URLSearchParams(new FormData(filters))); rows.innerHTML = xs.length ? xs.map(x => `<tr><td>${x.id_offre}</td><td>${ui.escape(x.titre)}</td><td>${ui.escape(x.canal_diffusion || '—')}</td><td>${ui.date(x.date_publication)}</td><td>${ui.status(x.statut)}</td><td><a href="/recrutement/offre?id=${x.id_offre}">Voir</a></td></tr>`).join('') : '<tr><td colspan="6" class="empty">Aucune offre.</td></tr>' } catch (e) { ui.error(e) } } filters.onsubmit = e => { e.preventDefault(); load() }; load()</script>
</body>

</html>