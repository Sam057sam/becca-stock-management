<?php
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();

$days = (int)($_GET['days'] ?? 7);

$sql = "SELECT 'Sale' AS type, s.invoice_no AS ref, COALESCE(c.name,'Walk-in') AS party, s.due_date AS due, s.total AS amount, s.status
        FROM sales s LEFT JOIN customers c ON c.id=s.customer_id
        WHERE s.status IN ('unpaid','partial') AND s.due_date IS NOT NULL AND s.due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        UNION ALL
        SELECT 'Purchase', p.reference_no, su.name, p.due_date, p.total, p.status
        FROM purchases p LEFT JOIN suppliers su ON su.id=p.supplier_id
        WHERE p.status IN ('unpaid','partial') AND p.due_date IS NOT NULL AND p.due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY due ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$days,$days]);
$rows = $stmt->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payment-reminders-'. $days .'d.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Type','Ref/Invoice','Party','Due','Amount','Status']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['type'],$r['ref'],$r['party'],$r['due'],number_format((float)$r['amount'],2),$r['status']]);
    }
    fclose($out);
    exit;
}
if (($_GET['export'] ?? '') === 'pdf') {
    ob_start();
    ?>
    <h3>Payment Reminders (Next <?= (int)$days ?> days)</h3>
    <table border="1" cellspacing="0" cellpadding="4" style="width:100%;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;font-size:12px">
      <tr><th>Type</th><th>Ref/Invoice</th><th>Party</th><th>Due</th><th>Amount</th><th>Status</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['type']) ?></td>
        <td><?= htmlspecialchars($r['ref'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['party'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['due']) ?></td>
        <td><?= number_format((float)$r['amount'],2) ?></td>
        <td><?= htmlspecialchars($r['status']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php
    $html = ob_get_clean();
    render_pdf($html, 'payment-reminders-'.$days.'d.pdf', false);
}
?>

<div class="p-6">
<h2 class="text-2xl font-semibold mb-3">Payment Reminders</h2>
<form method="get" class="bg-white border border-gray-200 rounded-md p-4 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
  <input type="hidden" name="page" value="report_payments">
  <label class="text-sm text-gray-700">Within Days<input class="mt-1 rounded-md border-gray-300 w-full" type="number" name="days" value="<?= $days ?>" min="1"></label>
  <div></div><div></div>
  <div class="flex items-end gap-2">
    <button type="submit" class="rounded-md bg-[var(--primary-color)] text-white px-3 py-2 text-sm">Filter</button>
    <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('report_payments',['days'=>$days,'export'=>'csv']) ?>">Export CSV</a>
    <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('report_payments',['days'=>$days,'export'=>'pdf']) ?>">Export PDF</a>
  </div>
</form>

<div class="bg-white border border-gray-200 rounded-md shadow-sm overflow-x-auto">
<table class="min-w-full divide-y divide-gray-200">
  <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ref/Invoice</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Party</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th></tr></thead>
  <tbody class="bg-white divide-y divide-gray-200">
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['type']) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['ref'] ?? '-') ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['party'] ?? '-') ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['due']) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= number_format((float)$r['amount'],2) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['status']) ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?>
    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No upcoming payments</td></tr>
  <?php endif; ?>
</table>
</div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
