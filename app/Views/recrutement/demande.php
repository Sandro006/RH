<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Fiche demande</title>
    <link rel="stylesheet" href="/web/css/base.css">
    <link rel="stylesheet" href="/web/css/layout.css">
</head>

<body>
    <main>
        <div class="breadcrumb"><a href="/recrutement/demandes">Demandes</a> / Fiche</div>
        <h1>Demande n°<span id="num">—</span> <span id="badge"></span></h1>
        <div class="steps"><span class="step">En cours</span><span class="step">En contrôle RH</span><span
                class="step">Validation budget</span><span class="step">Hiérarchie</span><span
                class="step">Validée</span></div>
        <div data-error class="error" hidden></div>
        <section id="detail" class="card">Chargement…</section>
        <section class="card"><button onclick="act('soumettre')">Soumettre</button><button class="danger"
                onclick="act('annuler')">Annuler</button></section>
    </main>
    <script src="/web/js/api.js"></script>
    <script src="/web/js/ui.js"></script>
    <script>const id = new URLSearchParams(location.search).get('id'); async function load() { try { const d = await api.get('/api/demandes/' + id); num.textContent = d.id_demande; badge.innerHTML = ui.status(d.statut); detail.innerHTML = `<p><b>Motif :</b> ${ui.escape(d.motif)}</p><p><b>Date souhaitée :</b> ${ui.date(d.date_souhaitee)}</p><p><b>Rémunération :</b> ${ui.money(d.remuneration_min)} – ${ui.money(d.remuneration_max)}</p><h3>Missions</h3><p>${ui.escape(d.missions || '—')}</p>` } catch (e) { ui.error(e) } } async function act(a) { try { await api.post('/api/demandes/' + id + '/' + a); load() } catch (e) { ui.error(e) } } load()</script>
</body>

</html>