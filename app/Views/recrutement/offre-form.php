<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Formulaire offre</title>
    <link rel="stylesheet" href="/web/css/base.css">
    <link rel="stylesheet" href="/web/css/layout.css">
</head>

<body>
    <main>
        <div class="breadcrumb"><a href="/recrutement/offres">Offres</a> / Formulaire</div>
        <h1>Modifier l’offre brouillon</h1>
        <div data-error class="error" hidden></div>
        <form id="form" class="card"><label>Titre *<input name="titre" required></label><label>Description<textarea
                    name="description"></textarea></label><label>Canal de diffusion<input
                    name="canal_diffusion"></label><label>Date de clôture<input type="date" name="date_cloture"></label>
            <div class="form-actions"><a class="button secondary"
                    href="/recrutement/offres">Annuler</a><button>Enregistrer</button></div>
        </form>
    </main>
    <script src="/web/js/api.js"></script>
    <script src="/web/js/ui.js"></script>
    <script>const id = new URLSearchParams(location.search).get('id'); api.get('/api/offres/' + id).then(x => Object.entries(x).forEach(([k, v]) => { if (form.elements[k]) form.elements[k].value = v || '' })).catch(ui.error); form.onsubmit = async e => { e.preventDefault(); try { await api.patch('/api/offres/' + id, Object.fromEntries(new FormData(form))); location.href = '/recrutement/offre?id=' + id } catch (e) { ui.error(e) } }</script>
</body>

</html>