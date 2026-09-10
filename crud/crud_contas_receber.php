<?php

require_once __DIR__ . '/../config/conexao.php';

function validarDataContaReceber(string $data, string $campo): string
{
    $data = trim($data);
    $objetoData = DateTime::createFromFormat('!Y-m-d', $data);

    if ($objetoData === false || $objetoData->format('Y-m-d') !== $data) {
        throw new InvalidArgumentException("O campo $campo deve estar no formato AAAA-MM-DD.");
    }

    return $data;
}

function validarStatusContaReceber(string $status): string
{
    $status = strtoupper(trim($status));

    if (!in_array($status, ['PENDENTE', 'RECEBIDO', 'ATRASADO'], true)) {
        throw new InvalidArgumentException('Status de conta a receber invalido.');
    }

    return $status;
}

function validarParcelasContaReceber(?int $numeroParcela, ?int $totalParcelas): void
{
    if ($numeroParcela === null && $totalParcelas === null) {
        return;
    }

    if (
        $numeroParcela === null
        || $totalParcelas === null
        || $numeroParcela < 1
        || $totalParcelas < 1
        || $numeroParcela > $totalParcelas
    ) {
        throw new InvalidArgumentException('Informe numero e total de parcelas validos, ou deixe ambos vazios.');
    }
}

function validarDadosContaReceber(
    string $descricao,
    float $valor,
    string $vencimento,
    string $metodoPagamento,
    int $idSetor,
    int $idCategoria,
    int $idAdmin,
    ?int $numeroParcela,
    ?int $totalParcelas
): array {
    $descricao = trim($descricao);
    $metodoPagamento = trim($metodoPagamento);

    if ($descricao === '' || strlen($descricao) > 255) {
        throw new InvalidArgumentException('A descricao e obrigatoria e deve ter no maximo 255 caracteres.');
    }

    if (!is_finite($valor) || $valor <= 0) {
        throw new InvalidArgumentException('O valor da conta a receber deve ser maior que zero.');
    }

    $valorFormatado = number_format($valor, 2, '.', '');
    if ((float) $valorFormatado <= 0) {
        throw new InvalidArgumentException('O valor deve ser de pelo menos 0.01.');
    }

    if ($metodoPagamento === '' || strlen($metodoPagamento) > 30) {
        throw new InvalidArgumentException('O metodo de pagamento e obrigatorio e deve ter no maximo 30 caracteres.');
    }

    if ($idSetor <= 0 || $idCategoria <= 0 || $idAdmin <= 0) {
        throw new InvalidArgumentException('Os IDs de setor, categoria e administrador devem ser maiores que zero.');
    }

    $vencimento = validarDataContaReceber($vencimento, 'vencimento');
    validarParcelasContaReceber($numeroParcela, $totalParcelas);

    return [$descricao, $valorFormatado, $vencimento, $metodoPagamento];
}

function validarCategoriaContaReceber(int $idCategoria, bool $exigirAtiva = true): void
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

    if ($categoria['tipo'] !== 'RECEITA') {
        throw new DomainException('A conta a receber deve usar uma categoria do tipo RECEITA.');
    }

    if ($exigirAtiva && !(bool) $categoria['ativo']) {
        throw new DomainException('A categoria escolhida esta desativada.');
    }
}

function criarContaReceber(
    string $descricao,
    float $valor,
    string $vencimento,
    string $metodoPagamento,
    int $idSetor,
    int $idCategoria,
    int $idAdmin,
    ?int $numeroParcela = null,
    ?int $totalParcelas = null
): int {
    global $pdo;

    [$descricao, $valorFormatado, $vencimento, $metodoPagamento] = validarDadosContaReceber(
        $descricao,
        $valor,
        $vencimento,
        $metodoPagamento,
        $idSetor,
        $idCategoria,
        $idAdmin,
        $numeroParcela,
        $totalParcelas
    );
    validarCategoriaContaReceber($idCategoria);

    $sql = '
        INSERT INTO contas_receber (
            descricao,
            valor,
            vencimento,
            metodo_pagamento,
            numero_parcela,
            total_parcelas,
            id_setor,
            id_categoria,
            id_admin
        ) VALUES (
            :descricao,
            :valor,
            :vencimento,
            :metodo_pagamento,
            :numero_parcela,
            :total_parcelas,
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
        ':metodo_pagamento' => $metodoPagamento,
        ':numero_parcela' => $numeroParcela,
        ':total_parcelas' => $totalParcelas,
        ':id_setor' => $idSetor,
        ':id_categoria' => $idCategoria,
        ':id_admin' => $idAdmin,
    ]);

    return (int) $pdo->lastInsertId();
}

function sqlConsultaContasReceber(): string
{
    return '
        SELECT
            cr.id_conta_receber,
            cr.descricao,
            cr.valor,
            cr.vencimento,
            cr.status,
            cr.metodo_pagamento,
            cr.numero_parcela,
            cr.total_parcelas,
            cr.data_recebimento,
            cr.id_setor,
            s.nome AS nome_setor,
            cr.id_categoria,
            c.nome AS nome_categoria,
            cr.id_admin,
            a.nome AS nome_administrador
        FROM contas_receber AS cr
        INNER JOIN setores AS s ON s.id_setor = cr.id_setor
        INNER JOIN categorias AS c ON c.id_categoria = cr.id_categoria
        INNER JOIN administradores AS a ON a.id_admin = cr.id_admin
    ';
}

function listarContasReceber(): array
{
    global $pdo;

    $sql = sqlConsultaContasReceber() . '
        ORDER BY cr.vencimento, cr.id_conta_receber
    ';

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function listarContasReceberPorStatus(string $status): array
{
    global $pdo;

    $status = validarStatusContaReceber($status);
    $sql = sqlConsultaContasReceber() . '
        WHERE cr.status = :status
        ORDER BY cr.vencimento, cr.id_conta_receber
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $status,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarContaReceberPorId(int $idContaReceber): ?array
{
    global $pdo;

    if ($idContaReceber <= 0) {
        throw new InvalidArgumentException('O ID da conta a receber deve ser maior que zero.');
    }

    $sql = sqlConsultaContasReceber() . '
        WHERE cr.id_conta_receber = :id_conta_receber
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_conta_receber' => $idContaReceber,
    ]);

    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    return $conta !== false ? $conta : null;
}

function editarContaReceber(
    int $idContaReceber,
    string $descricao,
    float $valor,
    string $vencimento,
    string $metodoPagamento,
    int $idSetor,
    int $idCategoria,
    ?int $numeroParcela = null,
    ?int $totalParcelas = null
): int {
    global $pdo;

    $contaAtual = buscarContaReceberPorId($idContaReceber);
    if ($contaAtual === null) {
        throw new DomainException('Conta a receber nao encontrada.');
    }

    if ($contaAtual['status'] === 'RECEBIDO') {
        throw new DomainException('Uma conta ja recebida nao pode ser editada pelo CRUD cadastral.');
    }

    [$descricao, $valorFormatado, $vencimento, $metodoPagamento] = validarDadosContaReceber(
        $descricao,
        $valor,
        $vencimento,
        $metodoPagamento,
        $idSetor,
        $idCategoria,
        (int) $contaAtual['id_admin'],
        $numeroParcela,
        $totalParcelas
    );

    $categoriaFoiAlterada = (int) $contaAtual['id_categoria'] !== $idCategoria;
    validarCategoriaContaReceber($idCategoria, $categoriaFoiAlterada);

    $sql = '
        UPDATE contas_receber
        SET descricao = :descricao,
            valor = :valor,
            vencimento = :vencimento,
            status = CASE
                WHEN :vencimento_status < CURDATE() THEN \'ATRASADO\'
                ELSE \'PENDENTE\'
            END,
            metodo_pagamento = :metodo_pagamento,
            numero_parcela = :numero_parcela,
            total_parcelas = :total_parcelas,
            id_setor = :id_setor,
            id_categoria = :id_categoria
        WHERE id_conta_receber = :id_conta_receber
          AND status IN (\'PENDENTE\', \'ATRASADO\')
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':descricao' => $descricao,
        ':valor' => $valorFormatado,
        ':vencimento' => $vencimento,
        ':vencimento_status' => $vencimento,
        ':metodo_pagamento' => $metodoPagamento,
        ':numero_parcela' => $numeroParcela,
        ':total_parcelas' => $totalParcelas,
        ':id_setor' => $idSetor,
        ':id_categoria' => $idCategoria,
        ':id_conta_receber' => $idContaReceber,
    ]);

    return $stmt->rowCount();
}

function atualizarContasReceberAtrasadas(): int
{
    global $pdo;

    $sql = '
        UPDATE contas_receber
        SET status = \'ATRASADO\'
        WHERE status = \'PENDENTE\'
          AND vencimento < CURDATE()
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->rowCount();
}
