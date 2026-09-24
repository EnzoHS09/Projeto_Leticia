<?php

require_once __DIR__ . '/../config/conexao.php';

function validarDadosAdministrador(string $nome, string $email): array
{
    $nome = trim($nome);
    $email = strtolower(trim($email));

    if ($nome === '') {
        throw new InvalidArgumentException('O nome do administrador e obrigatorio.');
    }

    if (strlen($nome) > 100) {
        throw new InvalidArgumentException('O nome do administrador deve ter no maximo 100 caracteres.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Informe um e-mail valido.');
    }

    if (strlen($email) > 255) {
        throw new InvalidArgumentException('O e-mail deve ter no maximo 255 caracteres.');
    }

    return [$nome, $email];
}

function validarSenhaAdministrador(string $senha): void
{
    if ($senha === '') {
        throw new InvalidArgumentException('A senha do administrador e obrigatoria.');
    }
}

function emailAdministradorEmUso(string $email, ?int $ignorarIdAdmin = null): bool
{
    global $pdo;

    $sql = '
        SELECT 1
        FROM administradores
        WHERE email = :email
    ';

    $parametros = [':email' => $email];

    if ($ignorarIdAdmin !== null) {
        $sql .= ' AND id_admin <> :id_admin';
        $parametros[':id_admin'] = $ignorarIdAdmin;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    return $stmt->fetchColumn() !== false;
}

function criarAdministrador(string $nome, string $email, string $senha): int
{
    global $pdo;

    [$nome, $email] = validarDadosAdministrador($nome, $email);
    validarSenhaAdministrador($senha);

    if (emailAdministradorEmUso($email)) {
        throw new DomainException('Ja existe um administrador com este e-mail.');
    }

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    if ($senhaHash === false) {
        throw new RuntimeException('Nao foi possivel proteger a senha.');
    }

    $sql = '
        INSERT INTO administradores (nome, email, senha_hash)
        VALUES (:nome, :email, :senha_hash)
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':senha_hash' => $senhaHash,
    ]);

    return (int) $pdo->lastInsertId();
}

function listarAdministradores(): array
{
    global $pdo;

    $sql = '
        SELECT id_admin, nome, email
        FROM administradores
        ORDER BY nome, id_admin
    ';

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarAdministradorPorId(int $idAdmin): ?array
{
    global $pdo;

    if ($idAdmin <= 0) {
        throw new InvalidArgumentException('O ID do administrador deve ser maior que zero.');
    }

    $sql = '
        SELECT id_admin, nome, email
        FROM administradores
        WHERE id_admin = :id_admin
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_admin' => $idAdmin,
    ]);

    $administrador = $stmt->fetch(PDO::FETCH_ASSOC);
    return $administrador !== false ? $administrador : null;
}

function editarDadosAdministrador(int $idAdmin, string $nome, string $email): int
{
    global $pdo;

    if (buscarAdministradorPorId($idAdmin) === null) {
        throw new DomainException('Administrador nao encontrado.');
    }

    [$nome, $email] = validarDadosAdministrador($nome, $email);

    if (emailAdministradorEmUso($email, $idAdmin)) {
        throw new DomainException('Ja existe outro administrador com este e-mail.');
    }

    $sql = '
        UPDATE administradores
        SET nome = :nome,
            email = :email
        WHERE id_admin = :id_admin
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':id_admin' => $idAdmin,
    ]);

    return $stmt->rowCount();
}

function alterarSenhaAdministrador(int $idAdmin, string $novaSenha): int
{
    global $pdo;

    if (buscarAdministradorPorId($idAdmin) === null) {
        throw new DomainException('Administrador nao encontrado.');
    }

    validarSenhaAdministrador($novaSenha);

    $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
    if ($senhaHash === false) {
        throw new RuntimeException('Nao foi possivel proteger a senha.');
    }

    $sql = '
        UPDATE administradores
        SET senha_hash = :senha_hash
        WHERE id_admin = :id_admin
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':senha_hash' => $senhaHash,
        ':id_admin' => $idAdmin,
    ]);

    return $stmt->rowCount();
}
function buscarAdministradorParaLogin(string $email): ?array
{
    global $pdo;
 
    $sql = '
        SELECT id_admin, nome, email, senha_hash
        FROM administradores
        WHERE email = :email
        LIMIT 1
    ';
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
 
    $administrador = $stmt->fetch(PDO::FETCH_ASSOC);
    return $administrador !== false ? $administrador : null;
}
function autenticarAdministrador(string $email, string $senha): ?array
{
    $email = strtolower(trim($email));
 
    $administrador = buscarAdministradorParaLogin($email);
 
    // Hash fictício: quando o e-mail não existe, ainda executamos password_verify
    // para que o tempo de resposta seja parecido e não dê para descobrir e-mails cadastrados.
    $hashFalso = '$2y$10$VvO1GB136tzO6hRAIbHCWuJUhJxLlHtpygEr9kNf7nedcQO1ge4E6';
    $hash = $administrador['senha_hash'] ?? $hashFalso;
 
    $senhaValida = password_verify($senha, $hash);
 
    if ($administrador === null || !$senhaValida) {
        return null;
    }
 
    // Atualiza o hash se o algoritmo/custo padrão do PHP mudou desde o cadastro
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        alterarSenhaAdministrador((int) $administrador['id_admin'], $senha);
    }
 
    unset($administrador['senha_hash']);
    return $administrador;
}