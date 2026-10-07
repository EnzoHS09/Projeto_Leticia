<?php
session_start();

require_once __DIR__ . '/crud/crud_administradores.php';

// Se já estiver logado, não precisa ver a tela de login
if (isset($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit();
}

$erro = $_SESSION['erros'] ?? null;
unset($_SESSION['erros']);

$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Informe o e-mail e a senha.';
    } else {
        try {
            $admin = autenticarAdministrador($email, $senha);

            if ($admin !== null) {
                // Evita session fixation
                session_regenerate_id(true);

                $_SESSION['admin_id']   = (int) $admin['id_admin'];
                $_SESSION['admin_name'] = $admin['nome'];
                $_SESSION['user_tipo']  = 'admin';

                header('Location: cadastro_admin.php');
                exit();
            }

            // Mensagem genérica: não revela se o e-mail existe
            $erro = 'E-mail ou senha incorretos.';
        } catch (PDOException $e) {
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
    <title>Login</title>
</head>

<body>
    <form method="POST" action="login.php">
        <label for="email">E-mail:
            <input type="email" id="email" name="email" required
                   value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
        </label>

        <label for="senha">Senha:
            <input type="password" id="senha" name="senha" required>
        </label>

        <?php if ($erro): ?>
            <p class="erro" style="color: red;"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <button type="submit">Entrar</button>
    </form>
</body>

</html>
