<?php
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/../includes/stock.php';
require_once __DIR__ . '/../includes/format.php';
require_once __DIR__ . '/../includes/upload.php';


$pdo = get_db();
$tab = $_GET['tab'] ?? 'list'; // Default to the list view

// Shared selects
$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$units = $pdo->query('SELECT id,name FROM units ORDER BY name')->fetchAll();
$suppliers = $pdo->query('SELECT id,name FROM suppliers ORDER BY name')->fetchAll();
$locations = $pdo->query('SELECT id,name FROM locations ORDER BY name')->fetchAll();
$firstLocation = (int)($locations[0]['id'] ?? 0);

$msg = '';
// Delete action
if ($tab==='list' && (($_GET['action'] ?? '') === 'delete')) {
  csrf_verify_get();
  $id = (int)($_GET['id'] ?? 0);
  if ($id) {
    $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
  }
  redirect_to_page('products');
  exit;
}

// Products list
$rows = $pdo->query('SELECT p.*, c.name AS category, u.name AS unit FROM products p
  LEFT JOIN categories c ON c.id=p.category_id
  LEFT JOIN units u ON u.id=p.unit_id
  ORDER BY p.id DESC LIMIT 200')->fetchAll();

?>

<div class="p-6">
  <div class="border-b border-gray-200 mb-4">
    <nav class="-mb-px flex gap-6" aria-label="Tabs">
      <a href="<?= page_url('product_new') ?>" class="whitespace-nowrap border-b-2 px-1 py-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Add Product</a>
      <a href="<?= page_url('products') ?>" class="whitespace-nowrap border-b-2 px-1 py-2 text-sm font-medium border-[var(--primary-color)] text-[var(--primary-color)]">Product List</a>
    </nav>
  </div>
  
  <div class="bg-white rounded-md border border-gray-200 shadow-sm overflow-hidden">
      <div class="p-4 flex items-center justify-between">
        <div class="text-lg font-semibold">Products</div>
        <a class="rounded-md bg-[var(--primary-color)] text-white px-3 py-2 text-sm" href="<?= page_url('product_new') ?>">+ Add Product</a>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HSN Code</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tax Rate</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
              <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="px-4 py-2">
                  <?php if (!empty($r['image_path'])): ?>
                    <img src="<?= htmlspecialchars(base_url($r['image_path'])) ?>" class="h-10 w-10 rounded object-cover" alt=""/>
                  <?php else: ?>
                    <span class="material-symbols-outlined text-gray-300">image</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2 text-sm text-gray-900"><?= htmlspecialchars($r['name']) ?></td>
                <td class="px-4 py-2 text-sm text-gray-500"><?= htmlspecialchars($r['sku'] ?? '-') ?></td>
                <td class="px-4 py-2 text-sm text-gray-500"><?= htmlspecialchars($r['hsn_code'] ?? '-') ?></td>
                <td class="px-4 py-2 text-sm text-gray-500"><?= number_format((float)($r['tax_rate'] ?? 0), 2) ?>%</td>
                <td class="px-4 py-2 text-sm text-gray-500"><?= money((float)$r['cost_price']) ?></td>
                <td class="px-4 py-2 text-sm text-gray-500"><?= money((float)$r['sell_price']) ?></td>
                <td class="px-4 py-2 text-right text-sm space-x-3">
                  <a class="text-[var(--primary-color)]" href="<?= page_url('product_view',['id'=>$r['id']]) ?>">View</a>
                  <a class="text-amber-600" href="<?= page_url('product_edit',['id'=>$r['id']]) ?>">Edit</a>
                  <a class="text-red-600" href="<?= page_url('products',['tab'=>'list','action'=>'delete','id'=>$r['id'],'_token'=>csrf_token()]) ?>" onclick="return confirm('Delete this product?')">Delete</a>
                </td>
              </tr>
            <?php endforeach; if (!$rows): ?>
              <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">No products yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>