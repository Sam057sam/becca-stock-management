<?php
require_role(['Admin']);
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/../includes/stock.php';

$pdo = get_db();
$msg = '';

function get_or_create($pdo, string $table, array $where, array $create) {
    $wcols = array_keys($where);
    $conds = implode(' AND ', array_map(fn($c)=>"$c=?", $wcols));
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE $conds LIMIT 1");
    $stmt->execute(array_values($where));
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;
    $cols = implode(',', array_keys($create));
    $ph = rtrim(str_repeat('?,', count($create)), ',');
    $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($ph)");
    $stmt->execute(array_values($create));
    return (int)$pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
if ($action === 'seed') {
        $pdo->beginTransaction();
        try {
            // Helper closures
            $pad = fn($n)=> str_pad((string)$n, 2, '0', STR_PAD_LEFT);

            // 1) Units (10)
            $unitIds = [];
            for ($i=1; $i<=10; $i++) {
                $name = 'DEMO - Unit '.$pad($i);
                $unitIds[] = get_or_create($pdo,'units',[ 'name'=>$name ],[ 'name'=>$name, 'short_name'=>'U'.$i ]);
            }

            // 2) Categories (10)
            $catIds = [];
            for ($i=1; $i<=10; $i++) {
                $name = 'DEMO - Category '.$pad($i);
                $catIds[] = get_or_create($pdo,'categories',['name'=>$name],[ 'name'=>$name, 'description'=>'Demo category '.$i ]);
            }

            // 3) Locations (10)
            $locIds = [];
            for ($i=1; $i<=10; $i++) {
                $name = 'DEMO - Location '.$pad($i);
                $locIds[] = get_or_create($pdo,'locations',['name'=>$name],[ 'name'=>$name, 'address'=>'Demo address '.$i ]);
            }

            // 4) Suppliers (10)
            $supIds = [];
            for ($i=1; $i<=10; $i++) {
                $name = 'DEMO - Supplier '.$pad($i);
                $supIds[] = get_or_create($pdo,'suppliers',['name'=>$name],[ 'name'=>$name, 'phone'=>'0700'.str_pad($i,6,'0',STR_PAD_LEFT), 'email'=>'supplier'.$i.'@example.com', 'address'=>'Demo', 'notes'=>'DEMO' ]);
            }

            // 5) Customers (10)
            $cusIds = [];
            for ($i=1; $i<=10; $i++) {
                $name = 'DEMO - Customer '.$pad($i);
                $cusIds[] = get_or_create($pdo,'customers',['name'=>$name],[ 'name'=>$name, 'phone'=>'0711'.str_pad($i,6,'0',STR_PAD_LEFT), 'email'=>'customer'.$i.'@example.com', 'address'=>'Demo', 'notes'=>'DEMO' ]);
            }

            // 6) Products (10)
            $pids = [];
            for ($i=1; $i<=10; $i++) {
                $name = 'DEMO - Product '.$pad($i);
                $catId = $catIds[array_rand($catIds)];
                $unitId = $unitIds[array_rand($unitIds)];
                $cost = mt_rand(1000, 4000)/100; // 10.00 - 40.00
                $price = round($cost * 1.6, 2);
                $reorder = mt_rand(3, 12);
                $pid = get_or_create($pdo,'products',['name'=>$name],[
                    'sku'=>'DEMO-PR-'.$pad($i), 'name'=>$name, 'category_id'=>$catId, 'unit_id'=>$unitId,
                    'cost_price'=>$cost, 'sell_price'=>$price, 'reorder_level'=>$reorder, 'is_active'=>1
                ]);
                $pids[] = $pid;
                // Ensure stock row for each demo location
                foreach ($locIds as $lid) {
                    $pdo->prepare('INSERT IGNORE INTO stock(product_id,location_id,quantity) VALUES (?,?,0)')->execute([$pid,$lid]);
                }
            }

            // 7) Purchases (10) — add plenty of stock
            for ($i=1; $i<=10; $i++) {
                $supplier = $supIds[array_rand($supIds)];
                $pDate = date('Y-m-d', strtotime('-'.mt_rand(12,25).' days'));
                $dueDate = date('Y-m-d', strtotime($pDate.' +7 days'));
                $ref = 'DEMO-PO-'.$pad($i).'-'.date('ymd', strtotime($pDate));
                $itemsCount = mt_rand(2,4);
                $subtotal = 0; $lines = [];
                for ($k=0; $k<$itemsCount; $k++) {
                    $pid = $pids[array_rand($pids)];
                    $lid = $locIds[array_rand($locIds)];
                    $qty = mt_rand(10,35);
                    $cost = (float)$pdo->query('SELECT cost_price FROM products WHERE id='.$pid)->fetchColumn();
                    $lt = $qty*$cost; $subtotal += $lt;
                    $lines[] = [$pid,$lid,$qty,$cost,$lt];
                }
                $discount = mt_rand(0, 100)/10; $tax = 0; $total = $subtotal - $discount + $tax;
                $pdo->prepare('INSERT INTO purchases(supplier_id,reference_no,purchase_date,due_date,status,subtotal,discount,tax,total,notes) VALUES (?,?,?,?,"unpaid",?,?,?,?,?)')->execute([$supplier,$ref,$pDate,$dueDate,$subtotal,$discount,$tax,$total,'DEMO']);
                $purchase_id = (int)$pdo->lastInsertId();
                foreach ($lines as $ln) {
                    [$pid,$lid,$qty,$cost,$lt] = $ln;
                    $pdo->prepare('INSERT INTO purchase_items(purchase_id,product_id,location_id,quantity,unit_cost,line_total) VALUES (?,?,?,?,?,?)')->execute([$purchase_id,$pid,$lid,$qty,$cost,$lt]);
                    adjust_stock($pid,$lid,$qty);
                }
                // random payment portion
                $pay = round($total * (mt_rand(3,10)/10), 2);
                $pdo->prepare('INSERT INTO purchase_payments(purchase_id,payment_date,amount,method,reference,notes) VALUES (?,?,?,?,?,?)')->execute([$purchase_id,date('Y-m-d', strtotime($pDate.' +3 days')),$pay,'Cash','DEMO-PP-'.$pad($i),'DEMO']);
                recalc_purchase_status($purchase_id);
            }

            // 8) Sales (10)
            $hasCost = column_exists('sale_items','cost_at_sale');
            for ($i=1; $i<=10; $i++) {
                $customer = $cusIds[array_rand($cusIds)];
                $sDate = date('Y-m-d', strtotime('-'.mt_rand(1,10).' days'));
                $invoice = 'DEMO-INV-'.$pad($i).'-'.date('ymd', strtotime($sDate));
                $itemsCount = mt_rand(2,4);
                $subtotal = 0; $lines=[];
                for ($k=0; $k<$itemsCount; $k++) {
                    $pid = $pids[array_rand($pids)];
                    $lid = $locIds[array_rand($locIds)];
                    $price = (float)$pdo->query('SELECT sell_price FROM products WHERE id='.$pid)->fetchColumn();
                    $available = get_stock_qty($pid,$lid);
                    $qty = max(1, min((int)$available, mt_rand(1,5)));
                    if ($qty <= 0) { continue; }
                    $lt = $qty*$price; $subtotal += $lt;
                    $lines[] = [$pid,$lid,$qty,$price,$lt];
                }
                if (!$lines) { $i--; continue; }
                $discount = mt_rand(0,50)/10; $tax = 0; $total = $subtotal - $discount + $tax;
                $pdo->prepare('INSERT INTO sales(customer_id,invoice_no,sale_date,due_date,status,subtotal,discount,tax,total,notes) VALUES (?,?,?,?,"unpaid",?,?,?,?,?)')->execute([$customer,$invoice,$sDate,NULL,$subtotal,$discount,$tax,$total,'DEMO']);
                $sale_id = (int)$pdo->lastInsertId();
                foreach ($lines as $ln) {
                    [$pid,$lid,$qty,$price,$lt] = $ln;
                    if ($hasCost) {
                        $cost = average_cost($pid, $sDate);
                        $pdo->prepare('INSERT INTO sale_items(sale_id,product_id,location_id,quantity,unit_price,line_total,cost_at_sale) VALUES (?,?,?,?,?,?,?)')->execute([$sale_id,$pid,$lid,$qty,$price,$lt,$cost]);
                    } else {
                        $pdo->prepare('INSERT INTO sale_items(sale_id,product_id,location_id,quantity,unit_price,line_total) VALUES (?,?,?,?,?,?)')->execute([$sale_id,$pid,$lid,$qty,$price,$lt]);
                    }
                    adjust_stock($pid,$lid,-$qty);
                }
                $pay = round($total * (mt_rand(5,10)/10), 2);
                $pdo->prepare('INSERT INTO sale_payments(sale_id,payment_date,amount,method,reference,notes) VALUES (?,?,?,?,?,?)')->execute([$sale_id,$sDate,$pay,'Cash','DEMO-SP-'.$pad($i),'DEMO']);
                recalc_sale_status($sale_id);
            }

            // 9) Expense Categories and Expenses (10)
            $expCatIds = [];
            for ($i=1; $i<=10; $i++) {
                $name = 'DEMO - ExpenseCat '.$pad($i);
                $expCatIds[] = get_or_create($pdo,'expense_categories',['name'=>$name],[ 'name'=>$name ]);
            }
            for ($i=1; $i<=10; $i++) {
                $cat = $expCatIds[array_rand($expCatIds)];
                $eDate = date('Y-m-d', strtotime('-'.mt_rand(1,12).' days'));
                $amount = mt_rand(500, 25000)/100; // 5.00 - 250.00
                $desc = '[DEMO] Expense '.$pad($i);
                $pdo->prepare('INSERT INTO expenses(category_id,expense_date,description,amount,paid_to,reference) VALUES (?,?,?,?,?,?)')->execute([$cat,$eDate,$desc,$amount,'Vendor','DEMO-EX-'.$pad($i)]);
            }

            $pdo->commit();
            $msg = 'Demo data inserted: 10+ records in each section (master data, purchases, sales, expenses).';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $msg = 'Error seeding demo data: ' . $e->getMessage();
        }
    } elseif ($action === 'clear') {
        $pdo->beginTransaction();
        try {
            // Delete sales & purchases with DEMO identifiers (cascades to items & payments)
            $pdo->exec("DELETE FROM sales WHERE invoice_no LIKE 'DEMO-%'");
            $pdo->exec("DELETE FROM purchases WHERE reference_no LIKE 'DEMO-%'");
            // Delete expenses labeled DEMO
            $pdo->exec("DELETE FROM expenses WHERE description LIKE '[DEMO]%' OR reference LIKE 'DEMO-%'");
            // Delete products, suppliers, customers, locations, categories with DEMO prefix
            $pdo->exec("DELETE FROM products WHERE name LIKE 'DEMO - %'");
            $pdo->exec("DELETE FROM suppliers WHERE name LIKE 'DEMO - %'");
            $pdo->exec("DELETE FROM customers WHERE name LIKE 'DEMO - %'");
            $pdo->exec("DELETE FROM locations WHERE name LIKE 'DEMO - %'");
            $pdo->exec("DELETE FROM categories WHERE name LIKE 'DEMO - %'");
            $pdo->exec("DELETE FROM expense_categories WHERE name LIKE 'DEMO - %'");
            $pdo->commit();
            $msg = 'Demo data cleared.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $msg = 'Error clearing demo data: ' . $e->getMessage();
        }
    }
}
?>

<h2>Demo Data</h2>
<?php if ($msg): ?><div class="note"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<p class="muted">Admin only. Inserts sample records with identifiers starting with "DEMO" so they are easy to remove later.</p>

<form method="post" class="card" style="display:flex;gap:10px;align-items:center">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="seed">
  <button type="submit">Load Demo Data</button>
  <span class="muted">Adds demo products, stock via purchases, sales, payments, and expenses.</span>
</form>

<form method="post" class="card" style="display:flex;gap:10px;align-items:center">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="clear">
  <button type="submit" class="danger" onclick="return confirm('Remove all DEMO data?')">Clear Demo Data</button>
  <span class="muted">Removes all rows with DEMO markers.</span>
</form>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
