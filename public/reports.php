<?php
require_once __DIR__ . '/../helpers.php';
requireLogin();

$types = [
    'stock' => 'Stock',
    'low' => 'Low stock',
    'expiry' => 'Expiry'
];

if ($_SESSION['role'] !== 'staff') {
    $types['movements'] = 'Movements';
}

if ($_SESSION['role'] === 'admin') {
    $types['audit'] = 'Audit log';
}

$type = $_GET['type'] ?? 'stock';

if (!isset($types[$type])) {
    $type = 'stock';
}

$days = (int) EXPIRY_DAYS;

if ($type === 'stock') {
    $sql = 'SELECT p.sku, p.name, p.unit,
                   COALESCE(SUM(b.quantity_remaining), 0) AS total_stock,
                   COALESCE(SUM(CASE WHEN b.expiry_date IS NULL OR b.expiry_date >= CURDATE()
                                THEN b.quantity_remaining END), 0) AS usable_stock
            FROM products p LEFT JOIN batches b ON b.product_id = p.id
            WHERE p.is_active = 1 GROUP BY p.id, p.sku, p.name, p.unit ORDER BY p.name';

} elseif ($type === 'low') {
    $sql = 'SELECT p.sku, p.name, p.minimum_stock, COALESCE(SUM(b.quantity_remaining), 0) AS usable_stock
            FROM products p LEFT JOIN batches b ON b.product_id = p.id
                 AND b.quantity_remaining > 0 AND (b.expiry_date IS NULL OR b.expiry_date >= CURDATE())
            WHERE p.is_active = 1 GROUP BY p.id, p.sku, p.name, p.minimum_stock
            HAVING usable_stock < p.minimum_stock ORDER BY p.name';

} elseif ($type === 'expiry') {
    $sql = "SELECT p.name, b.batch_number, b.expiry_date, b.quantity_remaining,
                   IF(b.expiry_date < CURDATE(), 'expired', 'expiring soon') AS status
            FROM batches b JOIN products p ON p.id = b.product_id
            WHERE b.quantity_remaining > 0 AND b.expiry_date IS NOT NULL
            AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL $days DAY)
            ORDER BY b.expiry_date";

} elseif ($type === 'movements') {
    $sql = 'SELECT m.created_at, m.type, p.name AS product, b.batch_number, m.quantity_change, m.note, u.full_name AS user
            FROM stock_movements m JOIN batches b ON b.id = m.batch_id
            JOIN products p ON p.id = b.product_id JOIN users u ON u.id = m.user_id
            ORDER BY m.id DESC LIMIT 200';

} else {
    $sql = 'SELECT a.created_at, u.full_name AS user, a.action, a.details
            FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 200';
}

$rows = $pdo->query($sql)->fetchAll();

pageHeader('Reports');
?>

<h2>Reports</h2>

<p>
    <?php foreach ($types as $key => $label): ?>
        <a href="reports.php?type=<?= e($key) ?>"><?= e($label) ?></a> |
    <?php endforeach; ?>
</p>

<h3><?= e($types[$type]) ?></h3>

<?php if (count($rows) === 0): ?>

    <p>No data.</p>

<?php else: ?>

    <table border="1" cellpadding="5">
        <tr>
            <?php foreach (array_keys($rows[0]) as $col): ?>
                <th><?= e($col) ?></th>
            <?php endforeach; ?>
        </tr>

        <?php foreach ($rows as $row): ?>
            <tr>
                <?php foreach ($row as $value): ?>
                    <td><?= e($value) ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </table>

<?php endif; ?>

<?php pageFooter(); ?>
