<?php
require_once __DIR__ . '/partials/header.php';

$pdo = get_db();
// KPIs
$totalStockValue = (float)$pdo->query("SELECT IFNULL(SUM(s.quantity * p.cost_price),0) FROM stock s JOIN products p ON p.id=s.product_id")->fetchColumn();
$recentExpenses = (float)$pdo->query("SELECT IFNULL(SUM(amount),0) FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$popularItem = $pdo->query("SELECT p.name FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY si.product_id ORDER BY SUM(si.quantity) DESC LIMIT 1")->fetchColumn() ?: '—';
$salesRows = $pdo->query("SELECT DATE_FORMAT(sale_date,'%Y-%m') ym, SUM(total) amt FROM sales WHERE sale_date >= DATE_SUB(LAST_DAY(CURDATE()), INTERVAL 5 MONTH) AND status<>'cancelled' GROUP BY ym ORDER BY ym")->fetchAll();
$expRows = $pdo->query("SELECT DATE_FORMAT(expense_date,'%Y-%m') ym, SUM(amount) amt FROM expenses WHERE expense_date >= DATE_SUB(LAST_DAY(CURDATE()), INTERVAL 5 MONTH) GROUP BY ym ORDER BY ym")->fetchAll();
$months = [];
for ($i=5; $i>=0; $i--) { $months[] = date('Y-m', strtotime("first day of -$i month")); }
$salesMap = []; foreach ($salesRows as $r) { $salesMap[$r['ym']] = (float)$r['amt']; }
$expMap = []; foreach ($expRows as $r) { $expMap[$r['ym']] = (float)$r['amt']; }
$salesSeries = []; $expSeries = [];
foreach ($months as $ym) { $salesSeries[] = $salesMap[$ym] ?? 0.0; $expSeries[] = $expMap[$ym] ?? 0.0; }
$salesMax = max(1, max($salesSeries));
$expMax = max(1, max($expSeries));
?>

<header class="bg-white shadow-sm">
  <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-bold leading-tight tracking-tight text-gray-900">Dashboard</h1>
  </div>
</header>

<div class="p-8">
  <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
    <div class="bg-white overflow-hidden shadow rounded-lg"><div class="p-5"><div class="flex items-center"><div class="flex-shrink-0"><span class="material-symbols-outlined text-3xl text-gray-500">attach_money</span></div><div class="ml-5 w-0 flex-1"><dl><dt class="text-sm font-medium text-gray-500 truncate">Total Stock Value</dt><dd class="text-2xl font-bold text-gray-900"><?= money($totalStockValue) ?></dd></dl></div></div></div></div>
    <div class="bg-white overflow-hidden shadow rounded-lg"><div class="p-5"><div class="flex items-center"><div class="flex-shrink-0"><span class="material-symbols-outlined text-3xl text-gray-500">trending_down</span></div><div class="ml-5 w-0 flex-1"><dl><dt class="text-sm font-medium text-gray-500 truncate">Recent Expenses</dt><dd class="text-2xl font-bold text-gray-900"><?= money($recentExpenses) ?></dd></dl></div></div></div></div>
    <div class="bg-white overflow-hidden shadow rounded-lg"><div class="p-5"><div class="flex items-center"><div class="flex-shrink-0"><span class="material-symbols-outlined text-3xl text-gray-500">star</span></div><div class="ml-5 w-0 flex-1"><dl><dt class="text-sm font-medium text-gray-500 truncate">Popular Items</dt><dd class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($popularItem) ?></dd></dl></div></div></div></div>
  </div>

  <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="bg-white overflow-hidden shadow rounded-lg">
      <div class="p-6">
        <h3 class="text-lg font-medium leading-6 text-gray-900">Sales Performance</h3>
        <div class="mt-6">
          <div class="grid min-h-[180px] grid-flow-col gap-6 grid-rows-[1fr_auto] items-end justify-items-center px-3">
            <?php foreach ($months as $idx=>$ym): $h = (int)round(($salesSeries[$idx]/$salesMax)*100); $label = date('M', strtotime($ym.'-01')); ?>
              <div class="bg-[var(--primary-color)] w-full rounded-t-md" style="height: <?= max(6,$h) ?>%;"></div>
              <p class="text-gray-500 text-sm font-medium"><?= $label ?></p>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="bg-white overflow-hidden shadow rounded-lg">
      <div class="p-6">
        <h3 class="text-lg font-medium leading-6 text-gray-900">Expense Trends</h3>
        <div class="mt-6">
          <div class="grid min-h-[180px] grid-flow-col gap-6 grid-rows-[1fr_auto] items-end justify-items-center px-3">
            <?php foreach ($months as $idx=>$ym): $h = (int)round(($expSeries[$idx]/$expMax)*100); $label = date('M', strtotime($ym.'-01')); ?>
              <div class="bg-[var(--primary-color)] w-full rounded-t-md" style="height: <?= max(6,$h) ?>%;"></div>
              <p class="text-gray-500 text-sm font-medium"><?= $label ?></p>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
