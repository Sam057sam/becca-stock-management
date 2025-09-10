<?php
require_once __DIR__ . '/db.php';

function get_stock_qty(int $product_id, int $location_id): float {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT quantity FROM stock WHERE product_id=? AND location_id=?');
    $stmt->execute([$product_id,$location_id]);
    $q = $stmt->fetchColumn();
    return (float)($q !== false ? $q : 0);
}

function ensure_stock_row(int $product_id, int $location_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT IGNORE INTO stock(product_id, location_id, quantity) VALUES (?,?,0)');
    $stmt->execute([$product_id,$location_id]);
}

function adjust_stock(int $product_id, int $location_id, float $delta): bool {
    $pdo = get_db();
    ensure_stock_row($product_id,$location_id);
    // For negative delta, ensure not below zero
    if ($delta < 0) {
        $available = get_stock_qty($product_id,$location_id);
        if ($available + $delta < -0.0001) {
            return false; // insufficient stock
        }
    }
    $stmt = $pdo->prepare('UPDATE stock SET quantity = quantity + ? WHERE product_id=? AND location_id=?');
    $stmt->execute([$delta,$product_id,$location_id]);
    return true;
}

function recalc_sale_status(int $sale_id) {
    $pdo = get_db();
    
    $stmt_paid = $pdo->prepare('SELECT IFNULL(SUM(amount),0) FROM sale_payments WHERE sale_id=?');
    $stmt_paid->execute([$sale_id]);
    $paid = (float)$stmt_paid->fetchColumn();

    $stmt_total = $pdo->prepare('SELECT total FROM sales WHERE id=?');
    $stmt_total->execute([$sale_id]);
    $total = (float)$stmt_total->fetchColumn();

    $status = 'unpaid';
    if ($paid <= 0.0001) $status = 'unpaid';
    elseif ($paid + 0.0001 < $total) $status = 'partial';
    else $status = 'paid';
    $stmt = $pdo->prepare('UPDATE sales SET status=? WHERE id=?');
    $stmt->execute([$status,$sale_id]);
}

function recalc_purchase_status(int $purchase_id) {
    $pdo = get_db();

    $stmt_paid = $pdo->prepare('SELECT IFNULL(SUM(amount),0) FROM purchase_payments WHERE purchase_id=?');
    $stmt_paid->execute([$purchase_id]);
    $paid = (float)$stmt_paid->fetchColumn();

    $stmt_total = $pdo->prepare('SELECT total FROM purchases WHERE id=?');
    $stmt_total->execute([$purchase_id]);
    $total = (float)$stmt_total->fetchColumn();

    $status = 'unpaid';
    if ($paid <= 0.0001) $status = 'unpaid';
    elseif ($paid + 0.0001 < $total) $status = 'partial';
    else $status = 'paid';
    $stmt = $pdo->prepare('UPDATE purchases SET status=? WHERE id=?');
    $stmt->execute([$status,$purchase_id]);
}

function average_cost(int $product_id, string $asOfDate): float {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT SUM(pi.quantity*pi.unit_cost) AS s, SUM(pi.quantity) AS q
                           FROM purchase_items pi JOIN purchases p ON p.id=pi.purchase_id
                           WHERE pi.product_id=? AND p.status<>'cancelled' AND p.purchase_date <= ?");
    $stmt->execute([$product_id, $asOfDate]);
    $row = $stmt->fetch();
    if (!$row || !$row['q']) {
        // fallback to product default cost
        $stmt_cost = $pdo->prepare("SELECT cost_price FROM products WHERE id=?");
        $stmt_cost->execute([$product_id]);
        $c = $stmt_cost->fetchColumn();
        return (float)$c;
    }
    return (float)$row['s'] / (float)$row['q'];
}