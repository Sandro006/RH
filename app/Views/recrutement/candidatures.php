<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Candidatures</title>
    <link rel="stylesheet" href="/web/css/base.css">
    <link rel="stylesheet" href="/web/css/layout.css">
</head>

<body>
    <main>
        <div class="breadcrumb">Recrutement / Candidatures</div>
        <h1>Candidatures</h1>
        <section class="card">
            <form id="filters" class="filters"><select name="statut">
                    <option value="">Tous les statuts</option>
                    <option>Reçue</option>
                    <option>En présélection</option>
                    <option>Présélectionnée</option>
                    <option>Refusée</option>
                </select><select name="origine_candidature">
                    <option value="">Toutes les origines</option>
                    <option>EXTERNE</option>
                    <option>INTERNE</option>
                    <option>COOPTATION</option>
                </select><input name="id_offre" placeholder="Offre"><button>Rechercher</button></form>
        </section>
        <section class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Candidat</th>
                        <th>Offre</th>
                        <th>Origine</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="rows">
                    <tr>
                        <td colspan="5" class="empty">Chargement…</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>
    <script src="/web/js/api.js"></script>
    <script src="/web/js/ui.js"></script>
    <script>async function load() { const xs = await api.get('/api/candidatures?' + new URLSearchParams(new FormData(filters))); rows.innerHTML = xs.length ? xs.map(x => `<tr><td>${ui.escape([x.prenom, x.nom].filter(Boolean).join(' ') || x.id_candidat)}</td><td>${x.id_offre}</td><td>${ui.escape(x.origine_candidature)}</td><td>${ui.status(x.statut)}</td><td><a href="/recrutement/candidature?id=${x.id_candidature}">Voir</a></td></tr>`).join('') : '<tr><td colspan="5" class="empty">Aucune candidature.</td></tr>' } filters.onsubmit = e => { e.preventDefault(); load() }; load().catch(ui.error)</script>
</body>

</html>