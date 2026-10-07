<?php
// actions/excluir_setor.php


require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_setores.php';
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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   
    $id_setor = (int)($_POST['id_setor'] ?? 0);
    $redirect_to = '../pages/dashboard.php'; 

    if ($id_setor <= 0) {
        $msg_url = urlencode("ID do setor é inválido.");
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }

    try {
        if (!filter_var($_POST['id_setor'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('Selecione um setor válido.');
        $pdo->beginTransaction();
        bloquearSaldoGeral();
        $setor = buscarSetorPorId($id_setor, true);
        if (!$setor) throw new DomainException('Setor não encontrado.');
        if (valorEmCentavos((string) $setor['saldo_atual'], true) !== 0) throw new DomainException('Recolha ou realoque o saldo do setor antes de desativá-lo.');
        foreach (['compromissos', 'contas_receber'] as $tabela) {
            $stmt = $pdo->prepare("SELECT id_setor_fk FROM $tabela WHERE id_setor_fk = :id AND status IN ('PENDENTE', 'ATRASADO') LIMIT 1 FOR UPDATE");
            $stmt->execute(['id' => $id_setor]);
            if ($stmt->fetchColumn()) throw new DomainException('O setor possui contas ou compromissos pendentes. Resolva as pendências antes de desativá-lo.');
        }

        $stmtUpdate = $pdo->prepare("UPDATE setores SET ativo = FALSE WHERE id_setor = :id");
        $stmtUpdate->execute(['id' => $id_setor]);
        
        $pdo->commit();
        header("Location: " . $redirect_to);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        $mensagem = ($e instanceof PDOException || $e instanceof Error)
            ? 'Não foi possível concluir a operação. Tente novamente.' : $e->getMessage();
        $msg_url = urlencode($mensagem);
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>
