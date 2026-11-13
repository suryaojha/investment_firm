<?php
/**
 * Generic CRUD engine for admin content tables.
 * cfg: table, title, singular, base (url incl. fixed query like items.php?type=faq), fields[], where[] (fixed column values),
 *      order (SQL), after_save (callable), help (html)
 * field: name, label, type (text|textarea|html|number|date|select|check|image|file|url), options[], help, required, list, dir, exts, max (MB), step
 */
function admin_crud(array $c): void {
    $pdo = db(); $table = $c['table']; $fields = $c['fields']; $where = $c['where'] ?? [];
    $base = $c['base']; $sep = strpos($base, '?') === false ? '?' : '&';
    $self = fn(string $q = '') => url('admin/' . $base . ($q ? $sep . $q : ''));
    $hasActive = (bool)($c['active'] ?? true);
    $conds = ''; $condVals = [];
    foreach ($where as $k => $v) { $conds .= " AND $k = ?"; $condVals[] = $v; }
    $load = function (int $id) use ($pdo, $table, $conds, $condVals) {
        $st = $pdo->prepare("SELECT * FROM $table WHERE id = ?$conds"); $st->execute(array_merge([$id], $condVals)); return $st->fetch() ?: null;
    };

    if (is_post()) {
        csrf_verify();
        $do = post('_do'); $pid = (int)post('id');
        $row = $pid ? $load($pid) : null;
        if ($do === 'delete' && $row) {
            foreach ($fields as $f) if (in_array($f['type'], ['image', 'file'], true)) delete_file($row[$f['name']] ?? null);
            $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$pid]);
            audit("$table.deleted", "#$pid"); flash('ok', 'Deleted.'); redirect($self());
        }
        if ($do === 'toggle' && $row && $hasActive) {
            $pdo->prepare("UPDATE $table SET is_active = ? WHERE id = ?")->execute([$row['is_active'] ? 0 : 1, $pid]); redirect($self());
        }
        if ($do === 'save') {
            $data = []; $errors = [];
            foreach ($fields as $f) {
                $n = $f['name']; $t = $f['type'];
                try {
                    if ($t === 'image' || $t === 'file') {
                        $exts = $f['exts'] ?? ($t === 'image' ? ['jpg', 'jpeg', 'png', 'webp', 'gif'] : ['pdf']);
                        $new = save_upload($n, $f['dir'] ?? 'uploads/images', $exts, $f['max'] ?? 8);
                        if ($new) { if ($row) delete_file($row[$n] ?? null); $data[$n] = $new; }
                        elseif (!empty($_POST['remove_' . $n]) && $row) { delete_file($row[$n] ?? null); $data[$n] = null; }
                    } elseif ($t === 'check') {
                        $data[$n] = isset($_POST[$n]) ? 1 : 0;
                    } elseif ($t === 'number') {
                        $data[$n] = post($n) === '' ? 0 : (float)post($n);
                    } elseif ($t === 'html') {
                        $data[$n] = clean_html((string)($_POST[$n] ?? ''));
                    } else {
                        $val = $t === 'textarea' ? trim((string)($_POST[$n] ?? '')) : post($n);
                        if (!empty($f['required']) && $val === '') $errors[] = $f['label'] . ' is required.';
                        $data[$n] = $val;
                    }
                } catch (Throwable $e) { $errors[] = $f['label'] . ': ' . $e->getMessage(); }
            }
            if (isset($c['validate'])) { try { $data = ($c['validate'])($data, $row); } catch (Throwable $e) { $errors[] = $e->getMessage(); } }
            if ($errors) {
                foreach ($errors as $er) flash('bad', $er);
                $_GET['action'] = $pid ? 'edit' : 'new'; $_GET['id'] = $pid; $GLOBALS['_crud_old'] = $data;
            } else {
                foreach ($where as $k => $v) $data[$k] = $v;
                if ($row) {
                    $set = implode(',', array_map(fn($k) => "$k = ?", array_keys($data)));
                    $pdo->prepare("UPDATE $table SET $set WHERE id = ?")->execute(array_merge(array_values($data), [$pid]));
                } else {
                    $cols = array_keys($data);
                    $pdo->prepare("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($data));
                }
                audit("$table." . ($row ? 'updated' : 'created'), $c['singular'] ?? $table);
                flash('ok', ($c['singular'] ?? 'Item') . ' saved.');
                redirect($self());
            }
        }
    }

    $action = get('action'); $id = (int)get('id');
    if ($action === 'new' || $action === 'edit') {
        $row = $action === 'edit' ? $load($id) : null;
        if ($action === 'edit' && !$row) { flash('bad', 'Not found.'); redirect($self()); }
        $old = $GLOBALS['_crud_old'] ?? null;
        $val = fn($f) => $old[$f['name']] ?? ($row[$f['name']] ?? ($f['default'] ?? ''));
        ?>
        <div class="card">
          <div class="card-head"><h2><?= $row ? 'Edit' : 'Add' ?> <?= e($c['singular'] ?? 'item') ?></h2><a class="btn btn-sm btn-ghost" href="<?= e($self()) ?>">← Back to list</a></div>
          <?php if (!empty($c['help'])) echo '<p class="muted">' . $c['help'] . '</p>'; ?>
          <form method="post" enctype="multipart/form-data" class="aform">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save"><input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>">
            <?php foreach ($fields as $f): $n = $f['name']; $v = $val($f); $req = !empty($f['required']) ? 'required' : ''; ?>
              <div class="field <?= in_array($f['type'], ['textarea', 'html'], true) ? 'wide' : '' ?>">
                <?php if ($f['type'] === 'check'): ?>
                  <label class="check"><input type="checkbox" name="<?= e($n) ?>" value="1" <?= $v || ($v === '' && !$row && !empty($f['default'])) ? 'checked' : '' ?>> <?= e($f['label']) ?></label>
                <?php else: ?>
                  <label for="f_<?= e($n) ?>"><?= e($f['label']) ?><?= $req ? ' *' : '' ?></label>
                  <?php if ($f['type'] === 'textarea' || $f['type'] === 'html'): ?>
                    <textarea id="f_<?= e($n) ?>" name="<?= e($n) ?>" rows="<?= $f['type'] === 'html' ? 14 : ($f['rows'] ?? 4) ?>" <?= $req ?>><?= e($v) ?></textarea>
                  <?php elseif ($f['type'] === 'select'): ?>
                    <select id="f_<?= e($n) ?>" name="<?= e($n) ?>"><?php foreach ($f['options'] as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= (string)$v === (string)$ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
                  <?php elseif ($f['type'] === 'image' || $f['type'] === 'file'): ?>
                    <?php if ($v): ?><div class="cur-file"><?php if ($f['type'] === 'image'): ?><img src="<?= e(media($v)) ?>" alt=""><?php else: ?><a href="<?= e(media($v)) ?>" target="_blank">Current file ↗</a><?php endif; ?>
                      <label class="check"><input type="checkbox" name="remove_<?= e($n) ?>" value="1"> remove</label></div><?php endif; ?>
                    <input id="f_<?= e($n) ?>" type="file" name="<?= e($n) ?>" accept="<?= e(implode(',', array_map(fn($x) => '.' . $x, $f['exts'] ?? ($f['type'] === 'image' ? ['jpg', 'jpeg', 'png', 'webp', 'gif'] : ['pdf'])))) ?>">
                  <?php else: ?>
                    <input id="f_<?= e($n) ?>" type="<?= $f['type'] === 'number' ? 'number' : ($f['type'] === 'date' ? 'date' : 'text') ?>" name="<?= e($n) ?>" value="<?= e($v) ?>" <?= $f['type'] === 'number' ? 'step="' . e($f['step'] ?? 'any') . '"' : '' ?> <?= $req ?>>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($f['help'])): ?><small><?= e($f['help']) ?></small><?php endif; ?>
              </div>
            <?php endforeach; ?>
            <div class="field wide"><button class="btn btn-primary">💾 Save</button></div>
          </form>
        </div>
        <?php
        return;
    }

    // ---- list ----
    $order = $c['order'] ?? 'sort_order, id';
    $rows = $pdo->prepare("SELECT * FROM $table WHERE 1=1$conds ORDER BY $order"); $rows->execute($condVals); $rows = $rows->fetchAll();
    ?>
    <div class="card">
      <div class="card-head"><h2><?= e($c['title']) ?> <span class="count"><?= count($rows) ?></span></h2><a class="btn btn-primary" href="<?= e($self('action=new')) ?>">+ Add <?= e($c['singular'] ?? 'item') ?></a></div>
      <?php if (!empty($c['help'])) echo '<p class="muted">' . $c['help'] . '</p>'; ?>
      <?php if (!$rows): ?><p class="muted">Nothing here yet — click “Add”.</p><?php else: ?>
      <div class="tbl-wrap"><table class="tbl">
        <thead><tr><?php foreach ($fields as $f) if (!empty($f['list'])): ?><th><?= e($f['label']) ?></th><?php endif; ?><?php if ($hasActive): ?><th>Visible</th><?php endif; ?><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?><tr>
          <?php foreach ($fields as $f) if (!empty($f['list'])): $v = $r[$f['name']] ?? ''; ?>
            <td><?php
              if ($f['type'] === 'image') echo $v ? '<img class="thumb" src="' . e(media($v)) . '" alt="">' : '—';
              elseif ($f['type'] === 'check') echo $v ? '✔' : '—';
              elseif ($f['type'] === 'select') echo e($f['options'][$v] ?? $v);
              elseif ($f['type'] === 'file') echo $v ? '<a href="' . e(media($v)) . '" target="_blank">file ↗</a>' : '—';
              elseif ($f['type'] === 'html') echo e(mb_strimwidth(strip_tags((string)$v), 0, 60, '…'));
              else echo e(mb_strimwidth((string)$v, 0, 70, '…'));
            ?></td>
          <?php endif; ?>
          <?php if ($hasActive): ?><td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_do" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="switch <?= $r['is_active'] ? 'on' : '' ?>" title="Toggle visibility"><i></i></button></form></td><?php endif; ?>
          <td class="actions">
            <a class="btn btn-sm btn-ghost" href="<?= e($self('action=edit&id=' . $r['id'])) ?>">Edit</a>
            <form method="post" class="inline" onsubmit="return confirm('Delete this permanently?')"><?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
          </td></tr><?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
    <?php
}
