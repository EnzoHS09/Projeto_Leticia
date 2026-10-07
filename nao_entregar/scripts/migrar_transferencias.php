<?php
// Somente pelo terminal. Sem --aplicar, apenas confere o banco.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado pelo terminal.');
}
if (count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--aplicar')) {
    fwrite(STDERR, "Use: php scripts/migrar_transferencias.php [--aplicar]\n");
    exit(1);
}
require_once __DIR__ . '/../config/conexao.php';

$aplicar = in_array('--aplicar', $argv, true);
try {
    $stmt = $pdo->prepare("SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transferencias'
        AND COLUMN_NAME = 'id_setor_destino_fk'");
    $stmt->execute();
    $coluna = $stmt->fetch();
    if (!$coluna || !preg_match('/^int(?:\(\d+\))? unsigned$/', $coluna['COLUMN_TYPE'])) {
        fwrite(STDERR, "Estrutura incompatível com banco_my_cash.sql. Nada foi alterado.\n");
        exit(1);
    }
    echo "Banco: $dbname; porta: $port.\n";
    if ($coluna['IS_NULLABLE'] === 'YES') {
        echo "O destino já permite NULL (Saldo Geral). Nada foi alterado.\n";
    } elseif (!$aplicar) {
        echo "Simulação: o destino passará a aceitar NULL para recolhimentos ao Saldo Geral.\n";
        echo "Nenhuma alteração aplicada. Faça backup e pare as operações antes de usar --aplicar.\n";
    } else {
        // ALTER TABLE não tem rollback: faça backup antes. Não modifica registros ou saldos.
        $pdo->exec('ALTER TABLE transferencias MODIFY id_setor_destino_fk INT UNSIGNED NULL');
        echo "Aplicado: o destino agora aceita NULL (Saldo Geral). Registros e saldos não foram recalculados.\n";
    }
} catch (Throwable $erro) {
    error_log($erro->getMessage());
    fwrite(STDERR, "Não foi possível concluir a migração. Confira a conexão, a estrutura do banco e o log do PHP.\n");
    exit(1);
}
