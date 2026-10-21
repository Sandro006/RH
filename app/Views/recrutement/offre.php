<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Fiche offre</title>
    <link rel="stylesheet" href="/web/css/base.css">
    <link rel="stylesheet" href="/web/css/layout.css">
</head>

<body>
    <main>
        <div class="breadcrumb"><a href="/recrutement/offres">Offres</a> / Fiche</div>
        <h1 id="title">Offre</h1>
        <div id="badge"></div>
        <div data-error class="error" hidden></div>
        <section id="detail" class="card">Chargement…</section>
        <section class="card"><button onclick="act('publier')">Publier</button><button
                onclick="act('suspendre')">Suspendre</button><button onclick="act('clore')">Clôturer</button></section>
    </main>
    <script src="/web/js/api.js"></script>
    <script src="/web/js/ui.js"></script>
    <script>const id = new URLSearchParams(location.search).get('id'); async function load() { try { const x = await api.get('/api/offres/' + id); title.textContent = `Offre n°${x.id_offre} — ${x.titre}`; badge.innerHTML = ui.status(x.statut); detail.innerHTML = `<p><b>Canal :</b> ${ui.escape(x.canal_diffusion || '—')}</p><p><b>Publication :</b> ${ui.date(x.date_publication)} · <b>Clôture :</b> ${ui.date(x.date_cloture)}</p><h3>Description</h3><p>${ui.escape(x.description || '—')}</p>` } catch (e) { ui.error(e) } } async function act(a) { try { await api.post('/api/offres/' + id + '/' + a); load() } catch (e) { ui.error(e) } } load()</script>
</body>

</html>