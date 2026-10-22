<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Suivi de candidature</title>
    <link rel="stylesheet" href="/web/css/base.css">
    <link rel="stylesheet" href="/web/css/components.css">
</head>

<body>
    <main class="public">
        <h1>Suivi de candidature</h1>
        <form class="card" id="form"><input name="reference" placeholder="Référence" required><input type="email"
                name="email" placeholder="Email" required><button>Consulter</button></form>
        <div id="result" class="card" hidden>Le suivi est accessible depuis le lien reçu par email.</div>
    </main>
    <script>form.onsubmit = e => { e.preventDefault(); result.hidden = false }</script>
</body>

</html>