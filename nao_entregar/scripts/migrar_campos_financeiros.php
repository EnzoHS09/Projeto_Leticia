<?php
// Somente pelo terminal. Sem --aplicar, apenas mostra o que será feito.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado pelo terminal.');
}
if (count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--aplicar')) {
    fwrite(STDERR, "Use: php scripts/migrar_campos_financeiros.php [--aplicar]\n");
    exit(1);
}

require_once __DIR__ . '/../config/conexao.php';

$aplicar = in_array('--aplicar', $argv, true);
$enum = "ENUM('PIX','DINHEIRO','CARTAO_CREDITO','CARTAO_DEBITO','BOLETO','TRANSFERENCIA','OUTRO')";
$colunas = [
    ['tabela' => 'contas_receber', 'coluna' => 'data', 'add' => 'DATE NULL AFTER valor', 'preencher' => 'vencimento', 'final' => 'DATE NOT NULL'],
    ['tabela' => 'contas_receber', 'coluna' => 'metodo_pagamento', 'add' => "$enum NULL AFTER vencimento", 'preencher' => "'OUTRO'", 'final' => "$enum NOT NULL"],
    ['tabela' => 'compromissos', 'coluna' => 'data', 'add' => 'DATE NULL AFTER valor', 'preencher' => 'vencimento', 'final' => 'DATE NOT NULL'],
    ['tabela' => 'compromissos', 'coluna' => 'metodo_pagamento', 'add' => "$enum NULL AFTER vencimento", 'preencher' => "'OUTRO'", 'final' => "$enum NOT NULL"],
    ['tabela' => 'receitas', 'coluna' => 'vencimento', 'add' => 'DATE NULL AFTER data', 'preencher' => 'data', 'final' => 'DATE NOT NULL'],
    ['tabela' => 'despesas', 'coluna' => 'vencimento', 'add' => 'DATE NULL AFTER data', 'preencher' => 'data', 'final' => 'DATE NOT NULL'],
];

try {
    $consulta = $pdo->prepare("SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela AND COLUMN_NAME = :coluna");
    $pendentes = [];
    foreach ($colunas as $item) {
        $consulta->execute(['tabela' => $item['tabela'], 'coluna' => $item['coluna']]);
        $atual = $consulta->fetch(PDO::FETCH_ASSOC);
        if (!$atual || $atual['IS_NULLABLE'] === 'YES') $pendentes[] = $item;
    }

    echo "Banco: $dbname; porta: $port.\n";
    if (!$pendentes) {
        echo "Os campos financeiros já estão atualizados. Nada foi alterado.\n";
        exit(0);
    }
    if (!$aplicar) {
        foreach ($pendentes as $item) echo "Simulação: ajustar {$item['tabela']}.{$item['coluna']}.\n";
        echo "Nenhuma alteração aplicada. Faça backup e pare as operações antes de usar --aplicar.\n";
        exit(0);
    }

    foreach ($pendentes as $item) {
        $consulta->execute(['tabela' => $item['tabela'], 'coluna' => $item['coluna']]);
        $atual = $consulta->fetch(PDO::FETCH_ASSOC);
        if (!$atual) {
            $pdo->exec("ALTER TABLE {$item['tabela']} ADD {$item['coluna']} {$item['add']}");
        }
        $pdo->exec("UPDATE {$item['tabela']} SET {$item['coluna']} = {$item['preencher']} WHERE {$item['coluna']} IS NULL");
        $pdo->exec("ALTER TABLE {$item['tabela']} MODIFY {$item['coluna']} {$item['final']}");
        echo "Aplicado: {$item['tabela']}.{$item['coluna']}.\n";
    }
    echo "Concluído sem recalcular valores, saldos ou status.\n";
} catch (Throwable $erro) {
    error_log($erro->getMessage());
    fwrite(STDERR, "Não foi possível concluir a migração. Restaure o backup se alguma alteração parcial tiver sido aplicada.\n");
    exit(1);
}
