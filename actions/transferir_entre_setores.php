<?php
// actions/transferir_entre_setores.php


require_once __DIR__ . '/../config/conexao.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: ../pages/autentificacao/login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use o formulário para realizar esta operação.');
}
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Envio inválido ou sessão expirada. Atualize a página e tente novamente.');
    }
    foreach ($_POST as $campo) {
        if (!is_string($campo)) {
            http_response_code(400);
            exit('Preencha os campos do formulário com texto.');
        }
    }

require_once __DIR__ . '/../crud/crud_setores.php'; 

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$id_admin = (int) $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_setor_origem = (int) ($_POST['id_setor_origem'] ?? 0);
    $id_setor_destino = (int) ($_POST['id_setor_destino'] ?? 0);
    $descricao = trim($_POST['descricao_transferencia'] ?? 'Transferência direta entre setores');
    
    $valorRecebido = $_POST['valor_transferencia'] ?? '0';
    $valor = str_replace(',', '.', trim($valorRecebido));
    $redirect_to = '../pages/dashboard.php';

    try {
        $envio = validarEnvioFinanceiro();
        $centavos = valorEmCentavos($valorRecebido);
        $valor = valorParaBanco($centavos);
        if (!filter_var($_POST['id_setor_origem'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            || !filter_var($_POST['id_setor_destino'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            || $descricao === '' || mb_strlen($descricao, 'UTF-8') > 255) throw new InvalidArgumentException('Informe origem, destino e descrição válidos.');
        if ($id_setor_origem === $id_setor_destino) throw new DomainException('O setor de origem e destino não podem ser o mesmo.');

        $pdo->beginTransaction();
        bloquearSaldoGeral();
        $origem = buscarSetorPorId($id_setor_origem, true);
        $destino = buscarSetorPorId($id_setor_destino, true);
        if (!$origem || !$origem['ativo'] || !$destino || !$destino['ativo']) throw new DomainException('Selecione setores ativos.');
        validarLimiteSaldo(valorEmCentavos((string) $destino['saldo_atual'], true) + $centavos);
        if (valorEmCentavos((string) $origem['saldo_atual'], true) < $centavos) throw new DomainException('Saldo insuficiente. Transação não efetuada.');

        $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual - :val WHERE id_setor = :id")->execute(['val' => $valor, 'id' => $id_setor_origem]);
        $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual + :val WHERE id_setor = :id")->execute(['val' => $valor, 'id' => $id_setor_destino]);

        $stmtMov = $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('REALOCACAO', :val, NOW(), :desc, 'ATIVA', :adm)");
        $stmtMov->execute(['val' => $valor, 'desc' => mb_substr('Realocação: ' . $origem['nome'] . ' -> ' . $destino['nome'] . ' - ' . $descricao, 0, 255, 'UTF-8'), 'adm' => $id_admin]);
        $id_movimentacao = $pdo->lastInsertId();

        $sqlTransf = "INSERT INTO transferencias (tipo, valor, data, status, id_setor_origem_fk, id_setor_destino_fk, id_admin_fk, id_movimentacao_fk) 
                      VALUES ('REALOCACAO', :val, CURDATE(), 'ATIVA', :origem, :destino, :adm, :mov)";
        $pdo->prepare($sqlTransf)->execute([
            'val' => $valor,
            'origem' => $id_setor_origem,
            'destino' => $id_setor_destino,
            'adm' => $id_admin,
            'mov' => $id_movimentacao
        ]);

        $pdo->commit();
        unset($_SESSION['envios'][$envio]);
        header("Location: " . $redirect_to);
        exit;

    } catch (Throwable $e) {
        error_log($e->getMessage());
        $mensagem = ($e instanceof PDOException || $e instanceof Error)
            ? 'Não foi possível concluir a operação. Tente novamente.' : $e->getMessage();
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        
        $msg_url = urlencode($mensagem);
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>
