<?php

require_once __DIR__ . '/../config/conexao.php';

function validarTipoCategoria(string $tipo): string
{
    $tipo = strtoupper(trim($tipo));

    if (!in_array($tipo, ['RECEITA', 'DESPESA'], true)) {
        throw new InvalidArgumentException('O tipo deve ser RECEITA ou DESPESA.');
    }

    return $tipo;
}

function validarDadosCategoria(string $nome, string $tipo): array
{
    $nome = trim($nome);
    $tipo = validarTipoCategoria($tipo);

    if ($nome === '') {
        throw new InvalidArgumentException('O nome da categoria e obrigatorio.');
    }

    if (strlen($nome) > 100) {
        throw new InvalidArgumentException('O nome da categoria deve ter no maximo 100 caracteres.');
    }

    return [$nome, $tipo];
}

function criarCategoria(string $nome, string $tipo): int
{
    global $pdo;

    [$nome, $tipo] = validarDadosCategoria($nome, $tipo);

    $sql = '
        INSERT INTO categorias (nome, tipo)
        VALUES (:nome, :tipo)
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome' => $nome,
        ':tipo' => $tipo,
    ]);

    return (int) $pdo->lastInsertId();
}

function listarCategorias(): array
{
    global $pdo;

    $sql = '
        SELECT id_categoria, nome, tipo, ativo
        FROM categorias
        ORDER BY tipo, nome, id_categoria
    ';

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function listarCategoriasAtivas(): array
{
    global $pdo;

    $sql = '
        SELECT id_categoria, nome, tipo, ativo
        FROM categorias
        WHERE ativo = 1
        ORDER BY tipo, nome, id_categoria
    ';

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function listarCategoriasPorTipo(string $tipo, bool $somenteAtivas = true): array
{
    global $pdo;

    $tipo = validarTipoCategoria($tipo);

    $sql = '
        SELECT id_categoria, nome, tipo, ativo
        FROM categorias
        WHERE tipo = :tipo
    ';

    if ($somenteAtivas) {
        $sql .= ' AND ativo = 1';
    }

    $sql .= ' ORDER BY nome, id_categoria';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tipo' => $tipo,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarCategoriaPorId(int $idCategoria): ?array
{
    global $pdo;

    if ($idCategoria <= 0) {
        throw new InvalidArgumentException('O ID da categoria deve ser maior que zero.');
    }

    $sql = '
        SELECT id_categoria, nome, tipo, ativo
        FROM categorias
        WHERE id_categoria = :id_categoria
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_categoria' => $idCategoria,
    ]);

    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
    return $categoria !== false ? $categoria : null;
}

function categoriaPossuiRegistrosFinanceiros(int $idCategoria): bool
{
    global $pdo;

    $sql = '
        SELECT (
            EXISTS(SELECT 1 FROM receitas WHERE id_categoria = :id_receita)
            OR EXISTS(SELECT 1 FROM despesas WHERE id_categoria = :id_despesa)
            OR EXISTS(SELECT 1 FROM contas_receber WHERE id_categoria = :id_conta)
            OR EXISTS(SELECT 1 FROM compromissos WHERE id_categoria = :id_compromisso)
        ) AS possui_registros
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_receita' => $idCategoria,
        ':id_despesa' => $idCategoria,
        ':id_conta' => $idCategoria,
        ':id_compromisso' => $idCategoria,
    ]);

    return (bool) $stmt->fetchColumn();
}

function editarCategoria(int $idCategoria, string $nome, string $tipo): int
{
    global $pdo;

    $categoriaAtual = buscarCategoriaPorId($idCategoria);
    if ($categoriaAtual === null) {
        throw new DomainException('Categoria nao encontrada.');
    }

    [$nome, $tipo] = validarDadosCategoria($nome, $tipo);

    if ($categoriaAtual['tipo'] !== $tipo && categoriaPossuiRegistrosFinanceiros($idCategoria)) {
        throw new DomainException('O tipo de uma categoria ja utilizada nao pode ser alterado.');
    }

    $sql = '
        UPDATE categorias
        SET nome = :nome,
            tipo = :tipo
        WHERE id_categoria = :id_categoria
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome' => $nome,
        ':tipo' => $tipo,
        ':id_categoria' => $idCategoria,
    ]);

    return $stmt->rowCount();
}

function alterarStatusCategoria(int $idCategoria, bool $ativo): int
{
    global $pdo;

    if (buscarCategoriaPorId($idCategoria) === null) {
        throw new DomainException('Categoria nao encontrada.');
    }

    $sql = '
        UPDATE categorias
        SET ativo = :ativo
        WHERE id_categoria = :id_categoria
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':ativo', $ativo, PDO::PARAM_BOOL);
    $stmt->bindValue(':id_categoria', $idCategoria, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount();
}

function ativarCategoria(int $idCategoria): int
{
    return alterarStatusCategoria($idCategoria, true);
}

function desativarCategoria(int $idCategoria): int
{
    return alterarStatusCategoria($idCategoria, false);
}
