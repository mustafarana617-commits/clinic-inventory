<?php

require_once __DIR__ . '/config.php';


function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}


function requireRole($roles)
{
    requireLogin();

    if (!in_array($_SESSION['role'], $roles)) {
        http_response_code(403);
        exit('403 Forbidden: You do not have permission to open this page.');
    }
}


function writeAudit($pdo, $action, $details)
{
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action, details)
         VALUES (:user_id, :action, :details)'
    );

    $userId = null;

    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }

    $stmt->execute(
        array(
            'user_id' => $userId,
            'action' => $action,
            'details' => $details
        )
    );
}


function pageHeader($title)
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<title>' . e($title) . '</title>';
    echo '<link rel="stylesheet" href="style.css"></head><body>';

    echo '<p><a href="index.php">Dashboard</a> | <a href="products.php">Products</a> | ';

    if (in_array($_SESSION['role'], array('admin', 'inventory_manager'))) {
        echo '<a href="suppliers.php">Suppliers</a> | <a href="purchases.php">Purchases</a> | ';
        echo '<a href="issues.php">Issues</a> | <a href="adjustments.php">Adjustments</a> | ';
    }

    echo '<a href="reports.php">Reports</a> | ';

    if ($_SESSION['role'] === 'admin') {
        echo '<a href="users.php">Users</a> | ';
    }

    echo '<a href="logout.php">Logout (' . e($_SESSION['user_name']) . ')</a></p><hr>';
}


function pageFooter()
{
    echo '<script src="app.js"></script></body></html>';
}
