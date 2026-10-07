<?php
    require_once __DIR__ . '/crud/crud_administradores.php';
    if (empty($_SESSION['admin_id'])) {
        header('Location: pages/autentificacao/login.php');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit('Use o formulário para criar um administrador.');
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

    try {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];

$adm_criado = criarAdministrador(
    $nome,
    $email,
    $senha
);

$_SESSION['mensagem_sucesso'] = 'Administrador criado com sucesso. ID: ' . $adm_criado;
    } catch (InvalidArgumentException | DomainException $erro) {
        $_SESSION['mensagem_erro'] = $erro->getMessage();
    } catch (Throwable $erro) {
        error_log($erro->getMessage());
        $_SESSION['mensagem_erro'] = 'Não foi possível criar o administrador.';
    }
    header('Location: cadastro_admin.php');
    exit;

    


?>


