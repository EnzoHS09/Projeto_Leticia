<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'path' => '/Projeto_Leticia/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
}

// Configurações de conexão com o banco de dados (ajuste conforme sua configuração)
$host = getenv('MY_CASH_DB_HOST') ?: 'localhost';
$port = getenv('MY_CASH_DB_PORT') ?: 3306;
$dbname = getenv('MY_CASH_DB_NAME') ?: 'my_cash';
$username = getenv('MY_CASH_DB_USER') ?: 'root';
$password = getenv('MY_CASH_DB_PASSWORD') ?: '';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '-03:00'");

    // Uma conta desativada também perde acesso se já estava conectada.
    if (PHP_SAPI !== 'cli' && isset($_SESSION['admin_id'])) {
        $stmt = $pdo->prepare('SELECT ativo FROM administradores WHERE id_admin = :id');
        $stmt->execute(['id' => $_SESSION['admin_id']]);
        if (!$stmt->fetchColumn()) {
            $_SESSION = [];
            session_regenerate_id(true);
            header('Location: /Projeto_Leticia/pages/autentificacao/login.php');
            exit;
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    if (PHP_SAPI !== 'cli') {
        http_response_code(503);
    }
    exit('Não foi possível conectar ao sistema. Tente novamente mais tarde.');
}
