<?php
$u = current_user();
$role=$u['role'] ?? '';
$page = $_GET['page'] ?? 'dashboard';
function item($href,$icon,$label,$active){
  $is = $active? 'bg-[var(--secondary-color)] text-[var(--text-primary)]' : 'text-[var(--text-secondary)] hover:bg-gray-100';
  return '<a class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium '.$is.'" href="'.page_url($href).'"><span class="material-symbols-outlined">'.$icon.'</span>'.$label.'</a>';
}
?>

<?= item('dashboard','dashboard','Dashboard', $page==='dashboard') ?>

<div>
  <h3 class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mt-4">Master Data</h3>
  <div class="mt-2 space-y-1">
    <?php if ($role!=='Staff'): ?>
      <?= item('categories','category','Categories', $page==='categories') ?>
      <?= item('units','straighten','Units', $page==='units') ?>
      <?= item('locations','place','Locations', $page==='locations') ?>
      <?= item('products','inventory_2','Products', $page==='products') ?>
      <?= item('suppliers','local_shipping','Suppliers', $page==='suppliers') ?>
      <?= item('customers','groups','Customers', $page==='customers') ?>
    <?php else: ?>
      <span class="px-3 text-xs text-gray-400">(Restricted)</span>
    <?php endif; ?>
  </div>
</div>

<div class="pt-4">
  <h3 class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Operations</h3>
  <div class="mt-2 space-y-1">
    <?= item('quotes','request_quote','Quotes/Estimates', $page==='quotes') ?>
    <?= item('sales','shopping_cart','Sales', $page==='sales') ?>
    <?php if ($role!=='Staff'): ?>
      <?= item('purchases','receipt_long','Purchases', $page==='purchases') ?>
      <?= item('expenses','paid','Expenses', $page==='expenses') ?>
    <?php endif; ?>
  </div>
</div>

<div class="pt-4">
  <h3 class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Reports</h3>
  <div class="mt-2 space-y-1">
    <?php if ($role!=='Staff'): ?>
      <?= item('report_sales','show_chart','Daily Sales', $page==='report_sales') ?>
      <?= item('report_purchases','bar_chart','Purchases', $page==='report_purchases') ?>
      <?= item('report_profit','assessment','Profit &amp; Loss', $page==='report_profit') ?>
      <?= item('report_low_stock','production_quantity_limits','Low Stock', $page==='report_low_stock') ?>
      <?= item('report_payments','notifications_active','Payment Reminders', $page==='report_payments') ?>
      <?= item('report_quotes','description','Quotes', $page==='report_quotes') ?>
    <?php else: ?>
      <span class="px-3 text-xs text-gray-400">(Restricted)</span>
    <?php endif; ?>
  </div>
</div>

<?php if ($u && $u['role'] === 'Admin'): ?>
  <div class="pt-4">
    <h3 class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Admin</h3>
    <div class="mt-2 space-y-1">
      <?= item('settings','settings','Settings', $page==='settings') ?>
      <?= item('users','manage_accounts','Users', $page==='users') ?>
      <?= item('demo','storage','Demo Data', $page==='demo') ?>
    </div>
  </div>
<?php endif; ?>
