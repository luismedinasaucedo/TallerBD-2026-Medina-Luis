<?php
// Plantilla de conexión a MariaDB 
$host = 'localhost';
$db   = 'db_inventario';
$user = 'USUARIO_DB';
$pass = 'CONTRASENA_DB';
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