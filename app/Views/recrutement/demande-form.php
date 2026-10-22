<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Formulaire demande</title>
    <link rel="stylesheet" href="/web/css/base.css">
    <link rel="stylesheet" href="/web/css/layout.css">
</head>

<body>
    <main>
        <div class="breadcrumb"><a href="/recrutement/demandes">Demandes</a> / Formulaire</div>
        <h1>Nouvelle demande de recrutement</h1>
        <div data-error class="error" hidden></div>
        <form id="form" class="card">
            <div class="grid"><label>Poste *<input name="id_poste" required></label><label>Demandeur *<input
                        name="id_demandeur" required></label><label>Motif *<select name="motif">
                        <option>Création</option>
                        <option>Remplacement</option>
                        <option>Renfort</option>
                        <option>Saisonnier</option>
                    </select></label><label>Personne remplacée<input name="nom_personne_remplacee"></label><label>Type
                    de contrat *<input name="type_contrat" required></label><label>Durée<input
                        name="duree_contrat"></label><label>Date souhaitée *<input type="date" name="date_souhaitee"
                        required></label><label>Rémunération min<input type="number"
                        name="remuneration_min"></label><label>Rémunération max<input type="number"
                        name="remuneration_max"></label></div><label>Missions *<textarea name="missions"
                    required></textarea></label>
            <div class="form-actions"><a class="button secondary"
                    href="/recrutement/demandes">Annuler</a><button>Enregistrer</button></div>
        </form>
    </main>
    <script src="/web/js/api.js"></script>
    <script src="/web/js/ui.js"></script>
    <script>form.onsubmit = async e => { e.preventDefault(); try { const x = await api.post('/api/demandes', Object.fromEntries(new FormData(form))); location.href = '/recrutement/demande?id=' + (x.id_demande || x) } catch (e) { ui.error(e) } }</script>
</body>

</html>