<?php
// actions/atualizar_perfil.php


require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_administradores.php';
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

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_admin = (int) $_SESSION['admin_id'];
    $redirect_to = '../pages/perfil/perfil.php';
    
    if ($id_admin <= 0) {
        $msg_url = urlencode("Sessão inválida. Por favor, faça login novamente.");
        $link_url = urlencode('../autentificacao/login.php');
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }

    $nome = trim($_POST['nome'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    try {
        if (empty($nome) || empty($email)) {
            throw new Exception("Os campos Nome e E-mail são obrigatórios.");
        }

        $stmtCheck = $pdo->prepare("SELECT id_admin FROM administradores WHERE email = :email AND id_admin != :id");
        $stmtCheck->execute(['email' => $email, 'id' => $id_admin]);
        if ($stmtCheck->fetch()) {
            throw new Exception("Este e-mail já está a ser utilizado por outra conta.");
        }

        $pdo->beginTransaction();

        if (!empty($nova_senha)) {
            if ($nova_senha !== $confirmar_senha) {
                throw new Exception("As novas senhas não coincidem.");
            }
            
            editarDadosAdministrador($id_admin, $nome, $email);
            alterarSenhaAdministrador($id_admin, $nova_senha);
        } 
        else {
            editarDadosAdministrador($id_admin, $nome, $email);
        }

        $pdo->commit();
        
        $_SESSION['admin_name'] = $nome;

        header("Location: {$redirect_to}?sucesso=1");
        exit;

    } catch (Throwable $e) {
        error_log($e->getMessage());
        $mensagem = ($e instanceof PDOException || $e instanceof Error)
            ? 'Não foi possível concluir a operação. Tente novamente.' : $e->getMessage();
        if ($pdo->inTransaction()) $pdo->rollBack();
        
        $msg_erro = urlencode($mensagem);
        header("Location: {$redirect_to}?erro={$msg_erro}");
        exit;
    }
}
?>

