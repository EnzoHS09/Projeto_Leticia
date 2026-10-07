<?php


require_once __DIR__ . '/crud/crud_administradores.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: pages/autentificacao/login.php');
    exit;
}

$paginaFormulario = 'cadastro_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensagem_erro'] = 'Envio invalido.';
    header('Location: ' . $paginaFormulario);
    exit;
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

// Confere se os campos existem e se foram enviados como texto.
$camposObrigatorios = ['nome', 'email', 'senha'];
foreach ($camposObrigatorios as $campo) {
    if (!isset($_POST[$campo]) || !is_string($_POST[$campo])) {
        $_SESSION['mensagem_erro'] = 'Preencha todos os campos.';
        header('Location: ' . $paginaFormulario);
        exit;
    }
}

$nome = trim($_POST['nome']);
$email = strtolower(trim($_POST['email']));
$senha = $_POST['senha'];

// Guarda apenas dados que podem voltar para o formulario. Nunca guarde a senha.
$_SESSION['dados_admin'] = [
    'nome' => $nome,
    'email' => $email,
];

try {
    $admCriado = criarAdministrador($nome, $email, $senha);

    $_SESSION['mensagem_sucesso'] = 'Administrador criado com sucesso. ID: ' . $admCriado;
    unset($_SESSION['dados_admin']);
} catch (InvalidArgumentException | DomainException $erro) {
    // Sao erros esperados de preenchimento ou regra de negocio.
    $_SESSION['mensagem_erro'] = $erro->getMessage();
} catch (Throwable $erro) {
    // Nao exiba detalhes tecnicos do banco para o usuario.
    error_log($erro->getMessage());
    $_SESSION['mensagem_erro'] = 'Nao foi possivel criar o administrador.';
}

header('Location: ' . $paginaFormulario);
exit;

