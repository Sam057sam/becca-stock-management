<?php
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/../includes/format.php';
$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);
$p = $pdo->prepare('SELECT p.*, c.name AS category, u.name AS unit FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN units u ON u.id=p.unit_id WHERE p.id=?');
$p->execute([$id]);
$p = $p->fetch();
if (!$p) { echo '<div class="p-6">Product not found.</div>'; require __DIR__.'/partials/footer.php'; exit; }

$stockRows = $pdo->prepare('SELECT l.id as location_id, l.name as location, IFNULL(s.quantity,0) as qty FROM locations l LEFT JOIN stock s ON s.location_id=l.id AND s.product_id=? ORDER BY l.name');
$stockRows->execute([$id]);
$stockRows = $stockRows->fetchAll();
$locs = $pdo->query('SELECT id,name FROM locations ORDER BY name')->fetchAll();

$mov = $pdo->prepare("(
  SELECT 'Purchase' AS type, p.purchase_date AS dt, p.reference_no AS ref, l.name AS location, pi.quantity AS qty, pi.unit_cost AS unit_price, pi.line_total AS total
  FROM purchase_items pi JOIN purchases p ON p.id=pi.purchase_id JOIN locations l ON l.id=pi.location_id
  WHERE pi.product_id=?
) UNION ALL (
  SELECT 'Sale' AS type, s.sale_date AS dt, s.invoice_no AS ref, l.name AS location, si.quantity AS qty, si.unit_price AS unit_price, si.line_total AS total
  FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN locations l ON l.id=si.location_id
  WHERE si.product_id=?
) ORDER BY dt DESC LIMIT 30");
$mov->execute([$id,$id]);
$moves = $mov->fetchAll();
// Handle stock adjust/transfer
if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_verify();
  if (($_POST['act'] ?? '')==='adjust') {
    $loc = (int)$_POST['adj_location_id'];
    $delta = (float)$_POST['adj_quantity'];
    $reason = trim($_POST['adj_reason'] ?? 'Manual adjust');
    if ($delta < 0) {
      $availStmt = $pdo->prepare('SELECT IFNULL(quantity,0) FROM stock WHERE product_id=? AND location_id=?');
      $availStmt->execute([$id,$loc]);
      $available = (float)$availStmt->fetchColumn();
      if ($available + $delta < -0.0001) {
        echo '<div class="p-6 text-rose-600">Insufficient stock to decrease.</div>';
      } else {
        adjust_stock($id,$loc,$delta);
        if (table_exists('stock_adjustments')) {
          $stmt=$pdo->prepare('INSERT INTO stock_adjustments(product_id,from_location_id,to_location_id,quantity,reason) VALUES (?,?,?,?,?)');
          $stmt->execute([$id,$delta<0?$loc:null,$delta>0?$loc:null,abs($delta),$reason]);
        }
        redirect_to_page('product_view', ['id'=>$id]);
        exit;
      }
    } else {
      adjust_stock($id,$loc,$delta);
      if (table_exists('stock_adjustments')) {
        $stmt=$pdo->prepare('INSERT INTO stock_adjustments(product_id,from_location_id,to_location_id,quantity,reason) VALUES (?,?,?,?,?)');
        $stmt->execute([$id,null,$loc,$delta,$reason]);
      }
      redirect_to_page('product_view', ['id'=>$id]);
      exit;
    }
  } elseif (($_POST['act'] ?? '')==='transfer') {
    $from = (int)$_POST['tr_from'];
    $to = (int)$_POST['tr_to'];
    $qty = (float)$_POST['tr_quantity'];
    if ($from && $to && $from !== $to && $qty>0) {
      $availStmt = $pdo->prepare('SELECT IFNULL(quantity,0) FROM stock WHERE product_id=? AND location_id=?');
      $availStmt->execute([$id,$from]);
      $available = (float)$availStmt->fetchColumn();
      if ($available + 0.0001 < $qty) {
        echo '<div class="p-6 text-rose-600">Insufficient stock at source.</div>';
      } else {
        adjust_stock($id,$from,-$qty);
        adjust_stock($id,$to,$qty);
        if (table_exists('stock_adjustments')) {
          $stmt=$pdo->prepare('INSERT INTO stock_adjustments(product_id,from_location_id,to_location_id,quantity,reason) VALUES (?,?,?,?,?)');
          $stmt->execute([$id,$from,$to,$qty,'Transfer']);
        }
        redirect_to_page('product_view', ['id'=>$id]);
        exit;
      }
    }
  }
}
?>

<div class="p-6">
  <div class="bg-white rounded-md border border-gray-200 shadow-sm p-6">
    <div class="flex gap-6 items-start">
      <div class="w-40 h-40 bg-gray-100 rounded overflow-hidden flex items-center justify-center">
        <?php if (!empty($p['image_path'])): ?>
          <img src="<?= htmlspecialchars($p['image_path']) ?>" alt="" class="object-cover w-full h-full"/>
        <?php else: ?>
          <span class="material-symbols-outlined text-gray-300 text-6xl">image</span>
        <?php endif; ?>
      </div>
      <div class="flex-1">
        <h2 class="text-2xl font-semibold mb-1"><?= htmlspecialchars($p['name']) ?></h2>
        <div class="text-gray-500 mb-2">SKU: <?= htmlspecialchars($p['sku'] ?: '-') ?></div>
        <div class="grid grid-cols-2 gap-4 max-w-xl text-sm">
          <div><span class="text-gray-500">Category:</span> <span class="text-gray-800"><?= htmlspecialchars($p['category'] ?: '-') ?></span></div>
          <div><span class="text-gray-500">Unit:</span> <span class="text-gray-800"><?= htmlspecialchars($p['unit'] ?: '-') ?></span></div>
          <div><span class="text-gray-500">Cost:</span> <span class="text-gray-800"><?= money((float)$p['cost_price']) ?></span></div>
          <div><span class="text-gray-500">Price:</span> <span class="text-gray-800"><?= money((float)$p['sell_price']) ?></span></div>
        </div>
        <?php if (!empty($p['description'])): ?>
          <div class="mt-3 text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($p['description']) ?></div>
        <?php endif; ?>
        <div class="mt-4 flex gap-3">
          <a class="rounded-md bg-[var(--primary-color)] text-white px-3 py-2 text-sm" href="<?= page_url('product_edit',['id'=>$p['id']]) ?>">Edit</a>
          <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('products',['tab'=>'list']) ?>">Back to list</a>
        </div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-md border border-gray-200 shadow-sm overflow-hidden">
      <div class="p-4 font-semibold">Stock by Location</div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th></tr></thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($stockRows as $r): ?>
              <tr>
                <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['location']) ?></td>
                <td class="px-4 py-2 text-sm text-gray-700 text-right"><?= (float)$r['qty'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white rounded-md border border-gray-200 shadow-sm overflow-hidden">
      <div class="p-4 font-semibold">Movement History</div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50"><tr>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ref</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
          </tr></thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($moves as $m): $sign = ($m['type']==='Sale'?-1:1); ?>
              <tr>
                <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($m['dt']) ?></td>
                <td class="px-4 py-2 text-sm <?= $m['type']==='Sale'?'text-rose-600':'text-emerald-600' ?>"><?= htmlspecialchars($m['type']) ?></td>
                <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($m['ref']) ?></td>
                <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($m['location']) ?></td>
                <td class="px-4 py-2 text-sm text-right text-gray-700"><?= $sign*(float)$m['qty'] ?></td>
                <td class="px-4 py-2 text-sm text-right text-gray-700"><?= money((float)$m['total']) ?></td>
              </tr>
            <?php endforeach; if (!$moves): ?>
              <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No movements yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-md border border-gray-200 shadow-sm p-4">
      <h3 class="font-semibold mb-3">Adjust Stock</h3>
      <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="adjust">
        <label class="text-sm text-gray-700">Location
          <select class="mt-1 rounded-md border-gray-300 w-full" name="adj_location_id">
            <?php foreach ($locs as $l): ?>
              <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="text-sm text-gray-700">Quantity (+/-)
          <input class="mt-1 rounded-md border-gray-300 w-full" type="number" step="0.001" name="adj_quantity" value="0">
        </label>
        <label class="text-sm text-gray-700 md:col-span-3">Reason
          <input class="mt-1 rounded-md border-gray-300 w-full" name="adj_reason" placeholder="Manual adjust">
        </label>
        <div class="md:col-span-3"><button class="rounded-md bg-[var(--primary-color)] text-white px-3 py-2 text-sm" type="submit">Apply Adjustment</button></div>
      </form>
    </div>
    <div class="bg-white rounded-md border border-gray-200 shadow-sm p-4">
      <h3 class="font-semibold mb-3">Transfer Between Locations</h3>
      <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="transfer">
        <label class="text-sm text-gray-700">From
          <select class="mt-1 rounded-md border-gray-300 w-full" name="tr_from">
            <?php foreach ($locs as $l): ?>
              <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="text-sm text-gray-700">To
          <select class="mt-1 rounded-md border-gray-300 w-full" name="tr_to">
            <?php foreach ($locs as $l): ?>
              <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="text-sm text-gray-700">Quantity
          <input class="mt-1 rounded-md border-gray-300 w-full" type="number" step="0.001" name="tr_quantity" value="0">
        </label>
        <div class="md:col-span-3"><button class="rounded-md bg-[var(--primary-color)] text-white px-3 py-2 text-sm" type="submit">Transfer</button></div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
