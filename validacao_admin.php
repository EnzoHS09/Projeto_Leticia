<?php
    require_once __DIR__ . '/crud/crud_administradores.php';
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];

$adm_criado = criar_Administrador(
    $nome,
    $email,
    $senha
);

echo "Administrador criado de ID: ".$admCriado;

    // $criar_adm = [
    //     'nome' => $nome,
    //     'email' => $email,
    //     'senha' => $senha
    // ];


?>


