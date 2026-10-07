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
        SELECT id_setor, nome, descricao, saldo_atual, ativo
        FROM setores
        ORDER BY nome, id_setor
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarSetorPorId(int $idSetor, bool $bloquear = false): ?array
{
    global $pdo;

    if ($idSetor <= 0) {
        throw new InvalidArgumentException('O ID do setor deve ser maior que zero.');
    }

    $sql = '
        SELECT id_setor, nome, descricao, saldo_atual, ativo
        FROM setores
        WHERE id_setor = :id_setor
    ';

    if ($bloquear) $sql .= ' FOR UPDATE';
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

function ativarSetor(int $idSetor): int
{
    global $pdo;

    if (buscarSetorPorId($idSetor) === null) {
        throw new DomainException('Setor nao encontrado.');
    }

    $stmt = $pdo->prepare('UPDATE setores SET ativo = TRUE WHERE id_setor = :id_setor');
    $stmt->execute([':id_setor' => $idSetor]);
    return $stmt->rowCount();
}

// Dinheiro é calculado em centavos para não perder partes do valor.
function valorEmCentavos(string $valor, bool $permitirZero = false): int
{
    $valor = str_replace(',', '.', trim($valor));
    if (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $valor)) {
        throw new InvalidArgumentException('Informe um valor válido, com no máximo duas casas decimais.');
    }
    $partes = explode('.', $valor);
    $centavos = (int) $partes[0] * 100 + (int) str_pad($partes[1] ?? '', 2, '0');
    if ($centavos < ($permitirZero ? 0 : 1)) {
        throw new InvalidArgumentException('O valor deve ser de pelo menos R$ 0,01.');
    }
    return $centavos;
}

function valorParaBanco(int $centavos): string
{
    $sinal = $centavos < 0 ? '-' : '';
    $centavos = abs($centavos);
    return $sinal . intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
}

function validarMetodoPagamento(string $metodo): string
{
    $metodo = strtoupper(trim($metodo));
    $permitidos = ['PIX', 'DINHEIRO', 'CARTAO_CREDITO', 'CARTAO_DEBITO', 'BOLETO', 'TRANSFERENCIA', 'OUTRO'];
    if (!in_array($metodo, $permitidos, true)) {
        throw new InvalidArgumentException('Selecione um método de pagamento válido.');
    }
    return $metodo;
}

// Todas as actions financeiras usam a mesma trava dentro da transação.
function bloquearSaldoGeral(): int
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT saldo_atual FROM saldo_geral WHERE id_saldo_geral = 1 FOR UPDATE');
    $stmt->execute();
    $saldo = $stmt->fetchColumn();
    if ($saldo === false) throw new DomainException('O Saldo Geral não foi configurado.');
    return valorEmCentavos((string) $saldo, true);
}

// Um formulário de cadastro/transferência só pode concluir um envio.
function validarEnvioFinanceiro(): string
{
    $envio = $_POST['envio'] ?? '';
    if (!is_string($envio) || !isset($_SESSION['envios'][$envio]) || $_SESSION['envios'][$envio] < time() - 3600) {
        throw new DomainException('Este formulário expirou ou já foi enviado. Atualize a página antes de continuar.');
    }
    return $envio;
}

// DECIMAL(15,2): no máximo 13 dígitos antes da vírgula.
function validarLimiteSaldo(int $centavos): void
{
    if ($centavos < 0 || $centavos > 999999999999999) {
        throw new DomainException('O resultado ultrapassa o limite de saldo permitido pelo banco. Nenhum valor foi alterado.');
    }
}
