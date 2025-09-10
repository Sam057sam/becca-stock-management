<?php
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();

// Add expense
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $stmt = $pdo->prepare('INSERT INTO expenses(category_id,expense_date,description,amount,paid_to,reference) VALUES (?,?,?,?,?,?)');
    $stmt->execute([(int)$_POST['category_id'], $_POST['expense_date'] ?: date('Y-m-d'), $_POST['description'] ?? null, (float)$_POST['amount'], $_POST['paid_to'] ?? null, $_POST['reference'] ?? null]);
    redirect_to_page('expenses');
    exit;
}

$cats = $pdo->query('SELECT id,name FROM expense_categories ORDER BY name')->fetchAll();
$rows = $pdo->query('SELECT e.id, e.expense_date, c.name as category, e.description, e.amount FROM expenses e JOIN expense_categories c ON c.id=e.category_id ORDER BY e.expense_date DESC, e.id DESC LIMIT 25')->fetchAll();
?>

<div class="p-6">
  <h2 class="text-2xl font-semibold mb-3">Expenses</h2>
  <form method="post" class="bg-white border border-gray-200 rounded-md p-4 shadow-sm grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
    <?= csrf_field() ?>
    <label class="text-sm text-gray-700">Category
      <select class="mt-1 rounded-md border-gray-300 w-full" name="category_id">
        <?php foreach($cats as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm text-gray-700">Date<input class="mt-1 rounded-md border-gray-300 w-full" type="date" name="expense_date" value="<?= date('Y-m-d') ?>"></label>
    <label class="text-sm text-gray-700">Amount<input class="mt-1 rounded-md border-gray-300 w-full" type="number" step="0.01" name="amount" required></label>
    <label class="text-sm text-gray-700 md:col-span-3">Description<input class="mt-1 rounded-md border-gray-300 w-full" name="description"></label>
    <label class="text-sm text-gray-700">Paid To<input class="mt-1 rounded-md border-gray-300 w-full" name="paid_to"></label>
    <label class="text-sm text-gray-700">Reference<input class="mt-1 rounded-md border-gray-300 w-full" name="reference"></label>
    <div class="md:col-span-3 flex gap-3">
      <button type="submit" class="rounded-md bg-[var(--primary-color)] text-white px-4 py-2 text-sm">Add Expense</button>
      <a class="rounded-md bg-gray-100 px-3 py-2 text-sm" href="<?= page_url('expenses') ?>">Clear</a>
      <p class="text-xs text-gray-500 self-center">Tip: set your categories under Master Data.</p>
    </div>
  </form>

  <div class="bg-white border border-gray-200 rounded-md shadow-sm overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th></tr></thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['expense_date']) ?></td>
            <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['category']) ?></td>
            <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($r['description']) ?></td>
            <td class="px-4 py-2 text-sm text-gray-700"><?= number_format((float)$r['amount'],2) ?></td>
          </tr>
        <?php endforeach; if (!$rows): ?>
          <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No expenses yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
