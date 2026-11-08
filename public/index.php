<?php

require_once __DIR__ . '/../helpers.php';


requireLogin();


$expiryWarningDays = (int) EXPIRY_DAYS;




$activeProducts = $pdo->query(
    "SELECT COUNT(*)
     FROM products
     WHERE is_active = 1"
)->fetchColumn();


$lowStockProducts = $pdo->query(
    "SELECT COUNT(*) FROM (
        SELECT products.id
        FROM products

        LEFT JOIN batches
        ON batches.product_id = products.id
        AND batches.quantity_remaining > 0
        AND (
            batches.expiry_date IS NULL
            OR batches.expiry_date >= CURDATE()
        )

        WHERE products.is_active = 1

        GROUP BY products.id, products.minimum_stock

        HAVING COALESCE(SUM(batches.quantity_remaining), 0)
        < products.minimum_stock

    ) AS low_stock"
)->fetchColumn();


$expiredBatches = $pdo->query(
    "SELECT COUNT(*)
     FROM batches
     WHERE quantity_remaining > 0
     AND expiry_date < CURDATE()"
)->fetchColumn();


$expiringBatches = $pdo->query(
    "SELECT COUNT(*)
     FROM batches
     WHERE quantity_remaining > 0
     AND expiry_date >= CURDATE()
     AND expiry_date <= DATE_ADD(
         CURDATE(),
         INTERVAL $expiryWarningDays DAY
     )"
)->fetchColumn();


$recentActivities = $pdo->query(
    "SELECT
        stock_movements.created_at,
        stock_movements.type,
        stock_movements.quantity_change,
        batches.batch_number,
        products.name AS product,
        users.full_name AS user

     FROM stock_movements

     JOIN batches
     ON batches.id = stock_movements.batch_id

     JOIN products
     ON products.id = batches.product_id

     JOIN users
     ON users.id = stock_movements.user_id

     ORDER BY stock_movements.id DESC

     LIMIT 10"
)->fetchAll();


pageHeader('Dashboard');

?>

<div class="banner">
    <h1>Clinic Inventory Management System</h1>
    <p>Track medicines, batches and expiry dates in one place</p>
</div>


<h2>Dashboard</h2>

<ul>

    <li>
        Active products:
        <?php echo e($activeProducts); ?>
    </li>

    <li>
        Low stock products:
        <?php echo e($lowStockProducts); ?>
    </li>

    <li>
        Expired batches:
        <?php echo e($expiredBatches); ?>
    </li>

    <li>
        Expiring within <?php echo e($expiryWarningDays); ?> days:
        <?php echo e($expiringBatches); ?>
    </li>

</ul>


<h3>Recent activity</h3>

<table border="1" cellpadding="5">

    <tr>
        <th>Time</th>
        <th>Type</th>
        <th>Product</th>
        <th>Batch</th>
        <th>Change</th>
        <th>User</th>
    </tr>

    <?php

    foreach ($recentActivities as $activity) {

    ?>

        <tr>

            <td>
                <?php echo e($activity['created_at']); ?>
            </td>

            <td>
                <?php echo e($activity['type']); ?>
            </td>

            <td>
                <?php echo e($activity['product']); ?>
            </td>

            <td>
                <?php echo e($activity['batch_number']); ?>
            </td>

            <td>
                <?php echo e($activity['quantity_change']); ?>
            </td>

            <td>
                <?php echo e($activity['user']); ?>
            </td>

        </tr>

    <?php
    }
    ?>

</table>

<?php

pageFooter();

?>
