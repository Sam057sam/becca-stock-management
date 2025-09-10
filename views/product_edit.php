<?php
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/../includes/format.php';
require_once __DIR__ . '/../includes/upload.php';


$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);
$product_stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
$product_stmt->execute([$id]);
$product = $product_stmt->fetch();
if (!$product) { echo '<div class="p-6">Product not found.</div>'; require __DIR__.'/partials/footer.php'; exit; }

$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$units = $pdo->query('SELECT id,name FROM units ORDER BY name')->fetchAll();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $name = trim($_POST['product_name'] ?? '');
  $sku = trim($_POST['sku'] ?? '');
  $hsn_code = trim($_POST['hsn_code'] ?? '');
  $tax_rate = (float)($_POST['tax_rate'] ?? 0);
  $category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
  $unit_id = $_POST['unit_id'] !== '' ? (int)$_POST['unit_id'] : null;
  $cost = (float)($_POST['cost_price'] ?? 0);
  $price = (float)($_POST['sell_price'] ?? 0);
  $description = trim($_POST['description'] ?? '');
  $reorder_level = (float)($_POST['reorder_level'] ?? ($product['reorder_level'] ?? 0));
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  if ($name === '') { $msg = 'Product name is required.'; }
  else {
    try {
        $imagePath = $product['image_path'] ?? null;
        if (!empty($_FILES['photo']['name'])) {
            $upload_dir = __DIR__ . '/../public/uploads/products';
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $new_image_path = handle_file_upload($_FILES['photo'], $upload_dir, $allowed_mime_types);
            if ($new_image_path) {
                // remove old file
                if (!empty($imagePath)) {
                  $old = __DIR__ . '/../public/' . $imagePath;
                  if (is_file($old)) @unlink($old);
                }
                $imagePath = $new_image_path;
            }
        }
      
      if (column_exists('products','image_path')) {
        $stmt = $pdo->prepare('UPDATE products SET sku=?, name=?, description=?, hsn_code=?, category_id=?, unit_id=?, cost_price=?, sell_price=?, tax_rate=?, image_path=?, reorder_level=?, is_active=? WHERE id=?');
        $stmt->execute([$sku,$name,$description,$hsn_code,$category_id,$unit_id,$cost,$price,$tax_rate,$imagePath,$reorder_level,$is_active,$id]);
      } else {
        $stmt = $pdo->prepare('UPDATE products SET sku=?, name=?, description=?, hsn_code=?, category_id=?, unit_id=?, cost_price=?, sell_price=?, tax_rate=?, reorder_level=?, is_active=? WHERE id=?');
        $stmt->execute([$sku,$name,$description,$hsn_code,$category_id,$unit_id,$cost,$price,$tax_rate,$reorder_level,$is_active,$id]);
      }
      redirect_to_page('product_view', ['id'=>$id]);
      exit;
    } catch (Throwable $e) { $msg = 'Update failed: '.$e->getMessage(); }
  }
}
?>

<div class="p-6">
  <div class="mx-auto max-w-4xl">
    <?php if ($msg): ?><div class="note mb-4"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <div class="rounded-md border border-gray-200 bg-white p-6 shadow-sm">
      <h2 class="text-xl font-semibold mb-4">Edit Product</h2>
      <form class="space-y-6" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700" for="product-name">Product Name</label>
            <input class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="product-name" name="product_name" value="<?= htmlspecialchars($product['name']) ?>" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="sku">SKU</label>
            <input class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="sku" name="sku" value="<?= htmlspecialchars($product['sku']) ?>" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="hsn-code">HSN Code</label>
            <input class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="hsn-code" name="hsn_code" value="<?= htmlspecialchars($product['hsn_code'] ?? '') ?>"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="tax-rate">Tax Rate (%)</label>
            <input class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="tax-rate" name="tax_rate" type="number" step="0.01" value="<?= htmlspecialchars($product['tax_rate'] ?? '0.00') ?>"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="category">Category</label>
            <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="category" name="category_id">
              <option value="">Select category</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (int)$product['category_id']===(int)$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="unit">Unit</label>
            <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="unit" name="unit_id">
              <option value="">Select unit</option>
              <?php foreach ($units as $u): ?>
                <option value="<?= $u['id'] ?>" <?= (int)$product['unit_id']===(int)$u['id']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="purchase-price">Purchase Price</label>
            <div class="relative mt-1 rounded-md shadow-sm">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"><span class="text-gray-500 sm:text-sm"><?= htmlspecialchars(currency_symbol()) ?></span></div>
              <input class="block w-full rounded-md border-gray-300 pl-7 pr-12 focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="purchase-price" name="cost_price" value="<?= htmlspecialchars($product['cost_price']) ?>" type="number" step="0.01"/>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="sale-price">Sale Price</label>
            <div class="relative mt-1 rounded-md shadow-sm">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"><span class="text-gray-500 sm:text-sm"><?= htmlspecialchars(currency_symbol()) ?></span></div>
              <input class="block w-full rounded-md border-gray-300 pl-7 pr-12 focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="sale-price" name="sell_price" value="<?= htmlspecialchars($product['sell_price']) ?>" type="number" step="0.01"/>
            </div>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700" for="description">Description</label>
            <textarea class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="description" name="description" rows="4"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="reorder">Reorder Level</label>
            <input class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="reorder" name="reorder_level" type="number" step="0.001" value="<?= htmlspecialchars($product['reorder_level'] ?? 0) ?>"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="photo">Replace Photo</label>
            <input type="file" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" name="photo" id="photo" accept="image/*" />
          </div>
          <div class="flex items-center gap-2">
            <input type="checkbox" id="is_active" name="is_active" <?= !empty($product['is_active'])?'checked':'' ?> />
            <label for="is_active" class="text-sm text-gray-700">Active</label>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <a class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50" href="<?= page_url('products',['tab'=>'list']) ?>">Cancel</a>
          <button class="rounded-md border border-transparent bg-[var(--primary-color)] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-opacity-90" type="submit">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>