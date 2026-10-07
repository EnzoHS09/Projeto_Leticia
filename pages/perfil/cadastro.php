<?php
require_once __DIR__ . '/../../crud/crud_administradores.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: ../autentificacao/login.php');
    exit;
}

$erros = [];
$sucesso = '';
$nome_input = '';
$email_input = '';

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

    $nome_input = $_POST['nome'] ?? '';
    $email_input = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if ($senha !== $confirmar_senha) {
        $erros['confirmar_senha'] = 'As senhas não coincidem.';
    } else {
        try {
            criarAdministrador($nome_input, $email_input, $senha);
            
            $sucesso = 'Administrador criado com sucesso!';
            $nome_input = ''; 
            $email_input = '';
            
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'nome') !== false) {
                $erros['nome'] = $msg;
            } elseif (stripos($msg, 'e-mail') !== false || stripos($msg, 'email') !== false) {
                $erros['email'] = $msg;
            } elseif (stripos($msg, 'senha') !== false) {
                $erros['senha'] = $msg;
            } else {
                $erros['geral'] = $msg;
            }
        } catch (DomainException $e) {
            $erros['email'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $erros['geral'] = 'Não foi possível criar o administrador. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyCash - Cadastro de Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/login.css">
</head>
<body>

    <div class="auth-container">
        <div class="auth-header">
            <div class="logo-container">
                <i class="fa-solid fa-user-shield"></i> Cadastro
            </div>
            <p>Registe um novo administrador no sistema.</p>
        </div>

        <form action="cadastro.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            
            <?php if (!empty($sucesso)): ?>
                <div style="background: #d1f4e0; color: #0d7a46; padding: 10px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-weight: 500; font-size: 14px;">
                    <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (isset($erros['geral'])): ?>
                <div class="erro erro-geral">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($erros['geral'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="nome">Nome Completo</label>
                <input type="text" id="nome" name="nome" class="form-control" placeholder="Nome do administrador" value="<?= htmlspecialchars($nome_input, ENT_QUOTES, 'UTF-8') ?>">
                <?php if (isset($erros['nome'])): ?>
                    <span class="erro"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($erros['nome'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">E-mail Corporativo</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Insira o seu e-mail" value="<?= htmlspecialchars($email_input, ENT_QUOTES, 'UTF-8') ?>">
                <?php if (isset($erros['email'])): ?>
                    <span class="erro"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($erros['email'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" class="form-control" placeholder="Crie uma senha forte">
                <?php if (isset($erros['senha'])): ?>
                    <span class="erro"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($erros['senha'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirmar_senha">Confirmar Senha</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" placeholder="Confirme a sua senha">
                <?php if (isset($erros['confirmar_senha'])): ?>
                    <span class="erro"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($erros['confirmar_senha'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-primary">Criar Administrador</button>
            <p style="text-align:center; margin-top:15px;"><a href="../dashboard.php">Voltar ao Dashboard</a></p>

        </form>
    </div>

</body>
</html>
