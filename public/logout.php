<?php

require_once __DIR__ . '/../helpers.php';


$_SESSION = array();

session_destroy();

header('Location: login.php');

exit;

?>

