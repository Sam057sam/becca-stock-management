<?php
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();

$from = $_GET['from'] ?? date('Y-m-d');
$to = $_GET['to'] ?? $from;

$stmt = $pdo->prepare('SELECT s.id, s.invoice_no, s.sale_date, s.total, s.status, COALESCE(c.name, "Walk-in") AS customer,
  (SELECT IFNULL(SUM(sp.amount),0) FROM sale_payments sp WHERE sp.sale_id=s.id) AS paid
  FROM sales s LEFT JOIN customers c ON c.id=s.customer_id
  WHERE s.sale_date BETWEEN ? AND ? AND s.status<>"cancelled"
  ORDER BY s.sale_date ASC, s.id ASC');
$stmt->execute([$from,$to]);
$rows = $stmt->fetchAll();

$sum_total = 0; $sum_paid = 0; $sum_balance = 0;
foreach ($rows as $r) { $sum_total += (float)$r['total']; $sum_paid += (float)$r['paid']; $sum_balance += (float)$r['total'] - (float)$r['paid']; }

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="daily-sales-'. $from .'-'. $to .'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Invoice','Customer','Total','Paid','Balance','Status']);
    foreach ($rows as $r) {
        $bal = (float)$r['total'] - (float)$r['paid'];
        fputcsv($out, [$r['sale_date'],$r['invoice_no'],$r['customer'],number_format((float)$r['total'],2),number_format((float)$r['paid'],2),number_format($bal,2),$r['status']]);
    }
    fputcsv($out, ['Totals','','',number_format($sum_total,2),number_format($sum_paid,2),number_format($sum_balance,2),'']);
    fclose($out);
    exit;
}
if (($_GET['export'] ?? '') === 'pdf') {
    ob_start();
    ?>
    <h3>Daily Sales Report (<?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?>)</h3>
    <table border="1" cellspacing="0" cellpadding="4" style="width:100%;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;font-size:12px">
      <tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr>
      <?php foreach ($rows as $r): $bal=(float)$r['total']-(float)$r['paid']; ?>
      <tr>
        <td><?= htmlspecialchars($r['sale_date']) ?></td>
        <td><?= htmlspecialchars($r['invoice_no']) ?></td>
        <td><?= htmlspecialchars($r['customer']) ?></td>
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
    render_pdf($html, 'daily-sales-'.$from.'-'.$to.'.pdf', false);
}
?>

<div class="p-6">
<h2 class="text-2xl font-semibold mb-3">Daily Sales Report</h2>
<form method="get" class="bg-white border border-gray-200 rounded-md p-4 shadow-sm grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
  <input type="hidden" name="page" value="report_sales">
  <label class="text-sm text-gray-700">From<input class="mt-1 rounded-md border-gray-300 w-full" type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
  <label class="text-sm text-gray-700">To<input class="mt-1 rounded-md border-gray-300 w-full" type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
  <div></div><div></div>
  <div class="flex items-end gap-2">
    <button type="submit" class="rounded-md bg-[var(--primary-color)] text-white px-3 py-2 text-sm">Filter</button>
    <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('report_sales',['from'=>$from,'to'=>$to,'export'=>'csv']) ?>">Export CSV</a>
    <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('report_sales',['from'=>$from,'to'=>$to,'export'=>'pdf']) ?>">Export PDF</a>
  </div>
</form>

<div class="bg-white border border-gray-200 rounded-md shadow-sm overflow-x-auto">
<table class="min-w-full divide-y divide-gray-200">
  <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th></tr></thead>
  <tbody class="bg-white divide-y divide-gray-200">
  <?php foreach ($rows as $r): $bal=(float)$r['total']-(float)$r['paid']; ?>
    <tr>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['sale_date']) ?></td>
      <td class="px-4 py-2 text-sm text-[var(--primary-color)]"><a href="<?= page_url('invoice',['id'=>$r['id']]) ?>"><?= htmlspecialchars($r['invoice_no']) ?></a></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['customer']) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= number_format((float)$r['total'],2) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= number_format((float)$r['paid'],2) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= number_format($bal,2) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['status']) ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?>
    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No sales in this period</td></tr>
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
