<div class="page-heading">
    <div>
        <h1><?= esc($heading ?? '') ?></h1>
        <?php if (!empty($subtitle)): ?>
            <p class="muted"><?= esc($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($actions)): ?>
        <div class="actions"><?= $actions ?></div>
    <?php endif; ?>
</div>
