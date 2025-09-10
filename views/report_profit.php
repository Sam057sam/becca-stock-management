<?php
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');

// Revenue from sale_items
$revStmt = $pdo->prepare("SELECT IFNULL(SUM(si.line_total),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.status<>'cancelled' AND s.sale_date BETWEEN ? AND ?");
$revStmt->execute([$from,$to]);
$revenue = (float)$revStmt->fetchColumn();

// Compute COGS by weighted average purchase cost up to 'to' date per product, times sold qty in period
// Prefer stored cost_at_sale if column exists; otherwise compute average cost
$cogs = 0.0;
if (column_exists('sale_items','cost_at_sale')) {
    $stmt = $pdo->prepare("SELECT si.product_id, si.quantity, si.cost_at_sale, s.sale_date FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.status<>'cancelled' AND s.sale_date BETWEEN ? AND ?");
    $stmt->execute([$from,$to]);
    $items = $stmt->fetchAll();
    foreach ($items as $it) {
        $cost = $it['cost_at_sale'] !== null ? (float)$it['cost_at_sale'] : average_cost((int)$it['product_id'], $it['sale_date']);
        $cogs += ((float)$it['quantity']) * $cost;
    }
} else {
    $soldStmt = $pdo->prepare("SELECT si.product_id, SUM(si.quantity) qty FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.status<>'cancelled' AND s.sale_date BETWEEN ? AND ? GROUP BY si.product_id");
    $soldStmt->execute([$from,$to]);
    $sold = $soldStmt->fetchAll();
    foreach ($sold as $row) {
        $pid = (int)$row['product_id'];
        $qtySold = (float)$row['qty'];
        $avg = average_cost($pid, $to);
        $cogs += $qtySold * $avg;
    }
}

// Expenses
$expStmt = $pdo->prepare("SELECT IFNULL(SUM(e.amount),0) FROM expenses e WHERE e.expense_date BETWEEN ? AND ?");
$expStmt->execute([$from,$to]);
$expenses = (float)$expStmt->fetchColumn();

$gross = $revenue - $cogs;
$net = $gross - $expenses;

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="profit-loss-'. $from .'-'. $to .'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Metric','Amount']);
    fputcsv($out, ['Revenue', number_format($revenue,2)]);
    fputcsv($out, ['COGS (Avg Cost)', number_format($cogs,2)]);
    fputcsv($out, ['Gross Profit', number_format($gross,2)]);
    fputcsv($out, ['Expenses', number_format($expenses,2)]);
    fputcsv($out, ['Net Profit', number_format($net,2)]);
    fclose($out);
    exit;
}
if (($_GET['export'] ?? '') === 'pdf') {
    ob_start();
    ?>
    <h3>Profit &amp; Loss (<?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?>)</h3>
    <table border="1" cellspacing="0" cellpadding="4" style="width:100%;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;font-size:12px">
      <tr><th>Metric</th><th>Amount</th></tr>
      <tr><td>Revenue</td><td><?= number_format($revenue,2) ?></td></tr>
      <tr><td>COGS (Avg Cost)</td><td><?= number_format($cogs,2) ?></td></tr>
      <tr><td>Gross Profit</td><td><?= number_format($gross,2) ?></td></tr>
      <tr><td>Expenses</td><td><?= number_format($expenses,2) ?></td></tr>
      <tr><td><strong>Net Profit</strong></td><td><strong><?= number_format($net,2) ?></strong></td></tr>
    </table>
    <?php
    $html = ob_get_clean();
    render_pdf($html, 'profit-loss-'.$from.'-'.$to.'.pdf', false);
}
?>

<div class="p-6">
<h2 class="text-2xl font-semibold mb-3">Profit & Loss</h2>
<form method="get" class="bg-white border border-gray-200 rounded-md p-4 shadow-sm grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
  <input type="hidden" name="page" value="report_profit">
  <label class="text-sm text-gray-700">From<input class="mt-1 rounded-md border-gray-300 w-full" type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
  <label class="text-sm text-gray-700">To<input class="mt-1 rounded-md border-gray-300 w-full" type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
  <div></div><div></div>
  <div class="flex items-end gap-2">
    <button type="submit" class="rounded-md bg-[var(--primary-color)] text-white px-3 py-2 text-sm">Filter</button>
    <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('report_profit',['from'=>$from,'to'=>$to,'export'=>'csv']) ?>">Export CSV</a>
    <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('report_profit',['from'=>$from,'to'=>$to,'export'=>'pdf']) ?>">Export PDF</a>
  </div>
  <p class="text-xs text-gray-500 md:col-span-5">COGS uses weighted average product cost from all purchases up to the end date.</p>
  </form>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
  <div class="bg-white border border-gray-200 rounded-md p-4 shadow-sm"><div class="text-sm text-gray-500">Revenue</div><div class="text-2xl font-bold text-gray-900"><?= number_format($revenue,2) ?></div></div>
  <div class="bg-white border border-gray-200 rounded-md p-4 shadow-sm"><div class="text-sm text-gray-500">COGS (Avg Cost)</div><div class="text-2xl font-bold text-gray-900"><?= number_format($cogs,2) ?></div></div>
  <div class="bg-white border border-gray-200 rounded-md p-4 shadow-sm"><div class="text-sm text-gray-500">Gross Profit</div><div class="text-2xl font-bold text-gray-900"><?= number_format($gross,2) ?></div></div>
  <div class="bg-white border border-gray-200 rounded-md p-4 shadow-sm"><div class="text-sm text-gray-500">Expenses</div><div class="text-2xl font-bold text-gray-900"><?= number_format($expenses,2) ?></div></div>
  <div class="bg-white border border-gray-200 rounded-md p-4 shadow-sm"><div class="text-sm text-gray-500">Net Profit</div><div class="text-2xl font-bold text-gray-900"><?= number_format($net,2) ?></div></div>
</div>

</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
