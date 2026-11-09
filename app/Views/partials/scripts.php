<?php $core = $core ?? true; $scripts = $scripts ?? []; ?>
<?php if ($core): ?>
    <script src="/assets/js/api.js"></script>
    <script src="/assets/js/ui.js"></script>
    <script src="/assets/js/table.js"></script>
    <script src="/assets/js/auth.js"></script>
    <script src="/assets/js/page-auth.js"></script>
<?php endif; ?>
<?php foreach ($scripts as $script): ?>
    <script src="<?= esc($script) ?>"></script>
<?php endforeach; ?>
