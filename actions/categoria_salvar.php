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
    $modo = $_POST['modo'] ?? 'criar';
    $nome = $_POST['nome'] ?? '';
    $tipo = $_POST['tipo'] ?? '';

    if ($modo === 'criar') {
        criarCategoria($nome, $tipo);
        $ok = 'criada';
    } elseif ($modo === 'editar') {
        $id = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) throw new InvalidArgumentException('Selecione uma categoria valida.');
        editarCategoria((int) $id, $nome, $tipo);
        $ok = 'editada';
    } else {
        throw new InvalidArgumentException('Operacao de categoria invalida.');
    }

    header('Location: ../pages/categorias.php?ok=' . $ok);
    exit;
} catch (Throwable $e) {
    error_log($e->getMessage());
    header('Location: ../pages/categorias.php?erro=validacao');
    exit;
}
