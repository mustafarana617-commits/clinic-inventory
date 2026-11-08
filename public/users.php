<?php
require_once __DIR__ . '/../helpers.php';
requireRole(['admin']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_POST['action'] === 'toggle') {
        $id = (int) $_POST['id'];

        if ($id === (int) $_SESSION['user_id']) {
            $error = 'You cannot deactivate your own account.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id = :id');
            $stmt->execute(['id' => $id]);

            writeAudit($pdo, 'toggle_user', 'User id ' . $id);

            header('Location: users.php');
            exit;
        }

    } else {
        $name = trim($_POST['full_name']);
        $email = trim($_POST['email']);

        if ($name === '') {
            $error = 'Name is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($_POST['password']) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (:n, :e, :p, :r)');

                $stmt->execute([
                    'n' => $name,
                    'e' => $email,
                    'p' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                    'r' => $_POST['role']
                ]);

                writeAudit($pdo, 'add_user', $email);

                header('Location: users.php');
                exit;

            } catch (PDOException $e) {
                $error = 'This email is already registered.';
            }
        }
    }
}

$users = $pdo->query('SELECT id, full_name, email, role, is_active FROM users ORDER BY id')->fetchAll();

pageHeader('Users');
?>

<h2>Users</h2>

<?php if ($error !== ''): ?>
    <p><?= e($error) ?></p>
<?php endif; ?>

<table border="1" cellpadding="5">
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= e($u['full_name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['role']) ?></td>
            <td><?= $u['is_active'] ? 'Active' : 'Inactive' ?></td>
            <td>
                <form method="POST">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                    <button type="submit">
                        <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<h3>Add user</h3>

<form method="POST">
    <input type="hidden" name="action" value="add">

    Name:
    <input type="text" name="full_name" required>

    Email:
    <input type="email" name="email" required>

    Password:
    <input type="password" name="password" required>

    Role:
    <select name="role">
        <option value="staff">staff</option>
        <option value="inventory_manager">inventory_manager</option>
        <option value="admin">admin</option>
    </select>

    <button type="submit">Add</button>
</form>

<?php pageFooter(); ?>
