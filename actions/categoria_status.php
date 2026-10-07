<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_categorias.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ../pages/autentificacao/login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use o formulario para realizar esta operacao.');
}
if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    http_response_code(403);
    exit('Envio invalido ou sessao expirada. Atualize a pagina e tente novamente.');
}
foreach ($_POST as $campo) {
    if (!is_string($campo)) {
        http_response_code(400);
        exit('Preencha os campos do formulario com texto.');
    }
}

try {
    $id = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $status = $_POST['status'] ?? '';
    if (!$id || !in_array($status, ['ativar', 'desativar'], true)) {
        throw new InvalidArgumentException('Dados da categoria invalidos.');
    }

    if ($status === 'ativar') ativarCategoria((int) $id);
    else desativarCategoria((int) $id);

    header('Location: ../pages/categorias.php?ok=' . ($status === 'ativar' ? 'ativada' : 'desativada'));
    exit;
} catch (Throwable $e) {
    error_log($e->getMessage());
    header('Location: ../pages/categorias.php?erro=validacao');
    exit;
}
