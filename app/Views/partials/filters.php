<?php
$id = $id ?? 'filters';
$fields = $fields ?? [];
$submitLabel = $submitLabel ?? 'Rechercher';
?>
<form id="<?= esc($id) ?>" class="filters">
    <?php foreach ($fields as $field): ?>
        <label>
            <?= esc($field['label'] ?? $field['name']) ?>
            <?php if (($field['type'] ?? 'text') === 'select'): ?>
                <select name="<?= esc($field['name']) ?>">
                    <?php foreach ($field['options'] ?? [] as $value => $label): ?>
                        <option value="<?= esc((string) $value) ?>"><?= esc((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input
                    type="<?= esc($field['type'] ?? 'text') ?>"
                    name="<?= esc($field['name']) ?>"
                    <?= isset($field['placeholder']) ? 'placeholder="' . esc($field['placeholder']) . '"' : '' ?>
                    <?= isset($field['min']) ? 'min="' . esc((string) $field['min']) . '"' : '' ?>
                    <?= isset($field['step']) ? 'step="' . esc((string) $field['step']) . '"' : '' ?>
                >
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    <button class="button-primary" type="submit"><?= esc($submitLabel) ?></button>
</form>
