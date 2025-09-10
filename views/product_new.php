<?php
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/../includes/stock.php';
require_once __DIR__ . '/../includes/format.php';
require_once __DIR__ . '/../includes/upload.php';


$pdo = get_db();
$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$units = $pdo->query('SELECT id,name FROM units ORDER BY name')->fetchAll();
$suppliers = $pdo->query('SELECT id,name FROM suppliers ORDER BY name')->fetchAll();
$locations = $pdo->query('SELECT id,name FROM locations ORDER BY name')->fetchAll();
$firstLocation = (int)($locations[0]['id'] ?? 0);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $name = trim($_POST['product_name'] ?? '');
  $sku = trim($_POST['sku'] ?? '');
  $hsn_code = trim($_POST['hsn_code'] ?? '');
  $tax_rate = (float)($_POST['tax_rate'] ?? 0);
  $category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
  $unit_id = $_POST['unit_id'] !== '' ? (int)$_POST['unit_id'] : null;
  $supplier_id = $_POST['supplier_id'] !== '' ? (int)$_POST['supplier_id'] : null; // optional (used for initial purchase)
  $cost = (float)($_POST['cost_price'] ?? 0);
  $price = (float)($_POST['sell_price'] ?? 0);
  $initial = (float)($_POST['initial_stock'] ?? 0);
  $description = trim($_POST['description'] ?? '');
  $initial_location_id = (int)($_POST['initial_location_id'] ?? $firstLocation);
  $reorder_level = (float)($_POST['reorder_level'] ?? 0);
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  if ($name === '') {
    $msg = 'Product name is required.';
  } else {
    $pdo->beginTransaction();
    try {
      // Handle photo upload
        $imagePath = null;
        if (!empty($_FILES['photo']['name'])) {
            $upload_dir = __DIR__ . '/../public/uploads/products';
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $imagePath = handle_file_upload($_FILES['photo'], $upload_dir, $allowed_mime_types);
        }

      if (column_exists('products','image_path')) {
        $stmt = $pdo->prepare('INSERT INTO products(sku,name,description,hsn_code,category_id,unit_id,cost_price,sell_price,tax_rate,image_path,reorder_level,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$sku,$name,$description,$hsn_code,$category_id,$unit_id,$cost,$price,$tax_rate,$imagePath,$reorder_level,$is_active]);
      } else {
        $stmt = $pdo->prepare('INSERT INTO products(sku,name,description,hsn_code,category_id,unit_id,cost_price,sell_price,tax_rate,reorder_level,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$sku,$name,$description,$hsn_code,$category_id,$unit_id,$cost,$price,$tax_rate,$reorder_level,$is_active]);
      }
      $pid = (int)$pdo->lastInsertId();
      // ensure stock row
      $pdo->prepare('INSERT IGNORE INTO stock(product_id,location_id,quantity) VALUES (?,?,0)')->execute([$pid,$initial_location_id ?: $firstLocation]);
      if ($initial > 0 && ($initial_location_id ?: $firstLocation)) {
        if ($supplier_id) {
          // Create an initial purchase document
          $ref = 'INIT-'.($sku ?: ('PR-'.$pid)).'-'.date('ymd-His');
          $sub = $initial * $cost; $discount = 0; $tax = 0; $total = $sub;
          $pdo->prepare('INSERT INTO purchases(supplier_id,reference_no,purchase_date,due_date,status,subtotal,discount,tax,total,notes) VALUES (?,?,?,?,"paid",?,?,?,?,?)')
              ->execute([$supplier_id,$ref,date('Y-m-d'),date('Y-m-d'),$sub,$discount,$tax,$total,'Initial stock']);
          $purchase_id = (int)$pdo->lastInsertId();
          $pdo->prepare('INSERT INTO purchase_items(purchase_id,product_id,location_id,quantity,unit_cost,line_total) VALUES (?,?,?,?,?,?)')
              ->execute([$purchase_id,$pid,$initial_location_id,$initial,$cost,$sub]);
          adjust_stock($pid,$initial_location_id,$initial);
          // mark payment equal to total to show paid
          $pdo->prepare('INSERT INTO purchase_payments(purchase_id,payment_date,amount,method,reference,notes) VALUES (?,?,?,?,?,?)')
              ->execute([$purchase_id,date('Y-m-d'),$total,'System','INIT','Auto']);
          recalc_purchase_status($purchase_id);
        } else {
          adjust_stock($pid,$initial_location_id,$initial);
        }
      }
      $pdo->commit();
      redirect_to_page('products');
      exit;
    } catch (Throwable $e) {
      $pdo->rollBack();
      $msg = 'Could not save product: '.$e->getMessage();
    }
  }
}
?>

<main class="flex-1 px-10 py-8">
  <div class="mx-auto max-w-4xl">
    <?php if ($msg): ?><div class="note"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <div class="mb-8 rounded-md border border-gray-200 bg-white p-6 shadow-sm">
      <div class="mb-6">
        <h1 class="text-2xl font-bold leading-tight tracking-tight text-gray-900">Add New Product</h1>
        <p class="text-gray-500">Fill out the form below to add a new product to your inventory.</p>
      </div>
      <form class="space-y-6" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700" for="product-name">Product Name</label>
            <input class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="product-name" name="product_name" placeholder="e.g., Organic Bananas" type="text" required/>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="sku">SKU</label>
            <input class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="sku" name="sku" placeholder="e.g., FRU-BAN-001" type="text"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="hsn-code">HSN Code</label>
            <input class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="hsn-code" name="hsn_code" placeholder="e.g., 08039010" type="text"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="tax-rate">Tax Rate (%)</label>
            <input class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="tax-rate" name="tax_rate" placeholder="e.g., 5.00" type="number" step="0.01" value="0.00"/>
          </div>
           <div>
            <label class="block text-sm font-medium text-gray-700" for="photo">Product Photo</label>
            <input class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" type="file" id="photo" name="photo" accept="image/*"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="category">Category</label>
            <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="category" name="category_id">
              <option value="">Select category</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="unit">Unit</label>
            <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="unit" name="unit_id">
              <option value="">Select unit</option>
              <?php foreach ($units as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="supplier">Supplier (optional, used for initial stock)</label>
            <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="supplier" name="supplier_id">
              <option value="">Select supplier</option>
              <?php foreach ($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="purchase-price">Purchase Price</label>
            <div class="relative mt-1 rounded-md shadow-sm">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"><span class="text-gray-500 sm:text-sm"><?= htmlspecialchars(currency_symbol()) ?></span></div>
              <input class="block w-full rounded-md border-gray-300 pl-7 pr-12 focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="purchase-price" name="cost_price" placeholder="0.00" type="number" step="0.01"/>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="sale-price">Sale Price</label>
            <div class="relative mt-1 rounded-md shadow-sm">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"><span class="text-gray-500 sm:text-sm"><?= htmlspecialchars(currency_symbol()) ?></span></div>
              <input class="block w-full rounded-md border-gray-300 pl-7 pr-12 focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="sale-price" name="sell_price" placeholder="0.00" type="number" step="0.01"/>
            </div>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700" for="initial-stock">Initial Stock Quantity</label>
            <input class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="initial-stock" name="initial_stock" placeholder="e.g., 100" type="number" step="0.001"/>
          </div>
           <div>
            <label class="block text-sm font-medium text-gray-700" for="initial-location">Initial Stock Location</label>
            <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="initial-location" name="initial_location_id">
              <?php foreach ($locations as $l): ?>
                <option value="<?= $l['id'] ?>" <?= $l['id']===$firstLocation?'selected':'' ?>><?= htmlspecialchars($l['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700" for="reorder">Reorder Level</label>
            <input class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="reorder" name="reorder_level" type="number" step="0.001" value="0"/>
          </div>
          <div class="flex items-center gap-2 mt-6">
            <input type="checkbox" id="is_active" name="is_active" checked>
            <label for="is_active" class="text-sm text-gray-700">Active</label>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700" for="description">Description</label>
            <textarea class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[var(--primary-color)] focus:ring-[var(--primary-color)] sm:text-sm" id="description" name="description" placeholder="A short description of the product." rows="4"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <a class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50" href="<?= page_url('products',['tab'=>'list']) ?>">Cancel</a>
          <button class="rounded-md border border-transparent bg-[var(--primary-color)] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-opacity-90" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>