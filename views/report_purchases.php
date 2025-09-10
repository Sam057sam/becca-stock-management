<?php
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();

$from = $_GET['from'] ?? date('Y-m-d');
$to = $_GET['to'] ?? $from;

$stmt = $pdo->prepare('SELECT p.id, p.reference_no, p.purchase_date, p.total, p.status, s.name AS supplier,
  (SELECT IFNULL(SUM(pp.amount),0) FROM purchase_payments pp WHERE pp.purchase_id=p.id) AS paid
  FROM purchases p JOIN suppliers s ON s.id=p.supplier_id
  WHERE p.purchase_date BETWEEN ? AND ? AND p.status<>"cancelled"
  ORDER BY p.purchase_date ASC, p.id ASC');
$stmt->execute([$from,$to]);
$rows = $stmt->fetchAll();

$sum_total = 0; $sum_paid = 0; $sum_balance = 0;
foreach ($rows as $r) { $sum_total += (float)$r['total']; $sum_paid += (float)$r['paid']; $sum_balance += (float)$r['total'] - (float)$r['paid']; }

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="purchases-'. $from .'-'. $to .'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Reference','Supplier','Total','Paid','Balance','Status']);
    foreach ($rows as $r) {
        $bal = (float)$r['total'] - (float)$r['paid'];
        fputcsv($out, [$r['purchase_date'],$r['reference_no'],$r['supplier'],number_format((float)$r['total'],2),number_format((float)$r['paid'],2),number_format($bal,2),$r['status']]);
    }
    fputcsv($out, ['Totals','','',number_format($sum_total,2),number_format($sum_paid,2),number_format($sum_balance,2),'']);
    fclose($out);
    exit;
}
if (($_GET['export'] ?? '') === 'pdf') {
    ob_start();
    ?>
    <h3>Purchase Report (<?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?>)</h3>
    <table border="1" cellspacing="0" cellpadding="4" style="width:100%;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;font-size:12px">
      <tr><th>Date</th><th>Reference</th><th>Supplier</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr>
      <?php foreach ($rows as $r): $bal=(float)$r['total']-(float)$r['paid']; ?>
      <tr>
        <td><?= htmlspecialchars($r['purchase_date']) ?></td>
        <td><?= htmlspecialchars($r['reference_no']) ?></td>
        <td><?= htmlspecialchars($r['supplier']) ?></td>
        <td><?= number_format((float)$r['total'],2) ?></td>
        <td><?= number_format((float)$r['paid'],2) ?></td>
        <td><?= number_format($bal,2) ?></td>
        <td><?= htmlspecialchars($r['status']) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr>
        <th colspan="3" align="right">Totals</th>
        <th><?= number_format($sum_total,2) ?></th>
        <th><?= number_format($sum_paid,2) ?></th>
        <th><?= number_format($sum_balance,2) ?></th>
        <th></th>
      </tr>
    </table>
    <?php
    $html = ob_get_clean();
    render_pdf($html, 'purchases-'.$from.'-'.$to.'.pdf', false);
}
?>

<div class="p-6">
<h2 class="text-2xl font-semibold mb-3">Purchase Report</h2>
<form method="get" class="bg-white border border-gray-200 rounded-md p-4 shadow-sm grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
  <input type="hidden" name="page" value="report_purchases">
  <label class="text-sm text-gray-700">From<input class="mt-1 rounded-md border-gray-300 w-full" type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
  <label class="text-sm text-gray-700">To<input class="mt-1 rounded-md border-gray-300 w-full" type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
  <div></div><div></div>
  <div class="flex items-end gap-2">
    <button type="submit" class="rounded-md bg-[var(--primary-color)] text-white px-3 py-2 text-sm">Filter</button>
    <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('report_purchases',['from'=>$from,'to'=>$to,'export'=>'csv']) ?>">Export CSV</a>
    <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('report_purchases',['from'=>$from,'to'=>$to,'export'=>'pdf']) ?>">Export PDF</a>
  </div>
</form>

<div class="bg-white border border-gray-200 rounded-md shadow-sm overflow-x-auto">
<table class="min-w-full divide-y divide-gray-200">
  <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th></tr></thead>
  <tbody class="bg-white divide-y divide-gray-200">
  <?php foreach ($rows as $r): $bal=(float)$r['total']-(float)$r['paid']; ?>
    <tr>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['purchase_date']) ?></td>
      <td class="px-4 py-2 text-sm text-[var(--primary-color)]"><a href="<?= page_url('purchase',['id'=>$r['id']]) ?>"><?= htmlspecialchars($r['reference_no']) ?></a></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['supplier']) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= number_format((float)$r['total'],2) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= number_format((float)$r['paid'],2) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= number_format($bal,2) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['status']) ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?>
    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No purchases in this period</td></tr>
  <?php endif; ?>
  </tbody>
  <tfoot class="bg-gray-50">
    <tr>
      <th colspan="3" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Totals</th>
      <th class="px-4 py-2 text-sm text-gray-700"><?= number_format($sum_total,2) ?></th>
      <th class="px-4 py-2 text-sm text-gray-700"><?= number_format($sum_paid,2) ?></th>
      <th class="px-4 py-2 text-sm text-gray-700"><?= number_format($sum_balance,2) ?></th>
      <th></th>
    </tr>
  </tfoot>
</table>
</div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
