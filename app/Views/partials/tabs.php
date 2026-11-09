<?php $tabs = $tabs ?? []; $active = $active ?? ''; ?>
<?php if ($tabs !== []): ?>
    <div class="tabs" role="tablist">
        <?php foreach ($tabs as $cle => $label): ?>
            <button class="tab<?= $cle === $active ? ' active' : '' ?>" type="button" data-tab="<?= esc($cle) ?>"><?= esc($label) ?></button>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
