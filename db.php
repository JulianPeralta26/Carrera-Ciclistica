<?php
// =====================================================================
// CONEXIÓN A LA BASE DE DATOS
// =====================================================================

$host     = '127.0.0.1';
$dbname   = 'uniespinal_ciclismo';
$user     = 'root';
$password = ''; // Por defecto en local

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
    ]);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
