<?php
// Execute pelo terminal. Sem --aplicar, apenas verifica as contas.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado pelo terminal.');
}
require_once __DIR__ . '/../config/conexao.php';

$aplicar = in_array('--aplicar', $argv, true);
$alteradas = 0;
$preservadas = 0;
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id_admin, senha FROM administradores FOR UPDATE');
    $stmt->execute();
    $contas = $stmt->fetchAll();
    foreach ($contas as $conta) {
        if (password_get_info($conta['senha'])['algo'] !== null) {
            $preservadas++;
            continue;
        }
        if (strlen($conta['senha']) > 72 || str_contains($conta['senha'], "\0")) {
            throw new RuntimeException('Há uma senha incompatível na conta ' . $conta['id_admin'] . '. Nada foi aplicado.');
        }
        $alteradas++;
        if ($aplicar) {
            $update = $pdo->prepare('UPDATE administradores SET senha = :senha WHERE id_admin = :id');
            $update->execute(['senha' => password_hash($conta['senha'], PASSWORD_DEFAULT), 'id' => $conta['id_admin']]);
        }
    }
    if ($aplicar) {
        $pdo->commit();
    } else {
        $pdo->rollBack();
    }
    echo ($aplicar ? 'Aplicação' : 'Simulação') . ": $alteradas senha(s) em texto puro; $preservadas hash(es) preservado(s).\n";
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $erro->getMessage() . "\n");
    exit(1);
}
