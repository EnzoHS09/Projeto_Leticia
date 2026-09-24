<?php
session_start();

require_once __DIR__ . '/crud/crud_administradores.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['erro'] = 'Acesso inválido.';
    header('Location: cadastro_admin.php');
    exit();
}

if (!isset($_SESSION['admin_id'])) {
    $_SESSION['erro'] = 'Você precisa estar autenticado para realizar esta ação.';
    header('Location: login.php');
    exit();
}

$nome = trim($_POST['nome'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$senha = $_POST['senha'] ?? '';
$confirmar_senha = trim($_POST['confirmar_senha'] ?? '');

$erros_interno = [];

// Validacao do nome
if ($nome === '') {
    $erros_interno['nome'] = 'Nome e obrigatório';
} elseif (strlen($nome) < 2) {
    $erros_interno['nome'] = 'O nome deve ter no mínimo 2 caracteres';
} elseif (strlen($nome) > 100) {
    $erros_interno['nome'] = 'O nome deve ter no máximo 100 caracteres';
}

// Validacao do email
if ($email === '') {
    $erros_interno['email'] = 'E-mail e obrigatório';
} elseif (strlen($email) > 254) {
    $erros_interno['email'] = 'O e-mail deve ter no máximo 254 caracteres';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros_interno['email'] = 'O e-mail informado e inválido';
}

// Validacao da senha
if ($senha === '') {
    $erros_interno['senha'] = 'Senha e obrigatoria';
} elseif (strlen($senha) < 8) {
    $erros_interno['senha'] = 'A senha deve ter no mínimo 8 caracteres';
} elseif (strlen($senha) > 72) {
    $erros_interno['senha'] = 'A senha ultrapassa o limite de caracteres';
} elseif (!preg_match('/[A-Z]/', $senha)) {
    $erros_interno['senha'] = 'A senha deve ter pelo menos uma letra maiúscula.';
} elseif (!preg_match('/[a-z]/', $senha)) {
    $erros_interno['senha'] = 'A senha deve ter pelo menos uma letra minúscula.';
} elseif (!preg_match('/[0-9]/', $senha)) {
    $erros_interno['senha'] = 'A senha deve ter pelo menos um numero.';
}

// Validacao do confirmar senha
if ($confirmar_senha === '') {
    $erros_interno['confirmar_senha'] = 'Confirmar senha e obrigatório';
} elseif ($confirmar_senha !== $senha) {
    $erros_interno['confirmar_senha'] = 'As senhas não coincidem';
}

if (!empty($erros_interno)) {
    $_SESSION['erros'] = $erros_interno;
    header('Location: cadastro_admin.php');
    exit();
}

$adm_criado = criarAdministrador(
    $nome,
    $email,
    $senha
);

echo "Administrador criado de ID: " . $adm_criado;
?>
