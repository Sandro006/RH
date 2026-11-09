<!doctype html>
<html lang="fr">
<head>
    <?= view('partials/head', ['title' => $title ?? 'Gestion RH']) ?>
</head>
<body>
    <?= view('partials/header') ?>
    <div class="app-shell">
        <?= view('partials/sidebar', ['active' => $active ?? '']) ?>
        <main class="main">
            <?= view('partials/breadcrumb', ['items' => $breadcrumb ?? []]) ?>
            <?= $content ?>
        </main>
    </div>
    <?= view('partials/scripts', ['scripts' => $scripts ?? []]) ?>
</body>
</html>
