<?php
session_start();

if (isset($_SESSION['erros']['nome'])) {
    $erros_nome = $_SESSION['erros']['nome'];
}

if (isset($_SESSION['erros']['email'])) {
    $erros_email = $_SESSION['erros']['email'];
}

if (isset($_SESSION['erros']['senha'])) {
    $erros_senha = $_SESSION['erros']['senha'];
}

if (isset($_SESSION['erros']['confirmar_senha'])) {
    $erros_confirmar_senha = $_SESSION['erros']['confirmar_senha'];
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

        <label for="nome">Nome:
            <input type="text" id="nome" name="nome" placeholder="Nome do administrador">

            <?php if (isset($erros_nome)): ?>
                <span class="erro"><?= $erros_nome ?></span>
            <?php endif; ?>

        </label>

        <label for="email">E-mail:
            <input type="text" id="email" name="email" placeholder="Insira o seu e-mail">

            <?php if (isset($erros_email)): ?>
                <span class="erro"><?= $erros_email ?></span>
            <?php endif; ?>
            
        </label>

        <label for="senha">Senha:
            <input type="text" id="senha" name="senha" placeholder="Crie uma senha">

            <?php if (isset($erros_senha)): ?>
                <span class="erro"><?= $erros_senha ?></span>
            <?php endif; ?>
            
        </label>

        <label for="confirmar_senha">Confirmar Senha:
            <input type="text" id="confirmar_senha" name="confirmar_senha" placeholder="Confirme a sua senha">

            <?php if (isset($erros_confirmar_senha)): ?>
                <span class="erro"><?= $erros_confirmar_senha ?></span>
            <?php endif; ?>
            
        </label>

        <button type="submit">Criar</button>


    </form>


</body>

</html>