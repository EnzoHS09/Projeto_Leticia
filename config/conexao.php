<?php
// Configurações de conexão com o banco de dados (ajuste conforme sua configuração)
$host = "localhost";
$port = 3306;
$dbname = "my_cash";
$username = "root";
$password = "";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
