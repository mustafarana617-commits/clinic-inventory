<?php

$host = 'localhost';
$databaseName = 'clinic_inventory';
$databaseUser = 'devuser';
$databasePassword = 'DevPassword123!';

$connectionString = "mysql:host=" . $host . ";dbname=" . $databaseName . ";charset=utf8mb4";

$pdo = new PDO(
    $connectionString,
    $databaseUser,
    $databasePassword,
    array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    )
);

define('EXPIRY_DAYS', 30);

session_start();
