<?php

require_once __DIR__ . '/../config/conexao.php';

function validarDataCompromisso(string $data): string
{
    $data = trim($data);
    $objetoData = DateTime::createFromFormat('!Y-m-d', $data);

    if ($objetoData === false || $objetoData->format('Y-m-d') !== $data) {
        throw new InvalidArgumentException('O vencimento deve estar no formato AAAA-MM-DD.');
    }

    return $data;
}

function validarStatusCompromisso(string $status): string
{
    $status = strtoupper(trim($status));

    if (!in_array($status, ['PENDENTE', 'PAGO', 'ATRASADO'], true)) {
        throw new InvalidArgumentException('Status de compromisso invalido.');
    }

    return $status;
}

function validarDadosCompromisso(
    string $descricao,
    float $valor,
    string $vencimento,
    int $idSetor,
    int $idCategoria,
    int $idAdmin
): array {
    $descricao = trim($descricao);

    if ($descricao === '' || strlen($descricao) > 255) {
        throw new InvalidArgumentException('A descricao e obrigatoria e deve ter no maximo 255 caracteres.');
    }

    if (!is_finite($valor) || $valor <= 0) {
        throw new InvalidArgumentException('O valor do compromisso deve ser maior que zero.');
    }

    $valorFormatado = number_format($valor, 2, '.', '');
    if ((float) $valorFormatado <= 0) {
        throw new InvalidArgumentException('O valor deve ser de pelo menos 0.01.');
    }

    if ($idSetor <= 0 || $idCategoria <= 0 || $idAdmin <= 0) {
        throw new InvalidArgumentException('Os IDs de setor, categoria e administrador devem ser maiores que zero.');
    }

    $vencimento = validarDataCompromisso($vencimento);

    return [$descricao, $valorFormatado, $vencimento];
}

function validarCategoriaCompromisso(int $idCategoria, bool $exigirAtiva = true): void
{
    global $pdo;

    $sql = '
        SELECT tipo, ativo
        FROM categorias
        WHERE id_categoria = :id_categoria
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_categoria' => $idCategoria,
    ]);

    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($categoria === false) {
        throw new DomainException('Categoria nao encontrada.');
    }

    if ($categoria['tipo'] !== 'DESPESA') {
        throw new DomainException('O compromisso deve usar uma categoria do tipo DESPESA.');
    }

    if ($exigirAtiva && !(bool) $categoria['ativo']) {
        throw new DomainException('A categoria escolhida esta desativada.');
    }
}

function criarCompromisso(
    string $descricao,
    float $valor,
    string $vencimento,
    int $idSetor,
    int $idCategoria,
    int $idAdmin
): int {
    global $pdo;

    [$descricao, $valorFormatado, $vencimento] = validarDadosCompromisso(
        $descricao,
        $valor,
        $vencimento,
        $idSetor,
        $idCategoria,
        $idAdmin
    );
    validarCategoriaCompromisso($idCategoria);

    $sql = '
        INSERT INTO compromissos (
            descricao,
            valor,
            vencimento,
            id_setor,
            id_categoria,
            id_admin
        ) VALUES (
            :descricao,
            :valor,
            :vencimento,
            :id_setor,
            :id_categoria,
            :id_admin
        )
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':descricao' => $descricao,
        ':valor' => $valorFormatado,
        ':vencimento' => $vencimento,
        ':id_setor' => $idSetor,
        ':id_categoria' => $idCategoria,
        ':id_admin' => $idAdmin,
    ]);

    return (int) $pdo->lastInsertId();
}

function sqlConsultaCompromissos(): string
{
    return '
        SELECT
            cp.id_compromisso,
            cp.descricao,
            cp.valor,
            cp.vencimento,
            cp.status,
            cp.id_setor,
            s.nome AS nome_setor,
            cp.id_categoria,
            c.nome AS nome_categoria,
            cp.id_admin,
            a.nome AS nome_administrador
        FROM compromissos AS cp
        INNER JOIN setores AS s ON s.id_setor = cp.id_setor
        INNER JOIN categorias AS c ON c.id_categoria = cp.id_categoria
        INNER JOIN administradores AS a ON a.id_admin = cp.id_admin
    ';
}

function listarCompromissos(): array
{
    global $pdo;

    $sql = sqlConsultaCompromissos() . '
        ORDER BY cp.vencimento, cp.id_compromisso
    ';

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function listarCompromissosPorStatus(string $status): array
{
    global $pdo;

    $status = validarStatusCompromisso($status);
    $sql = sqlConsultaCompromissos() . '
        WHERE cp.status = :status
        ORDER BY cp.vencimento, cp.id_compromisso
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $status,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarCompromissoPorId(int $idCompromisso): ?array
{
    global $pdo;

    if ($idCompromisso <= 0) {
        throw new InvalidArgumentException('O ID do compromisso deve ser maior que zero.');
    }

    $sql = sqlConsultaCompromissos() . '
        WHERE cp.id_compromisso = :id_compromisso
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_compromisso' => $idCompromisso,
    ]);

    $compromisso = $stmt->fetch(PDO::FETCH_ASSOC);
    return $compromisso !== false ? $compromisso : null;
}

function editarCompromisso(
    int $idCompromisso,
    string $descricao,
    float $valor,
    string $vencimento,
    int $idSetor,
    int $idCategoria
): int {
    global $pdo;

    $compromissoAtual = buscarCompromissoPorId($idCompromisso);
    if ($compromissoAtual === null) {
        throw new DomainException('Compromisso nao encontrado.');
    }

    if ($compromissoAtual['status'] === 'PAGO') {
        throw new DomainException('Um compromisso ja pago nao pode ser editado pelo CRUD cadastral.');
    }

    [$descricao, $valorFormatado, $vencimento] = validarDadosCompromisso(
        $descricao,
        $valor,
        $vencimento,
        $idSetor,
        $idCategoria,
        (int) $compromissoAtual['id_admin']
    );

    $categoriaFoiAlterada = (int) $compromissoAtual['id_categoria'] !== $idCategoria;
    validarCategoriaCompromisso($idCategoria, $categoriaFoiAlterada);

    $sql = '
        UPDATE compromissos
        SET descricao = :descricao,
            valor = :valor,
            vencimento = :vencimento,
            status = CASE
                WHEN :vencimento_status < CURDATE() THEN \'ATRASADO\'
                ELSE \'PENDENTE\'
            END,
            id_setor = :id_setor,
            id_categoria = :id_categoria
        WHERE id_compromisso = :id_compromisso
          AND status IN (\'PENDENTE\', \'ATRASADO\')
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':descricao' => $descricao,
        ':valor' => $valorFormatado,
        ':vencimento' => $vencimento,
        ':vencimento_status' => $vencimento,
        ':id_setor' => $idSetor,
        ':id_categoria' => $idCategoria,
        ':id_compromisso' => $idCompromisso,
    ]);

    return $stmt->rowCount();
}

function atualizarCompromissosAtrasados(): int
{
    global $pdo;

    $sql = '
        UPDATE compromissos
        SET status = \'ATRASADO\'
        WHERE status = \'PENDENTE\'
          AND vencimento < CURDATE()
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->rowCount();
}
