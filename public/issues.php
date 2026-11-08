<?php
require_once __DIR__ . '/../helpers.php';
requireRole(['admin', 'inventory_manager']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $productId = (int) $_POST['product_id'];
        $need = (float) $_POST['quantity'];
        $purpose = trim($_POST['purpose']);

        if ($need <= 0 || $purpose === '') {
            throw new Exception('Quantity and purpose are required.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id, quantity_remaining FROM batches
                               WHERE product_id = :p AND quantity_remaining > 0
                               AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                               ORDER BY expiry_date IS NULL, expiry_date, id FOR UPDATE');

        $stmt->execute(['p' => $productId]);

        foreach ($stmt->fetchAll() as $batch) {
            if ($need <= 0) {
                break;
            }

            $take = min($need, (float) $batch['quantity_remaining']);

            $up = $pdo->prepare('UPDATE batches SET quantity_remaining = quantity_remaining - :q WHERE id = :id');
            $up->execute(['q' => $take, 'id' => $batch['id']]);

            $mv = $pdo->prepare("INSERT INTO stock_movements (batch_id, type, quantity_change, note, user_id)
                                 VALUES (:b, 'issue', :q, :n, :u)");

            $mv->execute([
                'b' => $batch['id'],
                'q' => -$take,
                'n' => $purpose,
                'u' => $_SESSION['user_id']
            ]);

            $need = round($need - $take, 3);
        }

        if ($need > 0) {
            throw new Exception('Not enough usable stock. Nothing was deducted.');
        }

        $pdo->commit();

        writeAudit($pdo, 'issue_stock', 'Product id ' . $productId . ': ' . $purpose);

        header('Location: issues.php?done=1');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

$products = $pdo->query('SELECT id, name FROM products WHERE is_active = 1 ORDER BY name')->fetchAll();

$recent = $pdo->query("SELECT m.created_at, p.name, b.batch_number, m.quantity_change, m.note
                       FROM stock_movements m JOIN batches b ON b.id = m.batch_id
                       JOIN products p ON p.id = b.product_id
                       WHERE m.type = 'issue' ORDER BY m.id DESC LIMIT 10")->fetchAll();

pageHeader('Issue Stock');
?>

<h2>Issue Stock</h2>

<?php if (isset($_GET['done'])): ?>
    <p>Stock issued.</p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p><?= e($error) ?></p>
<?php endif; ?>

<form method="POST">
    Product:

    <select name="product_id">
        <?php foreach ($products as $p): ?>
            <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?></option>
        <?php endforeach; ?>
    </select>

    Quantity:
    <input type="number" name="quantity" min="0" step="0.001" required>

    Purpose:
    <input type="text" name="purpose" required>

    <button type="submit">Issue</button>
</form>

<h3>Recent issues</h3>

<table border="1" cellpadding="5">
    <?php foreach ($recent as $r): ?>
        <tr>
            <td><?= e($r['created_at']) ?></td>
            <td><?= e($r['name']) ?></td>
            <td><?= e($r['batch_number']) ?></td>
            <td><?= e($r['quantity_change']) ?></td>
            <td><?= e($r['note']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<?php pageFooter(); ?>
