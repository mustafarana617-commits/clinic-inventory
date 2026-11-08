<?php
require_once __DIR__ . '/../helpers.php';
requireRole(['admin', 'inventory_manager']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $batchId = (int) $_POST['batch_id'];
        $change = (float) $_POST['change'];
        $note = $_POST['reason'] . ' - ' . trim($_POST['note']);

        if ($change == 0) {
            throw new Exception('Change cannot be zero.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT quantity_remaining FROM batches WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $batchId]);

        $remaining = $stmt->fetchColumn();

        if ($remaining === false) {
            throw new Exception('Batch not found.');
        }

        if ($remaining + $change < 0) {
            throw new Exception('A batch quantity cannot go below zero.');
        }

        $stmt = $pdo->prepare('UPDATE batches SET quantity_remaining = quantity_remaining + :c WHERE id = :id');
        $stmt->execute(['c' => $change, 'id' => $batchId]);

        $stmt = $pdo->prepare("INSERT INTO stock_movements (batch_id, type, quantity_change, note, user_id)
                               VALUES (:b, 'adjustment', :c, :n, :u)");

        $stmt->execute([
            'b' => $batchId,
            'c' => $change,
            'n' => $note,
            'u' => $_SESSION['user_id']
        ]);

        $pdo->commit();

        writeAudit($pdo, 'adjust_stock', 'Batch id ' . $batchId . ' change ' . $change);

        header('Location: adjustments.php?done=1');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

$batches = $pdo->query('SELECT b.id, b.batch_number, b.expiry_date, b.quantity_remaining, p.name
                        FROM batches b JOIN products p ON p.id = b.product_id ORDER BY p.name, b.expiry_date')->fetchAll();

$recent = $pdo->query("SELECT m.created_at, p.name, b.batch_number, m.quantity_change, m.note
                       FROM stock_movements m JOIN batches b ON b.id = m.batch_id
                       JOIN products p ON p.id = b.product_id
                       WHERE m.type = 'adjustment' ORDER BY m.id DESC LIMIT 10")->fetchAll();

pageHeader('Adjustments');
?>

<h2>Stock Adjustment</h2>

<?php if (isset($_GET['done'])): ?>
    <p>Adjustment saved.</p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p><?= e($error) ?></p>
<?php endif; ?>

<form method="POST">
    Batch:

    <select name="batch_id">
        <?php foreach ($batches as $b): ?>
            <option value="<?= e($b['id']) ?>">
                <?= e($b['name']) ?> |
                <?= e($b['batch_number']) ?> |
                exp <?= e($b['expiry_date']) ?> |
                left <?= e($b['quantity_remaining']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    Change (minus removes):
    <input type="number" name="change" step="0.001" required>

    Reason:

    <select name="reason">
        <option>damaged</option>
        <option>expired</option>
        <option>lost</option>
        <option>stock_count</option>
        <option>other</option>
    </select>

    Note:
    <input type="text" name="note">

    <button type="submit">Save</button>
</form>

<h3>Recent adjustments</h3>

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
