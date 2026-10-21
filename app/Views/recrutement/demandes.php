<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Demandes de recrutement</title>
    <link rel="stylesheet" href="/web/css/base.css">
    <link rel="stylesheet" href="/web/css/layout.css">
    <link rel="stylesheet" href="/web/css/components.css">
</head>

<body>
    <header class="app-header"><a class="brand" href="/">Gestion RH</a><span class="user">Recrutement</span></header>
    <div class="shell">
        <aside class="sidebar">
            <nav><a class="active" href="/recrutement/demandes">Demandes</a><a href="/recrutement/offres">Offres</a><a
                    href="/recrutement/candidatures">Candidatures</a></nav>
        </aside>
        <main>
            <div class="breadcrumb">Recrutement / Demandes</div>
            <div class="page-title">
                <h1>Demandes de recrutement</h1><a class="button" href="/recrutement/demande-form">+ Nouvelle
                    demande</a>
            </div>
            <section class="card">
                <form id="filters" class="filters"><select name="statut">
                        <option value="">Tous les statuts</option>
                        <option>En cours</option>
                        <option>En contrôle RH</option>
                        <option>Validée</option>
                        <option>À corriger</option>
                    </select><input name="id_poste" placeholder="Poste"><input name="id_demandeur"
                        placeholder="Demandeur"><button>Rechercher</button></form>
            </section>
            <section class="card">
                <div data-error class="error" hidden></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Poste</th>
                                <th>Motif</th>
                                <th>Rémunération</th>
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
                </div>
            </section>
        </main>
    </div>
    <script src="/web/js/api.js"></script>
    <script src="/web/js/ui.js"></script>
    <script>async function load() { try { const xs = await api.get('/api/demandes?' + new URLSearchParams(new FormData(filters))); rows.innerHTML = xs.length ? xs.map(x => `<tr><td>${x.id_demande}</td><td>${ui.escape(x.intitule || x.id_poste)}</td><td>${ui.escape(x.motif)}</td><td>${ui.money(x.remuneration_min)} – ${ui.money(x.remuneration_max)}</td><td>${ui.status(x.designation_statut || x.statut)}</td><td><a href="/recrutement/demande?id=${x.id_demande}">Voir</a></td></tr>`).join('') : '<tr><td colspan="6" class="empty">Aucune demande.</td></tr>' } catch (e) { ui.error(e) } } filters.onsubmit = e => { e.preventDefault(); load() }; load()</script>
</body>

</html>