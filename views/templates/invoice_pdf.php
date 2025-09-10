<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <style>
      body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; }
      .header-container { display: table; width: 100%; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 15px; }
      .header-left, .header-right { display: table-cell; vertical-align: top; }
      .header-left { width: 50%; }
      .header-right { text-align: right; }
      h2 { margin: 0; font-size: 18px; }
      .details-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
      .details-table th, .details-table td { border: 1px solid #ccc; padding: 5px; text-align: left; }
      .details-table th { background-color: #f2f2f2; }
      .totals-table { width: 40%; margin-left: 60%; border-collapse: collapse; margin-top: 10px; }
      .totals-table td { padding: 4px; }
      .text-right { text-align: right; }
      .small-text { font-size: 9px; color: #555; }
  </style>
</head>
<body>
  <div class="header-container">
      <div class="header-left">
          <?php if (is_file(__DIR__.'/../../public/assets/logo.png')): ?>
              <img src="<?= __DIR__.'/../../public/assets/logo.png' ?>" style="max-height:60px;width:auto">
          <?php endif; ?>
          <h2 style="margin-top: 10px;">Tax Invoice</h2>
          <div><strong><?= htmlspecialchars(setting('company_name','')) ?></strong></div>
          <div class="small-text" style="white-space:pre-wrap;"><?= htmlspecialchars(format_address(settings_all(), 'company_')) ?></div>
          <div class="small-text">GSTIN: <?= htmlspecialchars(setting('company_gstin','')) ?></div>
      </div>
      <div class="header-right">
          <div>Invoice No: <strong><?= htmlspecialchars($sale['invoice_no']) ?></strong></div>
          <div>Date: <?= htmlspecialchars($sale['sale_date']) ?></div>
          <?php if ($sale['due_date']): ?><div>Due Date: <?= htmlspecialchars($sale['due_date']) ?></div><?php endif; ?>
          <div style="margin-top: 10px;"><strong>Bill To:</strong></div>
          <div><?= htmlspecialchars($sale['customer']) ?></div>
          <div class="small-text" style="white-space:pre-wrap"><?= htmlspecialchars($customer_address) ?></div>
          <div class="small-text">GSTIN: <?= htmlspecialchars($sale['gstin'] ?? 'N/A') ?></div>
          <div class="small-text">Place of Supply: <?= htmlspecialchars($sale['place_of_supply'] ?? 'N/A') ?></div>
      </div>
  </div>
    <table class="details-table">
      <thead>
        <tr><th>#</th><th>Product</th><th>HSN</th><th>Qty</th><th>Price</th><th>Taxable</th><th>CGST</th><th>SGST</th><th>IGST</th><th>Total</th></tr>
      </thead>
      <tbody>
        <?php $i=1; foreach ($items as $it): 
            $taxable = (float)$it['line_total'];
        ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($it['name']) ?></td>
              <td><?= htmlspecialchars($it['hsn_code'] ?? '') ?></td>
              <td><?= (float)$it['quantity'] ?></td>
              <td class="text-right"><?= number_format((float)$it['unit_price'],2) ?></td>
              <td class="text-right"><?= number_format($taxable, 2) ?></td>
              <td class="text-right"><?= (float)$it['cgst'] > 0 ? number_format((float)$it['cgst'],2) . '<br><span class="small-text">@'.((float)$it['tax_rate']/2).'%</span>' : '0.00' ?></td>
              <td class="text-right"><?= (float)$it['sgst'] > 0 ? number_format((float)$it['sgst'],2) . '<br><span class="small-text">@'.((float)$it['tax_rate']/2).'%</span>' : '0.00' ?></td>
              <td class="text-right"><?= (float)$it['igst'] > 0 ? number_format((float)$it['igst'],2) . '<br><span class="small-text">@'.(float)$it['tax_rate'].'%</span>' : '0.00' ?></td>
              <td class="text-right"><?= money($taxable + (float)$it['cgst'] + (float)$it['sgst'] + (float)$it['igst']) ?></td>
            </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <table class="totals-table">
      <tr><td>Subtotal</td><td class="text-right"><?= money((float)$sale['subtotal']) ?></td></tr>
      <tr><td>Discount</td><td class="text-right"><?= money((float)$sale['discount']) ?></td></tr>
      <tr><td>CGST</td><td class="text-right"><?= money((float)$sale['total_cgst']) ?></td></tr>
      <tr><td>SGST</td><td class="text-right"><?= money((float)$sale['total_sgst']) ?></td></tr>
      <tr><td>IGST</td><td class="text-right"><?= money((float)$sale['total_igst']) ?></td></tr>
      <tr><td><strong>Total</strong></td><td class="text-right"><strong><?= money((float)$sale['total']) ?></strong></td></tr>
    </table>
</body>
</html>