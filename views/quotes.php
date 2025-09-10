<?php
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();
$customers = $pdo->query('SELECT id,name FROM customers ORDER BY name')->fetchAll();
$locations = $pdo->query('SELECT id,name FROM locations ORDER BY name')->fetchAll();
$products = $pdo->query('SELECT id,name,sell_price FROM products WHERE is_active=1 ORDER BY name')->fetchAll();
$message = $_GET['msg'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    csrf_verify();
    $pdo->beginTransaction();
    try {
        $customer_id = $_POST['customer_id'] !== '' ? (int)$_POST['customer_id'] : null;
        $quote_date = $_POST['quote_date'] ?: date('Y-m-d');
        $valid_until = $_POST['valid_until'] ?: null;
        $items = $_POST['items'] ?? [];
        $subtotal = 0;
        foreach ($items as $it) {
            if (!empty($it['product_id']) && (float)$it['quantity'] > 0) {
                $subtotal += (float)$it['unit_price'] * (float)$it['quantity'];
            }
        }
        $discount = (float)($_POST['discount'] ?? 0);
        $tax = (float)($_POST['tax'] ?? 0);
        $total = max(0, $subtotal - $discount + $tax);
        $stmt = $pdo->prepare('INSERT INTO quotes(customer_id, quote_no, quote_date, valid_until, status, subtotal, discount, tax, total, notes) VALUES (?,?,?,?,"draft",?,?,?,?,?)');
        $quoteNo = 'Q-' . date('ymd-His');
        $stmt->execute([$customer_id, $quoteNo, $quote_date, $valid_until, $subtotal, $discount, $tax, $total, $_POST['notes'] ?? null]);
        $quote_id = (int)$pdo->lastInsertId();
        foreach ($items as $it) {
            $pid = (int)($it['product_id'] ?? 0);
            $loc = (int)($it['location_id'] ?? 0);
            $qty = (float)($it['quantity'] ?? 0);
            $price = (float)($it['unit_price'] ?? 0);
            if ($pid && $loc && $qty > 0) {
                $line_total = $qty * $price;
                $pdo->prepare('INSERT INTO quote_items(quote_id,product_id,location_id,quantity,unit_price,line_total) VALUES (?,?,?,?,?,?)')->execute([$quote_id,$pid,$loc,$qty,$price,$line_total]);
            }
        }
        $pdo->commit();
        redirect_to_page('quote_view', ['id' => $quote_id]);
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $message = 'Error: ' . $e->getMessage();
    }
}
$recent = $pdo->query('SELECT q.id, q.quote_no, q.quote_date, q.total, q.status, COALESCE(c.name, "Walk-in") AS customer FROM quotes q LEFT JOIN customers c ON c.id=q.customer_id ORDER BY q.id DESC LIMIT 10')->fetchAll();
?>

<div class="p-6">
<h2 class="text-2xl font-semibold mb-3">Quotes/Estimates</h2>
<?php if ($message): ?><div class="note mb-3"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<form method="post" class="bg-white border border-gray-200 rounded-md p-4 shadow-sm" id="quote-form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
    <label class="text-sm text-gray-700">Customer
      <select class="mt-1 rounded-md border-gray-300 w-full" name="customer_id">
        <option value="">Walk-in</option>
        <?php foreach($customers as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm text-gray-700">Date<input class="mt-1 rounded-md border-gray-300 w-full" type="date" name="quote_date" value="<?= date('Y-m-d') ?>"></label>
    <label class="text-sm text-gray-700">Valid Until<input class="mt-1 rounded-md border-gray-300 w-full" type="date" name="valid_until"></label>
    <label class="text-sm text-gray-700">Notes<input class="mt-1 rounded-md border-gray-300 w-full" name="notes"></label>
  </div>
  <table id="items" class="min-w-full divide-y divide-gray-200 mb-3">
    <thead class="bg-gray-50"><tr><th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th><th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Location</th><th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th><th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th><th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Line Total</th><th></th></tr></thead>
    <tr class="item-row">
      <td class="px-2 py-1"><select name="items[0][product_id]" class="prod rounded-md border-gray-300 w-full"></select></td>
      <td class="px-2 py-1"><select name="items[0][location_id]" class="loc rounded-md border-gray-300 w-full"></select></td>
      <td class="px-2 py-1"><input type="number" step="0.001" name="items[0][quantity]" class="qty rounded-md border-gray-300 w-24" value="1"></td>
      <td class="px-2 py-1"><input type="number" step="0.01" name="items[0][unit_price]" class="price rounded-md border-gray-300 w-28" value="0"></td>
      <td class="px-2 py-1 line">0.00</td>
      <td class="px-2 py-1"><button class="rounded bg-gray-100 px-2" type="button" onclick="removeRow(this)">×</button></td>
    </tr>
  </table>
  <div class="mb-3"><button class="rounded-md bg-gray-100 px-3 py-2 text-sm" type="button" onclick="addRow()">+ Add Item</button></div>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3 max-w-xl ml-auto">
    <label class="text-sm text-gray-700">Subtotal<input class="mt-1 rounded-md border-gray-300 w-full" id="subtotal" name="subtotal" readonly></label>
    <label class="text-sm text-gray-700">Discount<input class="mt-1 rounded-md border-gray-300 w-full" type="number" step="0.01" name="discount" value="0" oninput="recalc()"></label>
    <label class="text-sm text-gray-700">Tax<input class="mt-1 rounded-md border-gray-300 w-full" type="number" step="0.01" name="tax" value="0" oninput="recalc()"></label>
    <label class="text-sm text-gray-700">Total<input class="mt-1 rounded-md border-gray-300 w-full" id="total" name="total" readonly></label>
  </div>
  <div class="pt-2"><button class="rounded-md bg-[var(--primary-color)] text-white px-3 py-2 text-sm" type="submit">Save Quote</button></div>
</form>

<h3 class="text-lg font-semibold mt-6 mb-2">Recent Quotes</h3>
<div class="bg-white border border-gray-200 rounded-md shadow-sm overflow-x-auto">
<table class="min-w-full divide-y divide-gray-200">
  <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quote No</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th></tr></thead>
  <?php foreach ($recent as $r): ?>
    <tr class="divide-x divide-gray-200">
      <td class="px-3 py-2 text-sm text-gray-700"><a class="text-[var(--primary-color)]" href="<?= page_url('quote_view',['id'=>$r['id']]) ?>"><?= htmlspecialchars($r['quote_no']) ?></a></td>
      <td class="px-3 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['quote_date']) ?></td>
      <td class="px-3 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['customer']) ?></td>
      <td class="px-3 py-2 text-sm text-gray-700"><?= number_format((float)$r['total'],2) ?></td>
      <td class="px-3 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['status']) ?></td>
      <td class="px-3 py-2 text-sm text-gray-700">
        <a class="text-amber-700" href="<?= page_url('quote_view',['id'=>$r['id']]) ?>">View/Convert</a>
      </td>
    </tr>
  <?php endforeach; if (!$recent): ?>
    <tr><td colspan="6" class="px-3 py-3 text-center text-gray-400">No quotes yet</td></tr>
  <?php endif; ?>
</table>
</div>
</div>
<script>
const products = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
const locations = <?= json_encode($locations, JSON_UNESCAPED_UNICODE) ?>;
function fillSelect(select, data, valueKey='id', textKey='name') { select.innerHTML = ''; data.forEach(d => { const o = document.createElement('option'); o.value = d[valueKey]; o.textContent = d[textKey]; select.appendChild(o); }); }
function addRow() { const table = document.getElementById('items'); const idx = table.querySelectorAll('.item-row').length; const tr = document.createElement('tr'); tr.className = 'item-row'; tr.innerHTML = `<td class="px-2 py-1"><select name="items[${idx}][product_id]" class="prod rounded-md border-gray-300 w-full"></select></td><td class="px-2 py-1"><select name="items[${idx}][location_id]" class="loc rounded-md border-gray-300 w-full"></select></td><td class="px-2 py-1"><input type="number" step="0.001" name="items[${idx}][quantity]" class="qty rounded-md border-gray-300 w-24" value="1"></td><td class="px-2 py-1"><input type="number" step="0.01" name="items[${idx}][unit_price]" class="price rounded-md border-gray-300 w-28" value="0"></td><td class="px-2 py-1 line">0.00</td><td class="px-2 py-1"><button class="rounded bg-gray-100 px-2" type="button" onclick="removeRow(this)">×</button></td>`; table.appendChild(tr); hydrateRow(tr); recalc(); }
function removeRow(btn) { btn.closest('tr').remove(); recalc(); }
function hydrateRow(tr) { const prodSel = tr.querySelector('.prod'); const locSel = tr.querySelector('.loc'); fillSelect(prodSel, products); fillSelect(locSel, locations); const qty = tr.querySelector('.qty'); const price = tr.querySelector('.price'); prodSel.addEventListener('change', () => { const p = products.find(x => String(x.id) === prodSel.value); if (p) price.value = p.sell_price; recalc(); }); [qty, price].forEach(el => el.addEventListener('input', recalc)); }
document.querySelectorAll('.item-row').forEach(hydrateRow);
function recalc() { let subtotal = 0; document.querySelectorAll('#items .item-row').forEach(row => { const q = parseFloat(row.querySelector('.qty').value || 0); const pr = parseFloat(row.querySelector('.price').value || 0); const lt = q * pr; subtotal += lt; row.querySelector('.line').textContent = lt.toFixed(2); }); const discount = parseFloat(document.querySelector('[name=discount]').value || 0); const tax = parseFloat(document.querySelector('[name=tax]').value || 0); document.getElementById('subtotal').value = subtotal.toFixed(2); document.getElementById('total').value = Math.max(0, subtotal - discount + tax).toFixed(2); } recalc();
</script>
<?php require_once __DIR__ . '/partials/footer.php'; ?>