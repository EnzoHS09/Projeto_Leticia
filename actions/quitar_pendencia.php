<?php
// actions/quitar_pendencia.php


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

require_once __DIR__ . '/../crud/crud_compromissos.php';
require_once __DIR__ . '/../crud/crud_contas_receber.php';
require_once __DIR__ . '/../crud/crud_setores.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$id_admin_atual = (int) $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_registro = (int) ($_POST['id_registro'] ?? 0);
    $tipo = $_POST['tipo_quitacao'] ?? '';
    $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
    $valorRecebido = $_POST['valor_final'] ?? '0';
    $redirect_to = $_POST['redirect_to'] ?? '../pages/dashboard.php';
    $retorno = parse_url($redirect_to);
    $paginas = ['../pages/dashboard.php', '../pages/historico.php', '../pages/setores/detalhes.php',
        '/Projeto_Leticia/pages/dashboard.php', '/Projeto_Leticia/pages/historico.php',
        '/Projeto_Leticia/pages/setores/detalhes.php', '/Projeto_Leticia/pages/perfil/perfil.php'];
    if ($retorno === false || isset($retorno['host']) || isset($retorno['scheme'])
        || strpbrk($redirect_to, "\r\n\\\\") !== false || !in_array($retorno['path'] ?? '', $paginas, true)) {
        $redirect_to = '../pages/dashboard.php';
    }
    $valor_final = (float) str_replace(',', '.', $valorRecebido);

    if ($id_registro <= 0 || !in_array($tipo, ['despesa', 'receita']) || $valor_final <= 0) {
        $msg_url = urlencode("Dados de quitação inválidos. Verifique os valores inseridos.");
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }

    try {
        $centavos = valorEmCentavos($valorRecebido);
        $valor_final = valorParaBanco($centavos);
        validarDataContaReceber($data_pagamento, 'data do pagamento');
        if ((int) substr($data_pagamento, 0, 4) < 1000 || !filter_var($_POST['id_registro'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('Informe um registro e uma data válidos.');
        $pdo->beginTransaction();
        $saldo_geral = bloquearSaldoGeral();

        if ($tipo === 'despesa') {
            $pendencia = buscarCompromissoPorId($id_registro, true);
            if (!$pendencia) throw new Exception("Compromisso não encontrado.");
            if ($pendencia['status'] === 'PAGO') throw new Exception("Este compromisso já foi pago anteriormente.");

            $id_setor = $pendencia['id_setor_fk'];

            validarCategoriaCompromisso((int) $pendencia['id_categoria_fk'], false);
            $stmt = $pdo->prepare("SELECT id_despesa FROM despesas WHERE id_compromisso_fk = :id AND status = 'ATIVA' LIMIT 1 FOR UPDATE");
            $stmt->execute(['id' => $id_registro]);
            if ($stmt->fetchColumn()) throw new DomainException('Este compromisso já possui um pagamento ativo.');
            $setor = buscarSetorPorId((int) $id_setor, true);
            if (!$setor || !$setor['ativo']) throw new DomainException('O setor desta pendência está desativado.');
            if (valorEmCentavos((string) $setor['saldo_atual'], true) < $centavos) throw new DomainException('Saldo insuficiente. Transação não efetuada.');
            $metodo = validarMetodoPagamento($pendencia['metodo_pagamento']);

            $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual - :val WHERE id_setor = :id")->execute(['val' => $valor_final, 'id' => $id_setor]);
            $stmtMov = $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('DESPESA', :val, NOW(), :desc, 'ATIVA', :adm)");
            $stmtMov->execute(['val' => $valor_final, 'desc' => mb_substr('Pagamento: ' . $pendencia['descricao'], 0, 255, 'UTF-8'), 'adm' => $id_admin_atual]);
            $id_movimentacao = $pdo->lastInsertId();

            $sqlDesp = "INSERT INTO despesas (descricao, valor, data, vencimento, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_compromisso_fk, id_movimentacao_fk) VALUES (:desc, :val, :data_pg, :venc, :metodo, 'ATIVA', :setor, :cat, :adm, :comp_fk, :mov)";
            $pdo->prepare($sqlDesp)->execute(['desc' => $pendencia['descricao'], 'val' => $valor_final, 'data_pg' => $data_pagamento, 'venc' => $pendencia['vencimento'], 'metodo' => $metodo, 'setor' => $id_setor, 'cat' => $pendencia['id_categoria_fk'], 'adm' => $id_admin_atual, 'comp_fk' => $id_registro, 'mov' => $id_movimentacao]);
            
            $pdo->prepare("UPDATE compromissos SET status = 'PAGO' WHERE id_compromisso = :id")->execute(['id' => $id_registro]);
            
        } elseif ($tipo === 'receita') {
            $pendencia = buscarContaReceberPorId($id_registro, true);
            if (!$pendencia) throw new Exception("Conta a Receber não encontrada.");
            if ($pendencia['status'] === 'RECEBIDO') throw new Exception("Esta receita já foi baixada anteriormente.");

            $id_setor = $pendencia['id_setor_fk'];

            validarCategoriaContaReceber((int) $pendencia['id_categoria_fk'], false);
            $stmt = $pdo->prepare("SELECT id_receita FROM receitas WHERE id_conta_receber_fk = :id AND status = 'ATIVA' LIMIT 1 FOR UPDATE");
            $stmt->execute(['id' => $id_registro]);
            if ($stmt->fetchColumn()) throw new DomainException('Esta conta já possui um recebimento ativo.');
            $setor = buscarSetorPorId((int) $id_setor, true);
            if (!$setor || !$setor['ativo']) throw new DomainException('O setor desta pendência está desativado.');
            $metodo = validarMetodoPagamento($pendencia['metodo_pagamento']);
            validarLimiteSaldo($saldo_geral + $centavos);
            $pdo->prepare("UPDATE saldo_geral SET saldo_atual = saldo_atual + :val WHERE id_saldo_geral = 1")->execute(['val' => $valor_final]);

            $stmtMov = $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('RECEITA', :val, NOW(), :desc, 'ATIVA', :adm)");
            $stmtMov->execute(['val' => $valor_final, 'desc' => mb_substr('Recebimento: ' . $pendencia['descricao'], 0, 255, 'UTF-8'), 'adm' => $id_admin_atual]);
            $id_movimentacao = $pdo->lastInsertId();

            $sqlRec = "INSERT INTO receitas (descricao, valor, data, vencimento, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_conta_receber_fk, id_movimentacao_fk) VALUES (:desc, :val, :data_pg, :venc, :metodo, 'ATIVA', :setor, :cat, :adm, :cr_fk, :mov)";
            $pdo->prepare($sqlRec)->execute(['desc' => $pendencia['descricao'], 'val' => $valor_final, 'data_pg' => $data_pagamento, 'venc' => $pendencia['vencimento'], 'metodo' => $metodo, 'setor' => $id_setor, 'cat' => $pendencia['id_categoria_fk'], 'adm' => $id_admin_atual, 'cr_fk' => $id_registro, 'mov' => $id_movimentacao]);
            
            $pdo->prepare("UPDATE contas_receber SET status = 'RECEBIDO' WHERE id_conta_receber = :id")->execute(['id' => $id_registro]);
        }

        $pdo->commit();
        header("Location: " . $redirect_to);
        exit;

    } catch (Throwable $e) {
        error_log($e->getMessage());
        $mensagem = ($e instanceof PDOException || $e instanceof Error)
            ? 'Não foi possível concluir a operação. Tente novamente.' : $e->getMessage();
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg_url = urlencode($mensagem);
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>
