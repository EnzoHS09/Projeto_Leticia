<?php
// Converte o banco antigo para a estrutura usada pelo sistema atual.
// Sem --aplicar, apenas confere. As tabelas financeiras antigas precisam estar vazias.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado pelo terminal.');
}
if (count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--aplicar')) {
    fwrite(STDERR, "Use: php scripts/migrar_banco_antigo.php [--aplicar]\n");
    exit(1);
}
require_once __DIR__ . '/../config/conexao.php';

$aplicar = in_array('--aplicar', $argv, true);
$financeiras = ['contas_receber', 'compromissos', 'movimentacoes', 'receitas', 'despesas', 'transferencias'];
$tabelas = ['administradores', 'categorias', 'setores', 'saldo_geral', ...$financeiras];

function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$tabela, $coluna]);
    return (int) $stmt->fetchColumn() === 1;
}

function estruturaNova(PDO $pdo): bool
{
    $colunas = [
        ['administradores', 'senha'], ['administradores', 'ativo'], ['setores', 'ativo'],
        ['contas_receber', 'codigo_parcelamento'], ['contas_receber', 'id_setor_fk'],
        ['compromissos', 'id_setor_fk'], ['movimentacoes', 'criado_em'],
        ['movimentacoes', 'status'], ['movimentacoes', 'id_movimentacao_origem_fk'],
        ['receitas', 'status'], ['receitas', 'id_movimentacao_fk'],
        ['despesas', 'status'], ['despesas', 'id_movimentacao_fk'],
        ['transferencias', 'status'], ['transferencias', 'id_setor_origem_fk'],
        ['transferencias', 'id_setor_destino_fk'], ['transferencias', 'id_movimentacao_fk'],
    ];
    foreach ($colunas as [$tabela, $coluna]) {
        if (!colunaExiste($pdo, $tabela, $coluna)) return false;
    }
    $stmt = $pdo->prepare("SELECT IS_NULLABLE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transferencias'
        AND COLUMN_NAME = 'id_setor_destino_fk'");
    $stmt->execute();
    return $stmt->fetchColumn() === 'YES';
}

function estadoBase(PDO $pdo, string $colunaSenha): string
{
    $dados = [];
    $dados['administradores'] = $pdo->query("SELECT id_admin, nome, email, $colunaSenha AS senha
        FROM administradores ORDER BY id_admin")->fetchAll();
    $dados['categorias'] = $pdo->query('SELECT * FROM categorias ORDER BY id_categoria')->fetchAll();
    $dados['setores'] = $pdo->query('SELECT id_setor, nome, descricao, saldo_atual FROM setores ORDER BY id_setor')->fetchAll();
    $dados['saldo_geral'] = $pdo->query('SELECT * FROM saldo_geral ORDER BY id_saldo_geral')->fetchAll();
    return hash('sha256', serialize($dados));
}

try {
    $nomes = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tabelas as $tabela) {
        if (!in_array($tabela, $nomes, true)) {
            throw new RuntimeException("A tabela $tabela não existe. Nada foi alterado.");
        }
    }

    if (estruturaNova($pdo)) {
        echo "O banco $dbname já usa a estrutura nova. Nada foi alterado.\n";
        exit(0);
    }

    $senhaAntiga = colunaExiste($pdo, 'administradores', 'senha_hash');
    $senhaNova = colunaExiste($pdo, 'administradores', 'senha');
    if ($senhaAntiga === $senhaNova) {
        throw new RuntimeException('A coluna de senha não corresponde ao banco antigo nem ao novo. Nada foi alterado.');
    }

    $ocupadas = [];
    foreach ($financeiras as $tabela) {
        $total = (int) $pdo->query("SELECT COUNT(*) FROM $tabela")->fetchColumn();
        if ($total > 0) $ocupadas[] = "$tabela ($total)";
    }
    if ($ocupadas) {
        throw new RuntimeException('Há dados financeiros que exigem conferência manual: ' . implode(', ', $ocupadas) . '. Nada foi alterado.');
    }

    $duplicados = (int) $pdo->query('SELECT COUNT(*) FROM (
        SELECT nome FROM setores GROUP BY nome HAVING COUNT(*) > 1
    ) AS nomes_repetidos')->fetchColumn();
    if ($duplicados > 0) {
        throw new RuntimeException('Há setores com nomes repetidos. Nada foi alterado.');
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM saldo_geral WHERE id_saldo_geral = 1')->fetchColumn() !== 1
        || (int) $pdo->query('SELECT COUNT(*) FROM saldo_geral')->fetchColumn() !== 1) {
        throw new RuntimeException('O Saldo Geral não possui somente o registro de ID 1. Nada foi alterado.');
    }

    $colunaSenha = $senhaAntiga ? 'senha_hash' : 'senha';
    $estadoAntes = estadoBase($pdo, $colunaSenha);
    $totais = [
        'administradores' => (int) $pdo->query('SELECT COUNT(*) FROM administradores')->fetchColumn(),
        'categorias' => (int) $pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn(),
        'setores' => (int) $pdo->query('SELECT COUNT(*) FROM setores')->fetchColumn(),
    ];

    if (!$aplicar) {
        echo "Simulação no banco $dbname: {$totais['administradores']} administrador(es), "
            . "{$totais['categorias']} categoria(s) e {$totais['setores']} setor(es) serão preservados.\n";
        echo "As seis tabelas financeiras estão vazias e serão atualizadas para a estrutura nova.\n";
        echo "Nenhuma alteração aplicada. O backup é obrigatório antes de usar --aplicar.\n";
        exit(0);
    }

    // ALTER TABLE faz commit automático no MySQL/MariaDB. Por isso o script valida tudo antes.
    if ($senhaAntiga) {
        $pdo->exec('ALTER TABLE administradores CHANGE senha_hash senha VARCHAR(255) NOT NULL');
    }
    if (!colunaExiste($pdo, 'administradores', 'ativo')) {
        $pdo->exec('ALTER TABLE administradores ADD ativo BOOLEAN NOT NULL DEFAULT TRUE');
    }
    if (!colunaExiste($pdo, 'setores', 'ativo')) {
        $pdo->exec('ALTER TABLE setores ADD ativo BOOLEAN NOT NULL DEFAULT TRUE');
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'setores'
        AND COLUMN_NAME = 'nome' AND NON_UNIQUE = 0");
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE setores ADD CONSTRAINT uq_setor_nome UNIQUE (nome)');
    }

    $sqlInicial = file_get_contents(__DIR__ . '/../banco_my_cash.sql');
    if ($sqlInicial === false) throw new RuntimeException('Não foi possível ler banco_my_cash.sql.');
    $criacoes = [];
    foreach ($financeiras as $tabela) {
        $padrao = '/CREATE TABLE\s+' . preg_quote($tabela, '/') . '\s*\(.*?\) ENGINE=InnoDB;/s';
        if (!preg_match($padrao, $sqlInicial, $resultado)) {
            throw new RuntimeException("Não foi possível localizar a estrutura de $tabela no SQL principal.");
        }
        $criacoes[$tabela] = $resultado[0];
    }

    foreach (['transferencias', 'despesas', 'receitas', 'movimentacoes', 'compromissos', 'contas_receber'] as $tabela) {
        $pdo->exec("DROP TABLE $tabela");
    }
    foreach ($financeiras as $tabela) {
        $pdo->exec($criacoes[$tabela]);
    }

    if (!estruturaNova($pdo)) {
        throw new RuntimeException('A estrutura final não passou na conferência. Restaure o backup.');
    }
    if ($estadoAntes !== estadoBase($pdo, 'senha')) {
        throw new RuntimeException('Os dados básicos ficaram diferentes. Restaure o backup.');
    }
    echo "Aplicado no banco $dbname: estrutura atualizada e dados básicos preservados.\n";
} catch (Throwable $erro) {
    error_log($erro->getMessage());
    fwrite(STDERR, $erro->getMessage() . "\n");
    exit(1);
}
