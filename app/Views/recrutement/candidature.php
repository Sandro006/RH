<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Fiche candidature</title>
    <link rel="stylesheet" href="/web/css/base.css">
    <link rel="stylesheet" href="/web/css/layout.css">
</head>

<body>
    <main>
        <div class="breadcrumb"><a href="/recrutement/candidatures">Candidatures</a> / Fiche</div>
        <h1>Candidature n°<span id="num">—</span> <span id="badge"></span></h1>
        <div data-error class="error" hidden></div>
        <section id="detail" class="card">Chargement…</section>
        <section class="card"><button onclick="act('preselection')">Présélectionner</button><button
                onclick="act('planifier-entretien')">Planifier entretien</button><button
                onclick="act('retenir')">Retenir</button><button class="danger"
                onclick="act('refuser')">Refuser</button><button class="secondary"
                onclick="act('retirer')">Retirer</button></section>
    </main>
    <script src="/web/js/api.js"></script>
    <script src="/web/js/ui.js"></script>
    <script>const id = new URLSearchParams(location.search).get('id'); async function load() { try { const x = await api.get('/api/candidatures/' + id); num.textContent = x.id_candidature; badge.innerHTML = ui.status(x.statut); detail.innerHTML = `<p><b>Candidat :</b> ${ui.escape([x.prenom, x.nom].filter(Boolean).join(' '))}</p><p><b>Email :</b> ${ui.escape(x.email || '—')}</p><p><b>Origine :</b> ${ui.escape(x.origine_candidature)}</p>` } catch (e) { ui.error(e) } } async function act(a) { try { await api.post('/api/candidatures/' + id + '/' + a); load() } catch (e) { ui.error(e) } } load()</script>
</body>

</html>