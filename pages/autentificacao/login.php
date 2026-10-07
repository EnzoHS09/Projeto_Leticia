<?php


require_once __DIR__ . '/../../crud/crud_administradores.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: ../dashboard.php');
    exit();
}

$erro = $_SESSION['erros'] ?? null;
unset($_SESSION['erros']);

$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $email = strtolower(trim($_POST['email'] ?? ''));
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Informe o e-mail e a senha.';
    } else {
        try {
            $admin = autenticarAdministrador($email, $senha);

            if ($admin !== null) {
                session_regenerate_id(true);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));

                $_SESSION['admin_id']   = (int) $admin['id_admin'];
                $_SESSION['admin_name'] = $admin['nome'];
                $_SESSION['user_tipo']  = 'admin';

                header('Location: ../dashboard.php');
                exit();
            }

            $erro = 'E-mail ou senha incorretos.';
        } catch (Throwable $e) {
            error_log('Erro no login: ' . $e->getMessage());
            $erro = 'Não foi possível realizar o login. Tente novamente mais tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyCash - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/login.css">
</head>
<body>

    <div class="auth-container">
        <div class="auth-header">
            <div class="logo-container">
                <i class="fa-solid fa-chart-line"></i> MyCash
            </div>
            <p>Bem-vindo de volta! Faça login para continuar.</p>
        </div>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($erro): ?>
                <div class="erro erro-geral">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="admin@mycash.com" required
                       value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-primary">Entrar no Sistema</button>
            

        </form>
    </div>

</body>
</html>
