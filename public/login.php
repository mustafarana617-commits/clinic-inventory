<?php

require_once __DIR__ . '/../helpers.php';

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email = '';

    if (isset($_POST['email'])) {
        $email = trim($_POST['email']);
    }

    $password = '';

    if (isset($_POST['password'])) {
        $password = $_POST['password'];
    }


    $sql = "SELECT id, full_name, password_hash, role, is_active
            FROM users
            WHERE email = :email";

    $statement = $pdo->prepare($sql);
    $statement->execute(array('email' => $email));

    $user = $statement->fetch();

  
    if ($user && password_verify($password, $user['password_hash'])) {

        if ($user['is_active'] == 0) {

            $errorMessage = 'This account is inactive.';

        } else {

            
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            writeAudit($pdo, 'login', 'User logged in');

       
            header('Location: index.php');
            exit;
        }

    } else {

        $errorMessage = 'Invalid email or password.';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Login</title><link rel="stylesheet" href="style.css"></head>
<body>
    <h2>Clinic Inventory Login</h2>
    <?php if ($errorMessage !== ''): ?><p><?= e($errorMessage) ?></p><?php endif; ?>
    <form method="POST">
           Email: <input type="email" name="email" required><br><br>
      Password: <input type="password" name="password" required><br><br>
                        <button type="submit">Log in</button>  
 </form>
</body>
</html>
