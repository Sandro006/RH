<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Demandes à traiter</title>
    <link rel="stylesheet" href="/web/css/base.css">
    <link rel="stylesheet" href="/web/css/layout.css">
</head>

<body>
    <header class="app-header"><a class="brand" href="/">Gestion RH</a></header>
    <main>
        <div class="breadcrumb">Recrutement / Demandes à traiter</div>
        <h1>Mes demandes à traiter</h1>
        <section class="card">
            <div id="app" class="empty">Chargement…</div>
        </section>
    </main>
    <script src="/web/js/api.js"></script>
    <script src="/web/js/ui.js"></script>
    <script>api.get('/api/demandes').then(xs => app.innerHTML = xs.length ? '<table class="table"><tr><th>N°</th><th>Statut</th><th>Action</th></tr>' + xs.map(x => `<tr><td>${x.id_demande}</td><td>${ui.status(x.statut)}</td><td><a href="/recrutement/demande?id=${x.id_demande}">Traiter</a></td></tr>`).join('') + '</table>' : 'Aucune demande à traiter.').catch(ui.error)</script>
</body>

</html>