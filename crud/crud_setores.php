<?php

require_once __DIR__ . '/../config/conexao.php';

function validarDadosSetor(string $nome, ?string $descricao): array
{
    $nome = trim($nome);
    $descricao = $descricao !== null ? trim($descricao) : null;

    if ($nome === '') {
        throw new InvalidArgumentException('O nome do setor e obrigatorio.');
    }

    if (strlen($nome) > 100) {
        throw new InvalidArgumentException('O nome do setor deve ter no maximo 100 caracteres.');
    }

    if ($descricao === '') {
        $descricao = null;
    }

    if ($descricao !== null && strlen($descricao) > 255) {
        throw new InvalidArgumentException('A descricao do setor deve ter no maximo 255 caracteres.');
    }

    return [$nome, $descricao];
}

function criarSetor(string $nome, ?string $descricao = null): int
{
    global $pdo;

    [$nome, $descricao] = validarDadosSetor($nome, $descricao);

    $sql = '
        INSERT INTO setores (nome, descricao)
        VALUES (:nome, :descricao)
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome' => $nome,
        ':descricao' => $descricao,
    ]);

    return (int) $pdo->lastInsertId();
}

function listarSetores(): array
{
    global $pdo;

    $sql = '
        SELECT id_setor, nome, descricao, saldo_atual
        FROM setores
        ORDER BY nome, id_setor
    ';

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarSetorPorId(int $idSetor): ?array
{
    global $pdo;

    if ($idSetor <= 0) {
        throw new InvalidArgumentException('O ID do setor deve ser maior que zero.');
    }

    $sql = '
        SELECT id_setor, nome, descricao, saldo_atual
        FROM setores
        WHERE id_setor = :id_setor
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_setor' => $idSetor,
    ]);

    $setor = $stmt->fetch(PDO::FETCH_ASSOC);
    return $setor !== false ? $setor : null;
}

function editarSetor(int $idSetor, string $nome, ?string $descricao = null): int
{
    global $pdo;

    if (buscarSetorPorId($idSetor) === null) {
        throw new DomainException('Setor nao encontrado.');
    }

    [$nome, $descricao] = validarDadosSetor($nome, $descricao);

    $sql = '
        UPDATE setores
        SET nome = :nome,
            descricao = :descricao
        WHERE id_setor = :id_setor
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome' => $nome,
        ':descricao' => $descricao,
        ':id_setor' => $idSetor,
    ]);

    return $stmt->rowCount();
}
