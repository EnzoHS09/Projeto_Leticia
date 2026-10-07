<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_setores.php';

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
    $idSetor = filter_input(INPUT_POST, 'id_setor', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$idSetor) {
        throw new InvalidArgumentException('Selecione um setor valido.');
    }
    ativarSetor((int) $idSetor);
    header('Location: ../pages/dashboard.php');
    exit;
} catch (Throwable $e) {
    error_log($e->getMessage());
    $mensagem = ($e instanceof PDOException || $e instanceof Error)
        ? 'Nao foi possivel concluir a operacao. Tente novamente.' : $e->getMessage();
    header('Location: ../pages/erro.php?msg=' . urlencode($mensagem) . '&link=' . urlencode('../pages/dashboard.php'));
    exit;
}
