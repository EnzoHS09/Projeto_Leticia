<?php
    require_once __DIR__ . '/crud/crud_administradores.php';
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];

$adm_criado = criarAdministrador(
    $nome,
    $email,
    $senha
);

echo "Administrador criado de ID: ".$adm_criado;

    


?>


