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
        </label>

        <label for="email">E-mail:
            <input type="text" id="email" name="email" placeholder="Insira o seu e-mail">
        </label>

        <label for="senha">Senha:
            <input type="text" id="senha" name="senha" placeholder="Crie uma senha">
        </label>

        <label for="confirmar_senha">Confirmar Senha:
            <input type="text" id="confirmar_senha" name="confirmar_senha" placeholder="Confirme a sua senha">
        </label>

        <button type="submit">Criar</button>
        
        
    </form>

    
</body>
</html>