<?php
// This block handles PDF generation. It's called directly from index.php.
if (($_GET['export'] ?? '') === 'pdf') {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/pdf.php';
    require_once __DIR__ . '/../includes/settings.php';
    require_once __DIR__ . '/../includes/format.php';
    
    $pdo = get_db();
    $id = (int)($_GET['id'] ?? 0);

    // --- CORRECTED DATABASE QUERIES ---
    $sale_stmt = $pdo->prepare('SELECT s.*, c.name AS customer, c.address_line, c.address_line_2, c.landmark, c.city, c.state, c.zipcode, c.country FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.id=?');
    $sale_stmt->execute([$id]);
    $sale = $sale_stmt->fetch();

    $items_stmt = $pdo->prepare('SELECT si.*, p.name, p.hsn_code FROM sale_items si JOIN products p ON p.id=si.product_id WHERE sale_id=?');
    $items_stmt->execute([$id]);
    $items = $items_stmt->fetchAll();
    // --- END OF CORRECTION ---

    if (!$sale) { exit('Invoice not found.'); }

    $customer_address = format_address($sale);

    ob_start();
    // The PDF template is in a separate file for clarity
    require __DIR__ . '/templates/invoice_pdf.php';
    $html = ob_get_clean();
    render_pdf($html, 'invoice-'.$sale['invoice_no'].'.pdf');
    exit;
}

// This block handles the regular webpage view.
require_once __DIR__ . '/partials/header.php';
$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);
$sale_stmt = $pdo->prepare('SELECT s.*, COALESCE(c.name, "Walk-in") AS customer,
  c.address_line, c.address_line_2, c.landmark, c.city, c.state, c.zipcode, c.country
  FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.id=?');
$sale_stmt->execute([$id]);
$sale = $sale_stmt->fetch();
if (!$sale) { echo '<p>Invoice not found.</p>'; require __DIR__.'/partials/footer.php'; exit; }

$items_stmt = $pdo->prepare('SELECT si.*, p.name, p.hsn_code, l.name AS location FROM sale_items si JOIN products p ON p.id=si.product_id JOIN locations l ON l.id=si.location_id WHERE sale_id=?');
$items_stmt->execute([$id]);
$items = $items_stmt->fetchAll();
$customer_address = format_address($sale);
?>

<div class="p-6">
    <div class="max-w-4xl mx-auto bg-white p-8 border border-gray-200 rounded-lg shadow-sm">
        <div class="flex justify-between items-start mb-6">
            <div class="flex items-center gap-4">
                <?php if (is_file(__DIR__.'/../public/assets/logo.png')): ?>
                    <img src="assets/logo.png" alt="Logo" class="h-16 w-auto">
                <?php endif; ?>
                <div>
                    <h2 class="text-2xl font-bold">Tax Invoice</h2>
                    <div class="text-sm text-gray-600"><strong><?= htmlspecialchars(setting('company_name','')) ?></strong></div>
                    <div class="text-xs text-gray-500 whitespace-pre-wrap"><?= htmlspecialchars(format_address(settings_all(), 'company_')) ?></div>
                    <div class="text-xs text-gray-500">GSTIN: <?= htmlspecialchars(setting('company_gstin','')) ?></div>
                </div>
            </div>
            <div class="text-right text-sm">
                <div>Invoice No: <strong class="text-gray-800"><?= htmlspecialchars($sale['invoice_no']) ?></strong></div>
                <div>Date: <span class="text-gray-800"><?= htmlspecialchars($sale['sale_date']) ?></span></div>
                <?php if ($sale['due_date']): ?><div>Due Date: <span class="text-gray-800"><?= htmlspecialchars($sale['due_date']) ?></span></div><?php endif; ?>
            </div>
        </div>
        <div class="flex justify-between items-start mb-8 text-sm">
            <div>
                <div class="font-semibold text-gray-700">Bill To:</div>
                <div class="text-gray-800"><?= htmlspecialchars($sale['customer']) ?></div>
                <div class="text-xs text-gray-500 whitespace-pre-wrap"><?= htmlspecialchars($customer_address) ?></div>
                <div class="text-xs text-gray-500">GSTIN: <?= htmlspecialchars($sale['gstin'] ?? 'N/A') ?></div>
                <div class="text-xs text-gray-500">Place of Supply: <?= htmlspecialchars($sale['place_of_supply'] ?? 'N/A') ?></div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">#</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">Product</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">HSN</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Qty</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Price</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Taxable</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Tax</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php $i=1; foreach ($items as $it): 
                        $taxable = (float)$it['line_total'];
                        $tax_amount = (float)$it['cgst'] + (float)$it['sgst'] + (float)$it['igst'];
                    ?>
                    <tr>
                        <td class="px-3 py-2"><?= $i++ ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($it['name']) ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($it['hsn_code'] ?? '') ?></td>
                        <td class="px-3 py-2 text-right"><?= (float)$it['quantity'] ?></td>
                        <td class="px-3 py-2 text-right"><?= number_format((float)$it['unit_price'],2) ?></td>
                        <td class="px-3 py-2 text-right"><?= number_format($taxable, 2) ?></td>
                        <td class="px-3 py-2 text-right"><?= number_format($tax_amount, 2) ?></td>
                        <td class="px-3 py-2 text-right"><?= number_format($taxable + $tax_amount, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="flex justify-end mt-4">
            <div class="w-full max-w-xs text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <span class="text-gray-600">Subtotal:</span><span class="text-right"><?= money((float)$sale['subtotal']) ?></span>
                    <span class="text-gray-600">Discount:</span><span class="text-right"><?= money((float)$sale['discount']) ?></span>
                    <span class="text-gray-600">CGST:</span><span class="text-right"><?= money((float)$sale['total_cgst']) ?></span>
                    <span class="text-gray-600">SGST:</span><span class="text-right"><?= money((float)$sale['total_sgst']) ?></span>
                    <span class="text-gray-600">IGST:</span><span class="text-right"><?= money((float)$sale['total_igst']) ?></span>
                    <hr class="col-span-2 my-1">
                    <span class="font-bold text-base">Total:</span><span class="text-right font-bold text-base"><?= money((float)$sale['total']) ?></span>
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-between">
            <a class="btn-secondary rounded-md px-4 py-2 text-sm" href="<?= page_url('sales') ?>">Back to Sales</a>
            <div>
                <a class="btn-secondary rounded-md px-4 py-2 text-sm" href="<?= page_url('invoice', ['id'=>$sale['id'],'export'=>'pdf']) ?>">Export PDF</a>
                <button class="rounded-md bg-[var(--primary-color)] text-white px-4 py-2 text-sm font-semibold" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>