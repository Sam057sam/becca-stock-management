<?php
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();

$loc = $_GET['location_id'] ?? '';
$locs = $pdo->query('SELECT id,name FROM locations ORDER BY name')->fetchAll();

$sql = "SELECT p.name AS product, l.name AS location, s.quantity, p.reorder_level
        FROM stock s JOIN products p ON p.id=s.product_id JOIN locations l ON l.id=s.location_id
        WHERE p.is_active=1 AND s.quantity <= p.reorder_level";
$args = [];
if ($loc !== '') { $sql .= " AND s.location_id=?"; $args[] = (int)$loc; }
$sql .= " ORDER BY p.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="low-stock.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Product','Location','Quantity','Reorder Level']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['product'],$r['location'],$r['quantity'],$r['reorder_level']]);
    }
    fclose($out);
    exit;
}
if (($_GET['export'] ?? '') === 'pdf') {
    ob_start();
    ?>
    <h3>Low Stock<?= $loc!==''? ' (Location ID '.$loc.')':'' ?></h3>
    <table border="1" cellspacing="0" cellpadding="4" style="width:100%;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;font-size:12px">
      <tr><th>Product</th><th>Location</th><th>Quantity</th><th>Reorder Level</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['product']) ?></td>
        <td><?= htmlspecialchars($r['location']) ?></td>
        <td><?= (float)$r['quantity'] ?></td>
        <td><?= (float)$r['reorder_level'] ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php
    $html = ob_get_clean();
    render_pdf($html, 'low-stock'.($loc!==''?'-loc'.$loc:'').'.pdf', false);
}
?>

<div class="p-6">
<h2 class="text-2xl font-semibold mb-3">Low Stock</h2>
<form method="get" class="bg-white border border-gray-200 rounded-md p-4 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
  <input type="hidden" name="page" value="report_low_stock">
  <label class="text-sm text-gray-700">Location
    <select class="mt-1 rounded-md border-gray-300 w-full" name="location_id">
      <option value="">All</option>
      <?php foreach($locs as $l): ?><option value="<?= $l['id'] ?>" <?= ($loc!=='' && (int)$loc===$l['id'])?'selected':'' ?>><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <div></div><div></div>
  <div class="flex items-end gap-2">
    <button type="submit" class="rounded-md bg-[var(--primary-color)] text-white px-3 py-2 text-sm">Filter</button>
    <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('report_low_stock', array_filter(['location_id'=>$loc!==''?(int)$loc:null,'export'=>'csv'])) ?>">Export CSV</a>
    <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('report_low_stock', array_filter(['location_id'=>$loc!==''?(int)$loc:null,'export'=>'pdf'])) ?>">Export PDF</a>
  </div>
  </form>

<div class="bg-white border border-gray-200 rounded-md shadow-sm overflow-x-auto">
<table class="min-w-full divide-y divide-gray-200">
  <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reorder Level</th></tr></thead>
  <tbody class="bg-white divide-y divide-gray-200">
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['product']) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['location']) ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= (float)$r['quantity'] ?></td>
      <td class="px-4 py-2 text-sm text-gray-700"><?= (float)$r['reorder_level'] ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?>
    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No low stock items</td></tr>
  <?php endif; ?>
</table>
</div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
