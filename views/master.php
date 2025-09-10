<?php
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();
$entity = $_GET['page'] ?? '';

$map = [
  'categories' => ['table' => 'categories', 'fields' => ['name','description']],
  'units' => ['table' => 'units', 'fields' => ['name','short_name']],
  'locations' => ['table' => 'locations', 'fields' => ['name','address_line','landmark','city','state','zipcode','country']],
  'suppliers' => ['table' => 'suppliers', 'fields' => ['name','phone','email','gstin','address_line','address_line_2','landmark','city','state','zipcode','country','notes']],
  'customers' => ['table' => 'customers', 'fields' => ['name','phone','email','gstin','address_line','address_line_2','landmark','city','state','zipcode','country','notes']],
];

if (!isset($map[$entity])) { echo '<p>Unknown entity</p>'; require __DIR__.'/partials/footer.php'; exit; }
$meta = $map[$entity];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $vals = [];
    foreach ($meta['fields'] as $f) { $vals[$f] = trim($_POST[$f] ?? ''); }
    
    if ($id) {
        $set = implode(', ', array_map(fn($c) => "`$c`=?", array_keys($vals)));
        $stmt = $pdo->prepare("UPDATE {$meta['table']} SET $set WHERE id=?");
        $stmt->execute(array_merge(array_values($vals), [$id]));
    } else {
        $cols = '`' . implode('`,`', array_keys($vals)) . '`';
        $placeholders = rtrim(str_repeat('?,', count($vals)), ',');
        $stmt = $pdo->prepare("INSERT INTO {$meta['table']}($cols) VALUES ($placeholders)");
        $stmt->execute(array_values($vals));
    }
    redirect_to_page($entity);
    exit;
}

if (($_GET['action'] ?? '') === 'delete') {
    csrf_verify_get();
    $id = (int)($_GET['id'] ?? 0);
    if ($id) { $pdo->prepare("DELETE FROM {$meta['table']} WHERE id=?")->execute([$id]); }
    redirect_to_page($entity);
    exit;
}

$rows = $pdo->query("SELECT * FROM {$meta['table']} ORDER BY id DESC LIMIT 200")->fetchAll();
$edit = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM {$meta['table']} WHERE id=?");
    $stmt->execute([(int)$_GET['edit_id']]);
    $edit = $stmt->fetch();
}
?>

<div class="p-6">
  <h2 class="text-2xl font-semibold mb-4"><?= ucfirst($entity) ?></h2>
  
  <form method="post" class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-6">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id'] ?? '') ?>">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php 
        $labelMap = [
            'name' => 'Name', 'gstin' => 'GSTIN', 'address_line' => 'Address Line 1', 'address_line_2' => 'Address Line 2',
            'landmark' => 'Landmark', 'city' => 'City', 'state' => 'State', 'zipcode' => 'Pincode', 'country' => 'Country',
            'phone' => 'Phone', 'email' => 'Email', 'notes' => 'Notes', 'short_name' => 'Short Name', 'description' => 'Description'
        ];
        foreach ($meta['fields'] as $f): 
            $isTextArea = in_array($f, ['notes', 'description']);
            $isFullWidth = in_array($f, ['notes', 'description']);
        ?>
        <div class="<?= $isFullWidth ? 'lg:col-span-3 md:col-span-2' : '' ?>">
            <label for="<?= $f ?>" class="block text-sm font-medium text-gray-700"><?= $labelMap[$f] ?? ucfirst(str_replace('_', ' ', $f)) ?></label>
            <?php if ($isTextArea): ?>
                <textarea id="<?= $f ?>" name="<?= $f ?>" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><?= htmlspecialchars($edit[$f] ?? '') ?></textarea>
            <?php else: ?>
                <input type="text" id="<?= $f ?>" name="<?= $f ?>" value="<?= htmlspecialchars($edit[$f] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="flex justify-end gap-3 pt-6">
      <a href="<?= page_url($entity) ?>" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700">Clear</a>
      <button type="submit" class="rounded-md bg-[var(--primary-color)] text-white px-4 py-2 text-sm font-semibold"><?= $edit ? 'Update' : 'Save' ?></button>
    </div>
  </form>

  <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <?php foreach ($meta['fields'] as $f): ?>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= $labelMap[$f] ?? ucfirst(str_replace('_',' ',$f)) ?></th>
          <?php endforeach; ?>
          <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <?php foreach ($rows as $r): ?>
          <tr>
            <?php foreach ($meta['fields'] as $f): ?>
            <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap"><?= htmlspecialchars($r[$f] ?? '') ?></td>
            <?php endforeach; ?>
            <td class="px-4 py-2 text-right space-x-3 whitespace-nowrap">
              <a class="text-[var(--primary-color)] hover:underline" href="<?= page_url($entity, ['edit_id'=>$r['id']]) ?>">Edit</a>
              <a class="text-red-600 hover:underline" href="<?= page_url($entity, ['action'=>'delete','id'=>$r['id'],'_token'=>csrf_token()]) ?>" onclick="return confirm('Delete this record?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; if (!$rows): ?>
          <tr><td colspan="<?= count($meta['fields']) + 1 ?>" class="px-4 py-6 text-center text-gray-400">No records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>