<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Déposer une candidature</title>
    <link rel="stylesheet" href="/web/css/base.css">
    <link rel="stylesheet" href="/web/css/layout.css">
</head>

<body>
    <main>
        <div class="breadcrumb">Recrutement / Candidature</div>
        <h1>Déposer une candidature</h1>
        <div data-error class="error" hidden></div>
        <form id="form" class="card">
            <div class="grid"><label>Nom *<input name="nom" required></label><label>Prénom *<input name="prenom"
                        required></label><label>Email *<input type="email" name="email"
                        required></label><label>Téléphone<input name="telephone"></label></div><label>Adresse<input
                    name="adresse"></label>
            <fieldset>
                <legend>Origine *</legend><label><input type="radio" name="origine_candidature" value="EXTERNE" checked>
                    Externe</label><label><input type="radio" name="origine_candidature" value="INTERNE"> Interne /
                    Mobilité</label><label><input type="radio" name="origine_candidature" value="COOPTATION">
                    Recommandation</label>
            </fieldset><label>CV *<input type="file" name="cv" required></label><label>Lettre de motivation<textarea
                    name="lettre_motivation"></textarea></label><label><input type="checkbox" required> J’accepte les
                mentions légales *</label>
            <div class="form-actions"><a class="button secondary"
                    href="/recrutement/vitrine-offres">Annuler</a><button>Envoyer</button></div>
        </form>
    </main>
    <script src="/web/js/api.js"></script>
    <script src="/web/js/ui.js"></script>
    <script>const offer = new URLSearchParams(location.search).get('idOffre'); form.onsubmit = async e => { e.preventDefault(); try { await api.post('/api/offres/' + offer + '/candidatures', Object.fromEntries(new FormData(form))); alert('Candidature enregistrée') } catch (e) { ui.error(e) } }</script>
</body>

</html>