<?php
// actions/salvar_transacao.php
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
    $tipo_operacao = $_POST['tipo_operacao'] ?? 'despesa';
    $descricao = trim($_POST['descricao'] ?? '');
    
    $valorRecebido = $_POST['valor'] ?? '0';
    $valor = str_replace(',', '.', trim($valorRecebido));
    
    $id_setor = (int) ($_POST['id_setor'] ?? 0);
    $data_vencimento = trim($_POST['data'] ?? '');
    $data_lancamento = $data_vencimento;
    $status_pagamento = $_POST['status_pagamento'] ?? '';
    $metodo_pagamento = 'OUTRO'; 

    try {

        $envio = validarEnvioFinanceiro();
        if (!in_array($tipo_operacao, ['receita', 'despesa'], true) || !in_array($status_pagamento, ['pendente', 'efetivado'], true)) {
            throw new InvalidArgumentException('Selecione um tipo e uma situação válidos.');
        }
        if ($descricao === '' || mb_strlen($descricao, 'UTF-8') > 255) throw new InvalidArgumentException('Descrição obrigatória, com no máximo 255 caracteres.');
        if (!filter_var($_POST['id_setor'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('Selecione um setor válido.');
        $dataObj = DateTime::createFromFormat('!Y-m-d', $data_vencimento);
        if ($dataObj === false || $dataObj->format('Y-m-d') !== $data_vencimento || (int) $dataObj->format('Y') < 1000) throw new InvalidArgumentException('Informe uma data válida.');

        $centavos = valorEmCentavos($valor);
        $valor = valorParaBanco($centavos);
        $pdo->beginTransaction();
        bloquearSaldoGeral();
        $setor = buscarSetorPorId($id_setor, true);
        if (!$setor || !$setor['ativo']) throw new DomainException('Selecione um setor ativo.');

        $tipo_cat = ($tipo_operacao === 'receita') ? 'RECEITA' : 'DESPESA';
        $stmtCat = $pdo->prepare("SELECT id_categoria, ativo FROM categorias WHERE tipo = :tipo AND ativo = 1 ORDER BY id_categoria LIMIT 1 FOR UPDATE");
        $stmtCat->execute(['tipo' => $tipo_cat]);
        $categoria = $stmtCat->fetch(PDO::FETCH_ASSOC);
        
        if ($categoria === false || !(bool)$categoria['ativo']) {
            throw new DomainException('Nenhuma categoria ativa encontrada para o tipo selecionado.');
        }
        $id_categoria = $categoria['id_categoria'];



        if ($tipo_operacao === 'despesa' && $status_pagamento === 'efetivado') {
            $stmtSetor = $pdo->prepare("SELECT saldo_atual FROM setores WHERE id_setor = :id FOR UPDATE");
            $stmtSetor->execute(['id' => $id_setor]);
            if (valorEmCentavos((string) $stmtSetor->fetchColumn(), true) < $centavos) throw new DomainException('Saldo insuficiente. Transação não efetuada.');

            $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('DESPESA', :val, NOW(), :desc, 'ATIVA', :adm)")->execute(['val' => $valor, 'desc' => $descricao, 'adm' => $id_admin_atual]);
            $id_mov = $pdo->lastInsertId();
            
            $pdo->prepare("INSERT INTO despesas (descricao, valor, data, vencimento, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_movimentacao_fk) VALUES (:desc, :val, :data, :venc, :metodo, 'ATIVA', :setor, :cat, :adm, :mov)")->execute(['desc' => $descricao, 'val' => $valor, 'data' => $data_lancamento, 'venc' => $data_vencimento, 'metodo' => $metodo_pagamento, 'setor' => $id_setor, 'cat' => $id_categoria, 'adm' => $id_admin_atual, 'mov' => $id_mov]);
            $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual - :val WHERE id_setor = :id")->execute(['val' => $valor, 'id' => $id_setor]);

        } elseif ($tipo_operacao === 'receita' && $status_pagamento === 'efetivado') {
            $saldo_geral = bloquearSaldoGeral();
            validarLimiteSaldo($saldo_geral + $centavos);

            $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('RECEITA', :val, NOW(), :desc, 'ATIVA', :adm)")->execute(['val' => $valor, 'desc' => $descricao, 'adm' => $id_admin_atual]);
            $id_mov = $pdo->lastInsertId();
            
            $pdo->prepare("INSERT INTO receitas (descricao, valor, data, vencimento, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_movimentacao_fk) VALUES (:desc, :val, :data, :venc, :metodo, 'ATIVA', :setor, :cat, :adm, :mov)")->execute(['desc' => $descricao, 'val' => $valor, 'data' => $data_lancamento, 'venc' => $data_vencimento, 'metodo' => $metodo_pagamento, 'setor' => $id_setor, 'cat' => $id_categoria, 'adm' => $id_admin_atual, 'mov' => $id_mov]);
            $pdo->prepare("UPDATE saldo_geral SET saldo_atual = saldo_atual + :val WHERE id_saldo_geral = 1")->execute(['val' => $valor]);

        } elseif ($tipo_operacao === 'despesa' && $status_pagamento === 'pendente') {
            criarCompromisso($descricao, $valor, $data_lancamento, $data_vencimento, $metodo_pagamento, $id_setor, $id_categoria, $id_admin_atual);
        } elseif ($tipo_operacao === 'receita' && $status_pagamento === 'pendente') {
            criarContaReceber($descricao, $valor, $data_lancamento, $data_vencimento, $metodo_pagamento, $id_setor, $id_categoria, $id_admin_atual);
        }

        $pdo->commit();
        unset($_SESSION['envios'][$envio]);
        header("Location: ../pages/setores/detalhes.php?id=" . $id_setor);
        exit;

    } catch (Throwable $e) {
        error_log($e->getMessage());
        $mensagem = ($e instanceof PDOException || $e instanceof Error)
            ? 'Não foi possível concluir a operação. Tente novamente.' : $e->getMessage();
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        
        $msg_url = urlencode($mensagem);
        $link_url = urlencode("../pages/setores/detalhes.php?id={$id_setor}");
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>
