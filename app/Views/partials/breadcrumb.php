<?php $items = $items ?? []; ?>
<?php if ($items !== []): ?>
    <nav class="breadcrumb" aria-label="Fil d'Ariane">
        <?php foreach ($items as $index => $item): ?>
            <?php if ($index > 0): ?><span aria-hidden="true"> / </span><?php endif; ?>
            <?php if (!empty($item['url']) && $index < count($items) - 1): ?>
                <a href="<?= esc($item['url']) ?>"><?= esc($item['label']) ?></a>
            <?php else: ?>
                <span><?= esc($item['label']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
