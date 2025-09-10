<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/stock.php';

function convert_quote_to_sale(int $quote_id): int|false {
    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        // Fetch quote details
        $stmt_quote = $pdo->prepare('SELECT * FROM quotes WHERE id=?');
        $stmt_quote->execute([$quote_id]);
        $quote = $stmt_quote->fetch();
        if (!$quote) {
            throw new Exception("Quote not found.");
        }

        // Fetch quote items
        $stmt_items = $pdo->prepare('SELECT * FROM quote_items WHERE quote_id=?');
        $stmt_items->execute([$quote_id]);
        $items = $stmt_items->fetchAll();
        if (!$items) {
            throw new Exception("Quote has no items.");
        }

        // Check for sufficient stock before creating the sale
        foreach ($items as $item) {
            $available = get_stock_qty((int)$item['product_id'], (int)$item['location_id']);
            if ($available + 0.0001 < (float)$item['quantity']) {
                throw new Exception("Insufficient stock for product ID " . $item['product_id']);
            }
        }

        // Insert new sale record
        $stmt_sale = $pdo->prepare('INSERT INTO sales(customer_id, invoice_no, sale_date, due_date, status, subtotal, discount, tax, total, notes) VALUES (?,?,?,?,"unpaid",?,?,?,?,?)');
        $invoiceNo = 'INV-' . date('ymd-His');
        $stmt_sale->execute([
            $quote['customer_id'],
            $invoiceNo,
            $quote['quote_date'],
            $quote['valid_until'],
            $quote['subtotal'],
            $quote['discount'],
            $quote['tax'],
            $quote['total'],
            'Converted from Quote ' . $quote['quote_no']
        ]);
        $sale_id = (int)$pdo->lastInsertId();

        // Insert sale items and adjust stock
        $hasCostCol = column_exists('sale_items','cost_at_sale');
        foreach ($items as $item) {
            $line_total = (float)$item['quantity'] * (float)$item['unit_price'];
            if ($hasCostCol) {
                $cost = average_cost((int)$item['product_id'], $quote['quote_date']);
                $stmt = $pdo->prepare('INSERT INTO sale_items(sale_id,product_id,location_id,quantity,unit_price,line_total,cost_at_sale) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$sale_id, (int)$item['product_id'], (int)$item['location_id'], (float)$item['quantity'], (float)$item['unit_price'], $line_total, $cost]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO sale_items(sale_id,product_id,location_id,quantity,unit_price,line_total) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$sale_id, (int)$item['product_id'], (int)$item['location_id'], (float)$item['quantity'], (float)$item['unit_price'], $line_total]);
            }
            if (!adjust_stock((int)$item['product_id'], (int)$item['location_id'], -(float)$item['quantity'])) {
                throw new Exception('Failed to adjust stock for product ID ' . $item['product_id']);
            }
        }

        // Update quote status to 'accepted'
        $stmt_update_quote = $pdo->prepare('UPDATE quotes SET status="accepted" WHERE id=?');
        $stmt_update_quote->execute([$quote_id]);

        $pdo->commit();
        recalc_sale_status($sale_id);
        return $sale_id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log("Quote conversion failed: " . $e->getMessage());
        return false;
    }
}
?>