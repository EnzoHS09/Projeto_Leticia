<?php


session_start();

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    $usuarioencontrado = read($pdo, 'admin', "email = " . $pdo->quote($email));

    if ($usuarioencontrado && $usuarioencontrado['senha'] === $senha) {

        $_SESSION['user_id'] = $usuarioencontrado['id_admin'];
        $_SESSION['user_name'] = $usuarioencontrado['nome_admin'];
        $_SESSION['user_tipo'] = 'admin';

        header('location: admin.php');
        exit;
    } else {
        $erro = 'Email ou senha incorretos.';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>login</title>
</head>

<body>

    <form method="POST" action="login.php">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required><br><br>

        <label for="senha">Senha:</label>
        <input type="password" id="senha" name="senha" required><br>

        <?php if ($erro) {
            echo "<p style='color: red;'>$erro</p>";
        } ?>
        <input type="submit" value="Login">
    </form>

</body>

</html>