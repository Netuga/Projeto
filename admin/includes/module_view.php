<?php
$records = adminListRecords($conn, $module);
?>
<div class="module-toolbar">
  <div><h2><?= adminEscape($module['title']) ?></h2><p><?= adminEscape($module['description']) ?></p></div>
  <?php if (empty($module['readonly'])): ?><a class="admin-button" href="?module=<?= rawurlencode($moduleKey) ?>#record-form">+ Novo registo</a><?php endif; ?>
</div>

<?php if (empty($module['readonly'])): ?>
<form id="record-form" class="record-form" method="post" action="?module=<?= rawurlencode($moduleKey) ?>">
  <input type="hidden" name="csrf_token" value="<?= adminEscape($csrf) ?>">
  <input type="hidden" name="id" value="<?= adminEscape($editing['id'] ?? '') ?>">
  <h3><?= $editing ? 'Editar registo' : 'Criar registo' ?></h3>
  <div class="record-fields">
    <?php foreach ($module['fields'] as $field): $value = $editing[$field['name']] ?? ($field['default'] ?? ''); ?>
      <label class="record-field<?= ($field['wide'] ?? false) ? ' wide' : '' ?>">
        <span><?= adminEscape($field['label']) ?></span>
        <?php if (($field['type'] ?? 'text') === 'textarea'): ?>
          <textarea name="<?= adminEscape($field['name']) ?>" rows="4" <?= !empty($field['required']) ? 'required' : '' ?>><?= adminEscape($value) ?></textarea>
        <?php elseif (($field['type'] ?? '') === 'checkbox'): ?>
          <input type="checkbox" name="<?= adminEscape($field['name']) ?>" value="1" <?= (string)$value === '1' ? 'checked' : '' ?>>
        <?php else: ?>
          <input type="<?= adminEscape($field['type'] ?? 'text') ?>" name="<?= adminEscape($field['name']) ?>" value="<?= adminEscape($value) ?>" <?= !empty($field['required']) ? 'required' : '' ?> <?= isset($field['step']) ? 'step="' . adminEscape($field['step']) . '"' : '' ?>>
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
  </div>
  <div class="form-actions"><button class="admin-button" type="submit"><?= $editing ? 'Guardar alterações' : 'Criar' ?></button><?php if ($editing): ?><a class="admin-button secondary" href="?module=<?= rawurlencode($moduleKey) ?>">Cancelar</a><?php endif; ?></div>
</form>
<?php endif; ?>

<div class="table-wrap">
  <table class="admin-table">
    <thead><tr><th>ID</th><?php foreach ($module['columns'] as $column): ?><th><?= adminEscape($column['label']) ?></th><?php endforeach; ?><?php if (empty($module['readonly'])): ?><th>Ações</th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($records as $record): ?>
        <tr><td><?= (int)$record['id'] ?></td>
          <?php foreach ($module['columns'] as $column): ?><td><?= adminEscape($record[$column['name']] ?? '') ?></td><?php endforeach; ?>
          <?php if (empty($module['readonly'])): ?><td class="row-actions"><a href="?module=<?= rawurlencode($moduleKey) ?>&amp;edit=<?= (int)$record['id'] ?>#record-form">Editar</a><form method="post" action="?module=<?= rawurlencode($moduleKey) ?>" onsubmit="return confirm('Remover este registo?')"><input type="hidden" name="csrf_token" value="<?= adminEscape($csrf) ?>"><input type="hidden" name="admin_action" value="delete"><input type="hidden" name="id" value="<?= (int)$record['id'] ?>"><button class="text-button danger" type="submit">Remover</button></form></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$records): ?><tr><td colspan="<?= count($module['columns']) + (empty($module['readonly']) ? 2 : 1) ?>" class="empty-state">Ainda não há registos.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
