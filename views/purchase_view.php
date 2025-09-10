<?php
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);
$pur = $pdo->prepare('SELECT p.*, s.name AS supplier, s.address_line, s.address_line_2, s.landmark, s.city, s.state, s.zipcode, s.country
  FROM purchases p JOIN suppliers s ON s.id=p.supplier_id WHERE p.id=?');
$pur->execute([$id]);
$pur = $pur->fetch();
if (!$pur) { echo '<p>Purchase not found.</p>'; require __DIR__.'/partials/footer.php'; exit; }
$items = $pdo->prepare('SELECT pi.*, pr.name, l.name AS location FROM purchase_items pi JOIN products pr ON pr.id=pi.product_id JOIN locations l ON l.id=pi.location_id WHERE purchase_id=?');
$items->execute([$id]);
$items = $items->fetchAll();
// Correctly call format_address with an array
$supplier_address = format_address($pur);

// PDF
if (($_GET['export'] ?? '') === 'pdf') {
  ob_start();
  ?>
  <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#111">
    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:10px">
      <div>
        <h2 style="margin:0">Purchase</h2>
        <div>Reference: <?= htmlspecialchars($pur['reference_no']) ?></div>
        <div>Date: <?= htmlspecialchars($pur['purchase_date']) ?></div>
        <?php if ($pur['due_date']): ?><div>Due: <?= htmlspecialchars($pur['due_date']) ?></div><?php endif; ?>
        <div>Status: <?= htmlspecialchars($pur['status']) ?></div>
        <?php if (table_exists('settings')): ?>
          <div><strong><?= htmlspecialchars(setting('company_name','')) ?></strong></div>
          <div style="white-space:pre-wrap;max-width:260px;"><?= htmlspecialchars(format_address(settings_all(), 'company_')) ?></div>
        <?php endif; ?>
      </div>
      <div style="text-align:right">
        <div><strong>Supplier</strong></div>
        <div><?= htmlspecialchars($pur['supplier']) ?></div>
        <div style="max-width:240px;white-space:pre-wrap"><?= htmlspecialchars($supplier_address) ?></div>
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse" border="1" cellpadding="4">
      <tr><th>#</th><th>Product</th><th>Location</th><th>Qty</th><th>Unit Cost</th><th>Total</th></tr>
      <?php $i=1; foreach ($items as $it): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($it['name']) ?></td>
          <td><?= htmlspecialchars($it['location']) ?></td>
          <td><?= (float)$it['quantity'] ?></td>
          <td><?= money((float)$it['unit_cost']) ?></td>
          <td><?= money((float)$it['line_total']) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr><td colspan="5" align="right">Subtotal</td><td><?= money((float)$pur['subtotal']) ?></td></tr>
      <tr><td colspan="5" align="right">Discount</td><td><?= money((float)$pur['discount']) ?></td></tr>
      <tr><td colspan="5" align="right">Tax</td><td><?= money((float)$pur['tax']) ?></td></tr>
      <tr><td colspan="5" align="right"><strong>Total</strong></td><td><strong><?= money((float)$pur['total']) ?></strong></td></tr>
    </table>
  </div>
  <?php
  $html = ob_get_clean();
  render_pdf($html, 'purchase-'.$pur['reference_no'].'.pdf');
}
?>

<div class="p-6">
    <div class="max-w-4xl mx-auto bg-white p-8 border border-gray-200 rounded-lg shadow-sm">
      <div class="flex justify-between items-start mb-6">
        <div>
          <h2 class="text-2xl font-bold">Purchase Order</h2>
          <div>Reference: <span class="text-gray-800"><?= htmlspecialchars($pur['reference_no']) ?></span></div>
          <div>Date: <span class="text-gray-800"><?= htmlspecialchars($pur['purchase_date']) ?></span></div>
          <?php if ($pur['due_date']): ?><div>Due: <span class="text-gray-800"><?= htmlspecialchars($pur['due_date']) ?></span></div><?php endif; ?>
          <div>Status: <span class="font-semibold text-gray-800"><?= htmlspecialchars($pur['status']) ?></span></div>
        </div>
        <div class="text-right">
          <div class="font-semibold text-gray-700">Supplier</div>
          <div class="text-gray-800"><?= htmlspecialchars($pur['supplier']) ?></div>
          <div class="text-xs text-gray-500 whitespace-pre-wrap"><?= htmlspecialchars($supplier_address) ?></div>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">#</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">Location</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Qty</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Unit Cost</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Total</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php $i=1; foreach ($items as $it): ?>
                <tr>
                  <td class="px-3 py-2"><?= $i++ ?></td>
                  <td class="px-3 py-2"><?= htmlspecialchars($it['name']) ?></td>
                  <td class="px-3 py-2"><?= htmlspecialchars($it['location']) ?></td>
                  <td class="px-3 py-2 text-right"><?= (float)$it['quantity'] ?></td>
                  <td class="px-3 py-2 text-right"><?= money((float)$it['unit_cost']) ?></td>
                  <td class="px-3 py-2 text-right"><?= money((float)$it['line_total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50">
                <tr><td colspan="5" class="px-3 py-2 text-right font-semibold">Subtotal</td><td class="px-3 py-2 text-right"><?= money((float)$pur['subtotal']) ?></td></tr>
                <tr><td colspan="5" class="px-3 py-2 text-right font-semibold">Discount</td><td class="px-3 py-2 text-right"><?= money((float)$pur['discount']) ?></td></tr>
                <tr><td colspan="5" class="px-3 py-2 text-right font-semibold">Tax (CGST+SGST+IGST)</td><td class="px-3 py-2 text-right"><?= money((float)$pur['tax']) ?></td></tr>
                <tr><td colspan="5" class="px-3 py-2 text-right font-bold text-base">Total</td><td class="px-3 py-2 text-right font-bold text-base"><?= money((float)$pur['total']) ?></td></tr>
            </tfoot>
        </table>
      </div>
      <div class="mt-8 flex justify-between">
        <a class="btn-secondary rounded-md px-4 py-2 text-sm" href="<?= page_url('purchases') ?>">Back to Purchases</a>
        <div>
          <a class="btn-secondary rounded-md px-4 py-2 text-sm" href="<?= page_url('purchase_view',['id'=>$pur['id'],'export'=>'pdf']) ?>">Export PDF</a>
          <button class="rounded-md bg-gray-700 text-white px-4 py-2 text-sm font-semibold" onclick="window.print()">Print</button>
        </div>
      </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>