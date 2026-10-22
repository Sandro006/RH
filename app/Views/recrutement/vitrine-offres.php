<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Nos offres d’emploi</title>
    <link rel="stylesheet" href="/web/css/base.css">
    <link rel="stylesheet" href="/web/css/components.css">
</head>

<body>
    <main class="public">
        <h1>Nos offres d’emploi</h1>
        <div id="list" class="grid">
            <div class="empty">Chargement…</div>
        </div>
    </main>
    <script src="/web/js/api.js"></script>
    <script src="/web/js/ui.js"></script>
    <script>api.get('/api/public/offres').then(xs => list.innerHTML = xs.length ? xs.map(x => `<article class="card"><h2>${ui.escape(x.titre)}</h2><p>${ui.escape(x.description || '')}</p><p>Clôture : ${ui.date(x.date_cloture)}</p><a class="button" href="/recrutement/candidature-form?idOffre=${x.id_offre}">Postuler</a></article>`).join('') : '<div class="empty">Aucune offre disponible.</div>').catch(ui.error)</script>
</body>

</html>