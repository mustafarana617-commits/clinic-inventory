<?php
require_once __DIR__ . '/../helpers.php';
requireRole(['admin', 'inventory_manager']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'];

        if ($action === 'create') {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO purchase_orders (supplier_id, order_date) VALUES (:s, :d)');
            $stmt->execute(['s' => (int) $_POST['supplier_id'], 'd' => $_POST['order_date']]);

            $poId = $pdo->lastInsertId();
            $lines = 0;

            foreach ($_POST['product_id'] as $i => $productId) {
                $qty = $_POST['quantity'][$i];
                $cost = $_POST['unit_cost'][$i];

                if ($productId === '' || $qty === '') {
                    continue;
                }

                if ($qty <= 0 || !is_numeric($cost) || $cost < 0) {
                    throw new Exception('Quantity must be above 0 and cost must be 0 or more.');
                }

                $stmt = $pdo->prepare('INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity_ordered, unit_cost)
                                       VALUES (:po, :p, :q, :c)');

                $stmt->execute(['po' => $poId, 'p' => $productId, 'q' => $qty, 'c' => $cost]);
                $lines++;
            }

            if ($lines === 0) {
                throw new Exception('Add at least one product line.');
            }

            $pdo->commit();
            writeAudit($pdo, 'create_order', 'Order id ' . $poId);

        } elseif ($action === 'cancel') {
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE id = :id AND status = 'ordered'");
            $stmt->execute(['id' => (int) $_POST['id']]);

            writeAudit($pdo, 'cancel_order', 'Order id ' . (int) $_POST['id']);

        } elseif ($action === 'receive') {
            $poId = (int) $_POST['id'];
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT status FROM purchase_orders WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $poId]);

            $status = $stmt->fetchColumn();

            if ($status !== 'ordered' && $status !== 'partially_received') {
                throw new Exception('This order cannot receive goods.');
            }

            $received = 0;

            foreach ($_POST['item_id'] as $i => $itemId) {
                $qty = $_POST['quantity'][$i];

                if ($qty === '' || $qty == 0) {
                    continue;
                }

                $stmt = $pdo->prepare('SELECT product_id, quantity_ordered, quantity_received, unit_cost
                                       FROM purchase_order_items WHERE id = :id AND purchase_order_id = :po FOR UPDATE');

                $stmt->execute(['id' => $itemId, 'po' => $poId]);
                $item = $stmt->fetch();

                if (!$item || $qty < 0 || $qty > $item['quantity_ordered'] - $item['quantity_received']) {
                    throw new Exception('Received quantity is more than the outstanding quantity.');
                }

                $batchNo = trim($_POST['batch_number'][$i]);

                if ($batchNo === '') {
                    throw new Exception('Batch number is required.');
                }

                $expiry = $_POST['expiry_date'][$i] === '' ? null : $_POST['expiry_date'][$i];

                $stmt = $pdo->prepare('INSERT INTO batches (product_id, batch_number, expiry_date, quantity_remaining, unit_cost)
                                       VALUES (:p, :b, :e, :q, :c)');

                $stmt->execute([
                    'p' => $item['product_id'],
                    'b' => $batchNo,
                    'e' => $expiry,
                    'q' => $qty,
                    'c' => $item['unit_cost']
                ]);

                $batchId = $pdo->lastInsertId();

                $stmt = $pdo->prepare('UPDATE purchase_order_items SET quantity_received = quantity_received + :q WHERE id = :id');
                $stmt->execute(['q' => $qty, 'id' => $itemId]);

                $stmt = $pdo->prepare("INSERT INTO stock_movements (batch_id, type, quantity_change, note, user_id)
                                       VALUES (:b, 'receipt', :q, :n, :u)");

                $stmt->execute([
                    'b' => $batchId,
                    'q' => $qty,
                    'n' => 'Order ' . $poId,
                    'u' => $_SESSION['user_id']
                ]);

                $received++;
            }

            if ($received === 0) {
                throw new Exception('Enter a quantity for at least one line.');
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM purchase_order_items
                                   WHERE purchase_order_id = :po AND quantity_received < quantity_ordered');

            $stmt->execute(['po' => $poId]);

            $newStatus = ($stmt->fetchColumn() == 0) ? 'received' : 'partially_received';

            $stmt = $pdo->prepare('UPDATE purchase_orders SET status = :s WHERE id = :id');
            $stmt->execute(['s' => $newStatus, 'id' => $poId]);

            $pdo->commit();
            writeAudit($pdo, 'receive_goods', 'Order id ' . $poId);
        }

        header('Location: purchases.php');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

$receiveId = (int) ($_GET['receive'] ?? 0);
$items = [];

if ($receiveId > 0) {
    $stmt = $pdo->prepare('SELECT i.id, i.quantity_ordered, i.quantity_received, p.name
                           FROM purchase_order_items i JOIN products p ON p.id = i.product_id
                           WHERE i.purchase_order_id = :id');

    $stmt->execute(['id' => $receiveId]);
    $items = $stmt->fetchAll();
}

$orders = $pdo->query('SELECT o.id, o.order_date, o.status, s.name AS supplier
                       FROM purchase_orders o JOIN suppliers s ON s.id = o.supplier_id ORDER BY o.id DESC')->fetchAll();

$suppliers = $pdo->query('SELECT id, name FROM suppliers WHERE is_active = 1')->fetchAll();
$products = $pdo->query('SELECT id, name FROM products WHERE is_active = 1 ORDER BY name')->fetchAll();

pageHeader('Purchases');
?>

<h2>Purchase Orders</h2>

<?php if ($error !== ''): ?>
    <p><?= e($error) ?></p>
<?php endif; ?>

<?php if ($receiveId > 0): ?>

    <h3>Receive goods for order <?= e($receiveId) ?></h3>

    <form method="POST">
        <input type="hidden" name="action" value="receive">
        <input type="hidden" name="id" value="<?= e($receiveId) ?>">

        <?php foreach ($items as $it): ?>
            <input type="hidden" name="item_id[]" value="<?= e($it['id']) ?>">

            <?= e($it['name']) ?> (outstanding <?= e($it['quantity_ordered'] - $it['quantity_received']) ?>):

            Qty <input type="number" name="quantity[]" min="0" step="0.001">
            Batch <input type="text" name="batch_number[]">
            Expiry <input type="date" name="expiry_date[]"><br><br>
        <?php endforeach; ?>

        <button type="submit">Receive</button>
        <a href="purchases.php">Back</a>
    </form>

<?php else: ?>

    <table border="1" cellpadding="5">
        <?php foreach ($orders as $o): ?>
            <tr>
                <td>#<?= e($o['id']) ?></td>
                <td><?= e($o['supplier']) ?></td>
                <td><?= e($o['order_date']) ?></td>
                <td><?= e($o['status']) ?></td>

                <td>
                    <?php if ($o['status'] === 'ordered' || $o['status'] === 'partially_received'): ?>
                        <a href="purchases.php?receive=<?= e($o['id']) ?>">Receive</a>
                    <?php endif; ?>

                    <?php if ($o['status'] === 'ordered'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="id" value="<?= e($o['id']) ?>">
                            <button type="submit">Cancel</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h3>New order</h3>

    <form method="POST">
        <input type="hidden" name="action" value="create">

        Supplier:

        <select name="supplier_id">
            <?php foreach ($suppliers as $s): ?>
                <option value="<?= e($s['id']) ?>"><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>

        Date:
        <input type="date" name="order_date" value="<?= date('Y-m-d') ?>" required>
        <br><br>

        <?php for ($i = 0; $i < 3; $i++): ?>
            <select name="product_id[]">
                <option value="">-- product --</option>

                <?php foreach ($products as $p): ?>
                    <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>

            Qty <input type="number" name="quantity[]" min="0" step="0.001">
            Cost <input type="number" name="unit_cost[]" min="0" step="0.01">
            <br>
        <?php endfor; ?>

        <br>
        <button type="submit">Create order</button>
    </form>

<?php endif; ?>

<?php pageFooter(); ?>
