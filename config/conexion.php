<?php
$host = 'localhost';
$db   = 'db_inventario-21';
$user = 'usrdb-21';
$pass = 'm14pp';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset"; //Data Source Name
$opciones = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $opciones);
} catch (PDOException $e) {
    die('Error de conexión con la base de datos.');
}