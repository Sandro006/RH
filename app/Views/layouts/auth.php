<!doctype html>
<html lang="fr">
<head>
    <?= view('partials/head', ['title' => $title ?? 'Gestion RH']) ?>
</head>
<body class="auth-body">
    <?= $content ?>
    <?= view('partials/scripts', [
        'core'    => false,
        'scripts' => ['/assets/js/api.js', '/assets/js/pages/login.js'],
    ]) ?>
</body>
</html>
