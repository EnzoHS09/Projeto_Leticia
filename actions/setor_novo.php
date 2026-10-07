<?php
// actions/setor_novo.php

require_once __DIR__ . '/../config/conexao.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: ../pages/autentificacao/login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use o formulário para realizar esta operação.');
}
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Envio inválido ou sessão expirada. Atualize a página e tente novamente.');
    }
    foreach ($_POST as $campo) {
        if (!is_string($campo)) {
            http_response_code(400);
            exit('Preencha os campos do formulário com texto.');
        }
    }

require_once __DIR__ . '/../crud/crud_setores.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome_setor'] ?? '');
    $descricao = trim($_POST['descricao_setor'] ?? '');
    $redirect = $_POST['redirect_to'] ?? '../pages/dashboard.php';
    $retorno = parse_url($redirect);
    $paginas = ['../pages/dashboard.php', '../pages/historico.php', '../pages/setores/detalhes.php',
        '/Projeto_Leticia/pages/dashboard.php', '/Projeto_Leticia/pages/historico.php',
        '/Projeto_Leticia/pages/setores/detalhes.php', '/Projeto_Leticia/pages/perfil/perfil.php'];
    if ($retorno === false || isset($retorno['host']) || isset($retorno['scheme'])
        || strpbrk($redirect, "\r\n\\\\") !== false || !in_array($retorno['path'] ?? '', $paginas, true)) {
        $redirect = '../pages/dashboard.php';
    }

    if (!empty($nome)) {
        try {
            criarSetor($nome, $descricao);
        } catch (Throwable $e) {
        error_log($e->getMessage());
        $mensagem = ($e instanceof PDOException || $e instanceof Error)
            ? 'Não foi possível concluir a operação. Tente novamente.' : $e->getMessage();
            $msg_url = urlencode($mensagem);
            $link_url = urlencode($redirect);
            header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
            exit;
        }
    }
    
    header("Location: " . $redirect);
    exit;
}
?>
