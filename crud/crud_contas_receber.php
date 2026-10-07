<?php
// crud/crud_contas_receber.php

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/crud_setores.php';

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
    if ($numeroParcela === null && $totalParcelas === null) return;
    if ($numeroParcela === null || $totalParcelas === null || $numeroParcela < 1 || $totalParcelas < 1 || $numeroParcela > $totalParcelas) {
        throw new InvalidArgumentException('Informe numero e total de parcelas validos, ou deixe ambos vazios.');
    }
}

function validarDadosContaReceber(
    string $descricao, float $valor, string $data, string $vencimento, string $metodo, int $idSetor, int $idCategoria, int $idAdmin, ?int $numeroParcela, ?int $totalParcelas
): array {
    $descricao = trim($descricao);
    if ($descricao === '' || strlen($descricao) > 255) throw new InvalidArgumentException('A descricao e obrigatoria (max 255 chars).');
    if (!is_finite($valor) || $valor <= 0 || $valor >= 10000000000000) throw new InvalidArgumentException('O valor da conta a receber deve ser maior que zero.');
    $valorFormatado = number_format($valor, 2, '.', '');
    if ((float) $valorFormatado <= 0) throw new InvalidArgumentException('O valor deve ser de pelo menos 0.01.');
    if ($idSetor <= 0 || $idCategoria <= 0 || $idAdmin <= 0) throw new InvalidArgumentException('Os IDs devem ser maiores que zero.');
    $data = validarDataContaReceber($data, 'data');
    $vencimento = validarDataContaReceber($vencimento, 'vencimento');
    if ((int) substr($data, 0, 4) < 1000 || (int) substr($vencimento, 0, 4) < 1000) throw new InvalidArgumentException('Informe datas a partir do ano 1000.');
    validarParcelasContaReceber($numeroParcela, $totalParcelas);

    return [$descricao, $valorFormatado, $data, $vencimento, validarMetodoPagamento($metodo)];
}

function validarCategoriaContaReceber(int $idCategoria, bool $exigirAtiva = true): void
{
    global $pdo;
    $sql = 'SELECT tipo, ativo FROM categorias WHERE id_categoria = :id_categoria';
    if ($pdo->inTransaction()) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_categoria' => $idCategoria]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($categoria === false) throw new DomainException('Categoria nao encontrada.');
    if ($categoria['tipo'] !== 'RECEITA') throw new DomainException('A conta a receber deve usar uma categoria do tipo RECEITA.');
    if ($exigirAtiva && !(bool) $categoria['ativo']) throw new DomainException('A categoria escolhida esta desativada.');
}

function criarContaReceber(
    string $descricao, float $valor, string $data, string $vencimento, string $metodo, int $idSetor, int $idCategoria, int $idAdmin, ?int $numeroParcela = null, ?int $totalParcelas = null, ?string $codigoParcelamento = null
): int {
    global $pdo;

    [$descricao, $valorFormatado, $data, $vencimento, $metodo] = validarDadosContaReceber(
        $descricao, $valor, $data, $vencimento, $metodo, $idSetor, $idCategoria, $idAdmin, $numeroParcela, $totalParcelas
    );
    validarCategoriaContaReceber($idCategoria);
    $setor = buscarSetorPorId($idSetor, $pdo->inTransaction());
    if (!$setor || !$setor['ativo']) throw new DomainException('Selecione um setor ativo.');

    $status = ($vencimento < date('Y-m-d')) ? 'ATRASADO' : 'PENDENTE';

    $sql = '
        INSERT INTO contas_receber (
            descricao, valor, data, vencimento, metodo_pagamento, status, codigo_parcelamento, numero_parcela, total_parcelas,
            id_setor_fk, id_categoria_fk, id_admin_fk
        ) VALUES (
            :descricao, :valor, :data, :vencimento, :metodo, :status, :codigo_parcelamento, :numero_parcela, :total_parcelas,
            :id_setor, :id_categoria, :id_admin
        )
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':descricao' => $descricao,
        ':valor' => $valorFormatado,
        ':data' => $data,
        ':vencimento' => $vencimento,
        ':metodo' => $metodo,
        ':status' => $status,
        ':codigo_parcelamento' => $codigoParcelamento,
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
        SELECT cr.id_conta_receber, cr.descricao, cr.valor, cr.data, cr.vencimento, cr.metodo_pagamento, cr.status,
               cr.codigo_parcelamento, cr.numero_parcela, cr.total_parcelas,
               cr.id_setor_fk, s.nome AS nome_setor, cr.id_categoria_fk, c.nome AS nome_categoria,
               cr.id_admin_fk, a.nome AS nome_administrador
        FROM contas_receber AS cr
        INNER JOIN setores AS s ON s.id_setor = cr.id_setor_fk
        INNER JOIN categorias AS c ON c.id_categoria = cr.id_categoria_fk
        INNER JOIN administradores AS a ON a.id_admin = cr.id_admin_fk
    ';
}

function listarContasReceber(): array {
    global $pdo;
    $stmt = $pdo->prepare(sqlConsultaContasReceber() . ' ORDER BY cr.vencimento, cr.id_conta_receber');
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function listarContasReceberPorStatus(string $status): array {
    global $pdo;
    $status = validarStatusContaReceber($status);
    $stmt = $pdo->prepare(sqlConsultaContasReceber() . ' WHERE cr.status = :status ORDER BY cr.vencimento, cr.id_conta_receber');
    $stmt->execute([':status' => $status]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarContaReceberPorId(int $idContaReceber, bool $bloquear = false): ?array {
    global $pdo;
    if ($idContaReceber <= 0) throw new InvalidArgumentException('ID deve ser maior que zero.');
    $stmt = $pdo->prepare(sqlConsultaContasReceber() . ' WHERE cr.id_conta_receber = :id');
    $stmt->execute([':id' => $idContaReceber]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    return $conta !== false ? $conta : null;
}

function editarContaReceber(
    int $idContaReceber, string $descricao, float $valor, string $data, string $vencimento, string $metodo, int $idSetor, int $idCategoria, ?int $numeroParcela = null, ?int $totalParcelas = null
): int {
    global $pdo;
    $contaAtual = buscarContaReceberPorId($idContaReceber, $pdo->inTransaction());
    if ($contaAtual === null) throw new DomainException('Conta a receber nao encontrada.');
    if ($contaAtual['status'] === 'RECEBIDO') throw new DomainException('Uma conta ja recebida nao pode ser editada pelo CRUD cadastral.');

    // O formulário de edição não deve apagar as parcelas já cadastradas.
    $numeroParcela ??= $contaAtual['numero_parcela'] === null ? null : (int) $contaAtual['numero_parcela'];
    $totalParcelas ??= $contaAtual['total_parcelas'] === null ? null : (int) $contaAtual['total_parcelas'];
    [$descricao, $valorFormatado, $data, $vencimento, $metodo] = validarDadosContaReceber($descricao, $valor, $data, $vencimento, $metodo, $idSetor, $idCategoria, (int) $contaAtual['id_admin_fk'], $numeroParcela, $totalParcelas);

    $categoriaFoiAlterada = (int) $contaAtual['id_categoria_fk'] !== $idCategoria;
    validarCategoriaContaReceber($idCategoria, $categoriaFoiAlterada);
    $setor = buscarSetorPorId($idSetor, $pdo->inTransaction());
    if (!$setor || !$setor['ativo']) throw new DomainException('Selecione um setor ativo.');

    $sql = '
        UPDATE contas_receber
        SET descricao = :descricao, valor = :valor, data = :data, vencimento = :vencimento, metodo_pagamento = :metodo,
            status = CASE WHEN :vencimento_status < CURDATE() THEN \'ATRASADO\' ELSE \'PENDENTE\' END,
            numero_parcela = :numero_parcela, total_parcelas = :total_parcelas,
            id_setor_fk = :id_setor, id_categoria_fk = :id_categoria
        WHERE id_conta_receber = :id_conta_receber AND status IN (\'PENDENTE\', \'ATRASADO\')
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':descricao' => $descricao, ':valor' => $valorFormatado, ':data' => $data, ':vencimento' => $vencimento, ':metodo' => $metodo, ':vencimento_status' => $vencimento, ':numero_parcela' => $numeroParcela, ':total_parcelas' => $totalParcelas, ':id_setor' => $idSetor, ':id_categoria' => $idCategoria, ':id_conta_receber' => $idContaReceber]);
    return $stmt->rowCount();
}

function atualizarContasReceberAtrasadas(): int {
    global $pdo;
    $stmt = $pdo->prepare('UPDATE contas_receber SET status = \'ATRASADO\' WHERE status = \'PENDENTE\' AND vencimento < CURDATE()');
    $stmt->execute();
    return $stmt->rowCount();
}
?>
