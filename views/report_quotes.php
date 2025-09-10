<?php
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();

// Fetch quote data, grouped by month and status
$sql = "SELECT DATE_FORMAT(quote_date, '%Y-%m') AS ym, status, SUM(total) AS total
        FROM quotes
        WHERE quote_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY ym, status
        ORDER BY ym ASC, status ASC";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();

// Process data for display
$monthlyData = [];
foreach ($rows as $row) {
    $ym = $row['ym'];
    $status = $row['status'];
    $total = (float)$row['total'];
    if (!isset($monthlyData[$ym])) {
        $monthlyData[$ym] = ['total' => 0.0];
    }
    $monthlyData[$ym][$status] = $total;
    $monthlyData[$ym]['total'] += $total;
}

$months = array_keys($monthlyData);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="quotes-report.csv"');
    $out = fopen('php://output', 'w');
    $header = ['Month', 'Total Quotes'];
    $statuses = $pdo->query("SELECT DISTINCT status FROM quotes")->fetchAll(PDO::FETCH_COLUMN);
    $header = array_merge($header, $statuses);
    fputcsv($out, $header);
    foreach ($monthlyData as $ym => $data) {
        $row = [$ym, number_format($data['total'], 2)];
        foreach ($statuses as $status) {
            $row[] = number_format($data[$status] ?? 0, 2);
        }
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
?>

<div class="p-6">
  <h2 class="text-2xl font-semibold mb-3">Quotes Report</h2>
  <div class="bg-white border border-gray-200 rounded-md p-4 shadow-sm mb-4">
    <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('report_quotes',['export'=>'csv']) ?>">Export CSV</a>
  </div>

  <div class="bg-white border border-gray-200 rounded-md shadow-sm overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Quotes</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Draft</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Accepted</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejected</th>
          <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cancelled</th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <?php foreach ($monthlyData as $ym => $data): ?>
          <tr>
            <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars(date('M Y', strtotime($ym.'-01'))) ?></td>
            <td class="px-4 py-2 text-sm text-gray-700"><?= money($data['total']) ?></td>
            <td class="px-4 py-2 text-sm text-gray-700"><?= money($data['draft'] ?? 0) ?></td>
            <td class="px-4 py-2 text-sm text-gray-700"><?= money($data['sent'] ?? 0) ?></td>
            <td class="px-4 py-2 text-sm text-gray-700"><?= money($data['accepted'] ?? 0) ?></td>
            <td class="px-4 py-2 text-sm text-gray-700"><?= money($data['rejected'] ?? 0) ?></td>
            <td class="px-4 py-2 text-sm text-gray-700"><?= money($data['cancelled'] ?? 0) ?></td>
          </tr>
        <?php endforeach; if (!$monthlyData): ?>
          <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No quotes data available for the last 6 months.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>