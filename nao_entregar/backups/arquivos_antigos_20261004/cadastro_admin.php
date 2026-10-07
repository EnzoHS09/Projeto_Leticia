<?php
require_once __DIR__ . '/config/conexao.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: pages/autentificacao/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>
</head>
<body>
    <form action="validacao_admin.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach (['mensagem_erro', 'mensagem_sucesso'] as $mensagem): ?>
            <?php if (isset($_SESSION[$mensagem])): ?>
                <p><?= htmlspecialchars($_SESSION[$mensagem], ENT_QUOTES, 'UTF-8') ?></p>
                <?php unset($_SESSION[$mensagem]); ?>
            <?php endif; ?>
        <?php endforeach; ?>

        <label for="nome">
            <input type="text" id="nome" name="nome" placeholder="Nome do administrador">
        </label>

        <label for="email">
            <input type="text" id="email" name="email" placeholder="Insira o seu e-mail">
        </label>

        <label for="senha">
            <input type="password" id="senha" name="senha" placeholder="Crie uma senha">
        </label>

        <button type="submit">Criar administrador</button>
        
        
    </form>

    
</body>
</html>
