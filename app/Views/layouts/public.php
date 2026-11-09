<!doctype html>
<html lang="fr">
<head>
    <?= view('partials/head', ['title' => $title ?? 'Candidatures · Gestion RH']) ?>
</head>
<body>
    <header class="public-header">
        <a class="brand" href="/candidatures"><span class="brand-mark">RH</span>Gestion RH</a>
        <a class="button button-secondary" href="/login">Espace professionnel</a>
    </header>
    <?= $content ?>
    <?= view('partials/scripts', ['core' => false, 'scripts' => $scripts ?? []]) ?>
</body>
</html>
