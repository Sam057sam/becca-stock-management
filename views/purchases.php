<?php
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();

$suppliers = $pdo->query('SELECT id,name,gstin,state FROM suppliers ORDER BY name')->fetchAll();
$locations = $pdo->query('SELECT id,name FROM locations ORDER BY name')->fetchAll();
$products = $pdo->query('SELECT id,name,cost_price,hsn_code,tax_rate FROM products WHERE is_active=1 ORDER BY name')->fetchAll();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    csrf_verify();
    $pdo->beginTransaction();
    try {
        $supplier_id = (int)$_POST['supplier_id'];
        $purchase_date = $_POST['purchase_date'] ?: date('Y-m-d');
        $due_date = $_POST['due_date'] ?: null;
        $place_of_supply = trim($_POST['place_of_supply'] ?? '');
        $gstin = trim($_POST['gstin'] ?? '');
        $items = $_POST['items'] ?? [];
        
        $subtotal = 0; $total_cgst = 0; $total_sgst = 0; $total_igst = 0;
        $company_state = setting('company_state', '');
        
        foreach ($items as $it) {
            $qty = (float)($it['quantity'] ?? 0);
            $cost = (float)($it['unit_cost'] ?? 0);
            $tax_rate = (float)($it['tax_rate'] ?? 0);

            if (!empty($it['product_id']) && $qty > 0) {
                $line_total = $cost * $qty;
                $subtotal += $line_total;

                $taxable_amount = $line_total;
                if (strtolower(trim($place_of_supply)) === strtolower(trim($company_state))) {
                    $cgst = ($taxable_amount * ($tax_rate / 2)) / 100;
                    $sgst = ($taxable_amount * ($tax_rate / 2)) / 100;
                    $igst = 0;
                } else {
                    $cgst = 0;
                    $sgst = 0;
                    $igst = ($taxable_amount * $tax_rate) / 100;
                }
                $total_cgst += $cgst;
                $total_sgst += $sgst;
                $total_igst += $igst;
            }
        }
        $discount = (float)($_POST['discount'] ?? 0);
        $total_tax = $total_cgst + $total_sgst + $total_igst;
        $total = max(0, $subtotal - $discount + $total_tax);

        $ref = 'PO-' . date('ymd-His');
        $stmt = $pdo->prepare('INSERT INTO purchases(supplier_id, gstin, place_of_supply, reference_no, purchase_date, due_date, status, subtotal, discount, tax, total_cgst, total_sgst, total_igst, total, notes) VALUES (?,?,?,?,?,?, "unpaid", ?,?,?,?,?,?,?,?)');
        $stmt->execute([$supplier_id, $gstin, $place_of_supply, $ref, $purchase_date, $due_date, $subtotal, $discount, $total_tax, $total_cgst, $total_sgst, $total_igst, $total, $_POST['notes'] ?? null]);
        $purchase_id = (int)$pdo->lastInsertId();

        foreach ($items as $it) {
            $pid = (int)($it['product_id'] ?? 0);
            $loc = (int)($it['location_id'] ?? 0);
            $qty = (float)($it['quantity'] ?? 0);
            $cost = (float)($it['unit_cost'] ?? 0);
            $tax_rate = (float)($it['tax_rate'] ?? 0);

            if ($pid && $loc && $qty > 0) {
                $line_total = $qty * $cost;
                $taxable_amount = $line_total;
                 if (strtolower(trim($place_of_supply)) === strtolower(trim($company_state))) {
                    $cgst = ($taxable_amount * ($tax_rate / 2)) / 100;
                    $sgst = ($taxable_amount * ($tax_rate / 2)) / 100;
                    $igst = 0;
                } else {
                    $cgst = 0;
                    $sgst = 0;
                    $igst = ($taxable_amount * $tax_rate) / 100;
                }
                $pdo->prepare('INSERT INTO purchase_items(purchase_id,product_id,location_id,quantity,unit_cost,line_total,tax_rate,cgst,sgst,igst) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$purchase_id,$pid,$loc,$qty,$cost,$line_total,$tax_rate,$cgst,$sgst,$igst]);
                if (!adjust_stock($pid, $loc, $qty)) throw new Exception('Failed to adjust stock');
            }
        }
        $pdo->commit();
        recalc_purchase_status($purchase_id);
        redirect_to_page('purchases');
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $message = 'Error: ' . $e->getMessage();
    }
}

if (($_POST['action'] ?? '') === 'pay') {
    csrf_verify();
    $purchase_id = (int)$_POST['purchase_id'];
    $amount = (float)$_POST['amount'];
    $date = $_POST['payment_date'] ?: date('Y-m-d');
    $stmt = $pdo->prepare('INSERT INTO purchase_payments(purchase_id,payment_date,amount,method,reference,notes) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$purchase_id,$date,$amount,$_POST['method'] ?? null,$_POST['reference'] ?? null,$_POST['pnotes'] ?? null]);
    recalc_purchase_status($purchase_id);
    redirect_to_page('purchases');
    exit;
}

$recent = $pdo->query('SELECT p.id, p.reference_no, p.purchase_date, p.total, p.status, s.name AS supplier FROM purchases p JOIN suppliers s ON s.id=p.supplier_id ORDER BY p.id DESC LIMIT 10')->fetchAll();
?>

<div class="p-6">
<h2 class="text-2xl font-semibold mb-3">Purchases</h2>
<?php if ($message): ?><div class="note mb-3"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<form method="post" class="bg-white border border-gray-200 rounded-md p-4 shadow-sm" id="purchase-form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
    <div>
        <label for="supplier_id" class="block text-sm font-medium text-gray-700">Supplier</label>
        <select id="supplier_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" name="supplier_id">
            <?php foreach($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="place_of_supply" class="block text-sm font-medium text-gray-700">Place of Supply (State)</label>
        <input id="place_of_supply" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" name="place_of_supply" required>
    </div>
     <div>
        <label for="gstin" class="block text-sm font-medium text-gray-700">Supplier GSTIN</label>
        <input id="gstin" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" name="gstin">
    </div>
    <div>
        <label for="purchase_date" class="block text-sm font-medium text-gray-700">Date</label>
        <input id="purchase_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" type="date" name="purchase_date" value="<?= date('Y-m-d') ?>">
    </div>
    <div>
        <label for="due_date" class="block text-sm font-medium text-gray-700">Due Date</label>
        <input id="due_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" type="date" name="due_date">
    </div>
     <div class="md:col-span-3">
        <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
        <input id="notes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" name="notes">
    </div>
  </div>
  
  <div class="overflow-x-auto">
    <table id="items" class="min-w-full divide-y divide-gray-200 mb-3 text-sm">
      <thead class="bg-gray-50">
          <tr>
              <th class="px-2 py-2 text-left font-medium text-gray-500 uppercase">Product</th>
              <th class="px-2 py-2 text-left font-medium text-gray-500 uppercase">HSN</th>
              <th class="px-2 py-2 text-left font-medium text-gray-500 uppercase">Location</th>
              <th class="px-2 py-2 text-left font-medium text-gray-500 uppercase">Qty</th>
              <th class="px-2 py-2 text-left font-medium text-gray-500 uppercase">Unit Cost</th>
              <th class="px-2 py-2 text-left font-medium text-gray-500 uppercase">Taxable Amt</th>
              <th class="px-2 py-2 text-left font-medium text-gray-500 uppercase">Tax Rate (%)</th>
              <th class="px-2 py-2 text-left font-medium text-gray-500 uppercase">Tax Amt</th>
              <th class="px-2 py-2 text-left font-medium text-gray-500 uppercase">Line Total</th>
              <th></th>
          </tr>
      </thead>
      <tbody>
          <tr class="item-row">
            <td class="px-2 py-1"><select name="items[0][product_id]" class="prod rounded-md border-gray-300 w-full min-w-[150px]"></select></td>
            <td class="px-2 py-1"><input type="text" name="items[0][hsn_code]" class="hsn rounded-md border-gray-300 w-24" readonly></td>
            <td class="px-2 py-1"><select name="items[0][location_id]" class="loc rounded-md border-gray-300 w-full min-w-[100px]"></select></td>
            <td class="px-2 py-1"><input type="number" step="0.001" name="items[0][quantity]" class="qty rounded-md border-gray-300 w-20" value="1"></td>
            <td class="px-2 py-1"><input type="number" step="0.01" name="items[0][unit_cost]" class="price rounded-md border-gray-300 w-24" value="0"></td>
            <td class="px-2 py-1 taxable">0.00</td>
            <td class="px-2 py-1"><input type="number" step="0.01" name="items[0][tax_rate]" class="tax-rate rounded-md border-gray-300 w-20" value="0"></td>
            <td class="px-2 py-1 tax-amt">0.00</td>
            <td class="px-2 py-1 line-total">0.00</td>
            <td class="px-2 py-1"><button class="rounded bg-gray-100 px-2" type="button" onclick="removeRow(this)">×</button></td>
          </tr>
      </tbody>
    </table>
  </div>
  <div class="mb-3"><button class="rounded-md bg-gray-100 px-3 py-2 text-sm" type="button" onclick="addRow()">+ Add Item</button></div>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <div class="md:col-span-2"></div>
    <div class="md:col-span-2">
        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
            <div class="text-gray-600">Subtotal:</div><div id="subtotal" class="text-right">0.00</div>
            <div class="text-gray-600">Discount:</div><div class="text-right"><input class="w-full text-right rounded-md border-gray-300" type="number" step="0.01" name="discount" value="0"></div>
            <div class="text-gray-600">CGST:</div><div id="total_cgst" class="text-right">0.00</div>
            <div class="text-gray-600">SGST:</div><div id="total_sgst" class="text-right">0.00</div>
            <div class="text-gray-600">IGST:</div><div id="total_igst" class="text-right">0.00</div>
            <div class="text-gray-600 font-bold text-base mt-1">Total:</div><div id="total" class="text-right font-bold text-base mt-1">0.00</div>
        </div>
    </div>
  </div>
  <div class="pt-4 flex justify-end"><button class="rounded-md bg-[var(--primary-color)] text-white px-4 py-2 text-sm font-semibold" type="submit">Save Purchase</button></div>
</form>

<h3 class="text-lg font-semibold mt-6 mb-2">Recent Purchases</h3>
<div class="bg-white border border-gray-200 rounded-md shadow-sm overflow-x-auto">
<table class="min-w-full divide-y divide-gray-200">
  <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reference</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th></tr></thead>
  <?php foreach ($recent as $r): ?>
    <tr class="divide-x divide-gray-200">
      <td class="px-3 py-2 text-sm text-gray-700"><a class="text-[var(--primary-color)]" href="<?= page_url('purchase_view',['id'=>$r['id']]) ?>"><?= htmlspecialchars($r['reference_no']) ?></a></td>
      <td class="px-3 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['purchase_date']) ?></td>
      <td class="px-3 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['supplier']) ?></td>
      <td class="px-3 py-2 text-sm text-gray-700"><?= number_format((float)$r['total'],2) ?></td>
      <td class="px-3 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['status']) ?></td>
      <td class="px-3 py-2 text-sm text-gray-700">
        <details class="inline-block">
          <summary class="cursor-pointer text-amber-700">Pay</summary>
          <form method="post" class="inline-block p-2 bg-white border rounded shadow-md">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="purchase_id" value="<?= $r['id'] ?>">
            <input class="rounded-md border-gray-300 text-sm" type="date" name="payment_date" value="<?= date('Y-m-d') ?>">
            <input class="rounded-md border-gray-300 w-24 text-sm" type="number" step="0.01" name="amount" placeholder="Amount">
            <input class="rounded-md border-gray-300 text-sm" name="method" placeholder="Method">
            <button class="rounded-md bg-[var(--primary-color)] text-white px-2 py-1 text-xs" type="submit">Add</button>
          </form>
        </details>
      </td>
    </tr>
  <?php endforeach; if (!$recent): ?>
    <tr><td colspan="6" class="px-3 py-3 text-center text-gray-400">No purchases yet</td></tr>
  <?php endif; ?>
</table>
</div>
</div>

<script>
// This script is identical to the one in sales.php, but uses `cost_price` instead of `sell_price`
const products = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
const locations = <?= json_encode($locations, JSON_UNESCAPED_UNICODE) ?>;
const suppliers = <?= json_encode($suppliers, JSON_UNESCAPED_UNICODE) ?>;
const companyState = "<?= htmlspecialchars(strtolower(trim(setting('company_state', '')))) ?>";

function fillSelect(select, data, valueKey='id', textKey='name') {
  select.innerHTML = '<option value="">Select...</option>';
  data.forEach(d => {
    const o = document.createElement('option');
    o.value = d[valueKey];
    o.textContent = d[textKey];
    select.appendChild(o);
  });
}

function addRow() {
  const tableBody = document.querySelector('#items tbody');
  const idx = tableBody.querySelectorAll('.item-row').length;
  const tr = document.createElement('tr');
  tr.className = 'item-row';
  tr.innerHTML = `
      <td class="px-2 py-1"><select name="items[${idx}][product_id]" class="prod rounded-md border-gray-300 w-full min-w-[150px]"></select></td>
      <td class="px-2 py-1"><input type="text" name="items[${idx}][hsn_code]" class="hsn rounded-md border-gray-300 w-24" readonly></td>
      <td class="px-2 py-1"><select name="items[${idx}][location_id]" class="loc rounded-md border-gray-300 w-full min-w-[100px]"></select></td>
      <td class="px-2 py-1"><input type="number" step="0.001" name="items[${idx}][quantity]" class="qty rounded-md border-gray-300 w-20" value="1"></td>
      <td class="px-2 py-1"><input type="number" step="0.01" name="items[${idx}][unit_cost]" class="price rounded-md border-gray-300 w-24" value="0"></td>
      <td class="px-2 py-1 taxable">0.00</td>
      <td class="px-2 py-1"><input type="number" step="0.01" name="items[${idx}][tax_rate]" class="tax-rate rounded-md border-gray-300 w-20" value="0"></td>
      <td class="px-2 py-1 tax-amt">0.00</td>
      <td class="px-2 py-1 line-total">0.00</td>
      <td class="px-2 py-1"><button class="rounded bg-gray-100 px-2" type="button" onclick="removeRow(this)">×</button></td>`;
  tableBody.appendChild(tr);
  hydrateRow(tr);
  recalc();
}

function removeRow(btn){
  btn.closest('tr').remove();
  recalc();
}

function hydrateRow(tr){
  const prodSel = tr.querySelector('.prod');
  const locSel = tr.querySelector('.loc');
  fillSelect(prodSel, products);
  fillSelect(locSel, locations);
  
  const hsnInput = tr.querySelector('.hsn');
  const priceInput = tr.querySelector('.price');
  const taxRateInput = tr.querySelector('.tax-rate');

  prodSel.addEventListener('change', ()=>{
    const p = products.find(x => String(x.id) === prodSel.value);
    if (p) {
        priceInput.value = p.cost_price; // Use cost_price for purchases
        hsnInput.value = p.hsn_code || '';
        taxRateInput.value = p.tax_rate || 0;
    }
    recalc();
  });

  tr.querySelectorAll('.qty, .price, .tax-rate').forEach(el => el.addEventListener('input', recalc));
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.item-row').forEach(hydrateRow);
    document.querySelector('[name=discount]').addEventListener('input', recalc);
    document.querySelector('[name=place_of_supply]').addEventListener('input', recalc);
    
    const supplierSelect = document.getElementById('supplier_id');
    supplierSelect.addEventListener('change', () => {
        const s = suppliers.find(x => String(x.id) === supplierSelect.value);
        if (s) {
            document.getElementById('gstin').value = s.gstin || '';
            document.getElementById('place_of_supply').value = s.state || '';
        } else {
             document.getElementById('gstin').value = '';
             document.getElementById('place_of_supply').value = '';
        }
        recalc();
    });
    // Trigger change on load to populate initial values
    supplierSelect.dispatchEvent(new Event('change'));
    
    recalc();
});


function recalc(){
  let subtotal = 0; 
  let totalCgst = 0;
  let totalSgst = 0;
  let totalIgst = 0;
  const placeOfSupply = document.getElementById('place_of_supply').value.toLowerCase().trim();

  document.querySelectorAll('#items .item-row').forEach(row=>{
    const q = parseFloat(row.querySelector('.qty').value || 0);
    const pr = parseFloat(row.querySelector('.price').value || 0);
    const taxRate = parseFloat(row.querySelector('.tax-rate').value || 0);
    
    const taxableAmount = q * pr;
    subtotal += taxableAmount;

    let cgst = 0, sgst = 0, igst = 0;
    if (taxRate > 0) {
        // For purchases, it's about your location relative to supplier's
        if (placeOfSupply === companyState) {
            cgst = (taxableAmount * (taxRate / 2)) / 100;
            sgst = (taxableAmount * (taxRate / 2)) / 100;
        } else {
            igst = (taxableAmount * taxRate) / 100;
        }
    }
    
    const taxAmount = cgst + sgst + igst;
    const lineTotal = taxableAmount + taxAmount;

    totalCgst += cgst;
    totalSgst += sgst;
    totalIgst += igst;

    row.querySelector('.taxable').textContent = taxableAmount.toFixed(2);
    row.querySelector('.tax-amt').textContent = taxAmount.toFixed(2);
    row.querySelector('.line-total').textContent = lineTotal.toFixed(2);
  });
  
  const discount = parseFloat(document.querySelector('[name=discount]').value || 0);
  const totalTax = totalCgst + totalSgst + totalIgst;
  const grandTotal = subtotal - discount + totalTax;

  document.getElementById('subtotal').textContent = subtotal.toFixed(2);
  document.getElementById('total_cgst').textContent = totalCgst.toFixed(2);
  document.getElementById('total_sgst').textContent = totalSgst.toFixed(2);
  document.getElementById('total_igst').textContent = totalIgst.toFixed(2);
  document.getElementById('total').textContent = grandTotal.toFixed(2);
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>