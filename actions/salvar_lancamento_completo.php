<?php
// actions/salvar_lancamento_completo.php


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
    $status_pagamento = $_POST['status_pagamento'] ?? 'pendente';
    $descricao = trim($_POST['descricao'] ?? '');
    
    $valorRecebido = $_POST['valor'] ?? '0';
    $valor_total = str_replace(',', '.', trim($valorRecebido));
    
    $id_setor = (int) ($_POST['id_setor'] ?? 0);
    $id_categoria = (int) ($_POST['id_categoria'] ?? 0);
    $metodo_pagamento = trim($_POST['metodo_pagamento'] ?? 'PIX');
    $data_lancamento = trim($_POST['data_lancamento'] ?? '');
    $data_vencimento = trim($_POST['data_vencimento'] ?? '');
    $redirect_to = $_POST['redirect_to'] ?? '../pages/dashboard.php';
    $retorno = parse_url($redirect_to);
    $paginas = ['../pages/dashboard.php', '../pages/historico.php', '../pages/setores/detalhes.php',
        '/Projeto_Leticia/pages/dashboard.php', '/Projeto_Leticia/pages/historico.php',
        '/Projeto_Leticia/pages/setores/detalhes.php', '/Projeto_Leticia/pages/perfil/perfil.php'];
    if ($retorno === false || isset($retorno['host']) || isset($retorno['scheme'])
        || strpbrk($redirect_to, "\r\n\\\\") !== false || !in_array($retorno['path'] ?? '', $paginas, true)) {
        $redirect_to = '../pages/dashboard.php';
    }

    $is_parcelado = isset($_POST['is_parcelado']);
    $total_parcelas = $is_parcelado ? filter_var($_POST['qtd_parcelas'] ?? '', FILTER_VALIDATE_INT) : 1;

    try {

        $envio = validarEnvioFinanceiro();
        if (!in_array($tipo_operacao, ['receita', 'despesa'], true) || !in_array($status_pagamento, ['pendente', 'efetivado'], true)) {
            throw new InvalidArgumentException('Selecione um tipo e uma situação válidos.');
        }
        if ($descricao === '' || mb_strlen($descricao, 'UTF-8') > 255) throw new InvalidArgumentException('Descrição obrigatória, com no máximo 255 caracteres.');
        if (!filter_var($_POST['id_setor'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('Selecione um setor válido.');
        $dataLancamentoObj = DateTime::createFromFormat('!Y-m-d', $data_lancamento);
        $dataObj = DateTime::createFromFormat('!Y-m-d', $data_vencimento);
        if ($dataLancamentoObj === false || $dataLancamentoObj->format('Y-m-d') !== $data_lancamento
            || $dataObj === false || $dataObj->format('Y-m-d') !== $data_vencimento
            || (int) $dataLancamentoObj->format('Y') < 1000 || (int) $dataObj->format('Y') < 1000) {
            throw new InvalidArgumentException('Informe datas válidas.');
        }

        $total_centavos = valorEmCentavos($valor_total);
        if ($total_parcelas < 1 || $total_parcelas > 120 || $total_centavos < $total_parcelas) throw new InvalidArgumentException('Informe de 1 a 120 parcelas, com pelo menos R$ 0,01 em cada parcela.');
        if (!filter_var($_POST['id_categoria'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('Selecione uma categoria válida.');
        if (!in_array($metodo_pagamento, ['PIX', 'DINHEIRO', 'CARTAO_CREDITO', 'CARTAO_DEBITO', 'BOLETO', 'TRANSFERENCIA', 'OUTRO'], true)) throw new InvalidArgumentException('Selecione um método de pagamento válido.');

        $pdo->beginTransaction();
        bloquearSaldoGeral();
        $setor = buscarSetorPorId($id_setor, true);
        if (!$setor || !$setor['ativo']) throw new DomainException('Selecione um setor ativo.');
        if ($tipo_operacao === 'receita') validarCategoriaContaReceber($id_categoria);
        else validarCategoriaCompromisso($id_categoria);

        $parcela_centavos = intdiv($total_centavos, $total_parcelas);
        $codigo_parcelamento = $total_parcelas > 1 && $tipo_operacao === 'receita' ? bin2hex(random_bytes(16)) : null;

        for ($i = 1; $i <= $total_parcelas; $i++) {
            $centavos_atual = $i === $total_parcelas ? $total_centavos - $parcela_centavos * ($total_parcelas - 1) : $parcela_centavos;
            $valor_atual = valorParaBanco($centavos_atual);
            $meses_add = $i - 1;
            // Mantém o dia original, limitado ao último dia de cada mês.
            $dataParcela = (clone $dataObj)->modify('first day of this month')->modify("+$meses_add months");
            $dataParcela->setDate((int) $dataParcela->format('Y'), (int) $dataParcela->format('m'), min((int) $dataObj->format('j'), (int) $dataParcela->format('t')));
            if ((int) $dataParcela->format('Y') > 9999) throw new InvalidArgumentException('A data das parcelas ultrapassa o limite permitido.');
            $vencimento_atual = $dataParcela->format('Y-m-d');
            
            $eh_efetivado = ($status_pagamento === 'efetivado' && $i === 1);
            $desc_atual = ($total_parcelas > 1) ? "{$descricao} (Parc. $i/$total_parcelas)" : $descricao;
            if (mb_strlen($desc_atual, 'UTF-8') > 255) throw new InvalidArgumentException('Encurte a descrição para incluir a identificação das parcelas.');

            if ($tipo_operacao === 'despesa') {
                if ($eh_efetivado) {
                    $stmtSetor = $pdo->prepare("SELECT saldo_atual FROM setores WHERE id_setor = :id FOR UPDATE");
                    $stmtSetor->execute(['id' => $id_setor]);
                    if (valorEmCentavos((string) $stmtSetor->fetchColumn(), true) < $centavos_atual) throw new DomainException('Saldo insuficiente. Transação não efetuada.');

                    $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual - :val WHERE id_setor = :id")->execute(['val' => $valor_atual, 'id' => $id_setor]);
                    $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('DESPESA', :val, NOW(), :desc, 'ATIVA', :adm)")->execute(['val' => $valor_atual, 'desc' => mb_substr('Despesa: ' . $desc_atual, 0, 255, 'UTF-8'), 'adm' => $id_admin_atual]);
                    $id_mov = $pdo->lastInsertId();

                    $sql = "INSERT INTO despesas (descricao, valor, data, vencimento, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_movimentacao_fk) VALUES (:desc, :val, :data, :venc, :metodo, 'ATIVA', :setor, :cat, :adm, :mov)";
                    $pdo->prepare($sql)->execute(['desc'=>$desc_atual, 'val'=>$valor_atual, 'data'=>$data_lancamento, 'venc'=>$vencimento_atual, 'metodo'=>$metodo_pagamento, 'setor'=>$id_setor, 'cat'=>$id_categoria, 'adm'=>$id_admin_atual, 'mov'=>$id_mov]);
                } else {
                    criarCompromisso($desc_atual, $valor_atual, $data_lancamento, $vencimento_atual, $metodo_pagamento, $id_setor, $id_categoria, $id_admin_atual);
                }
            } 
            else {
                if ($eh_efetivado) {
                    $saldo_geral = bloquearSaldoGeral();
                    validarLimiteSaldo($saldo_geral + $centavos_atual);
                    $pdo->prepare("UPDATE saldo_geral SET saldo_atual = saldo_atual + :val WHERE id_saldo_geral = 1")->execute(['val' => $valor_atual]);

                    $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('RECEITA', :val, NOW(), :desc, 'ATIVA', :adm)")->execute(['val' => $valor_atual, 'desc' => mb_substr('Receita: ' . $desc_atual, 0, 255, 'UTF-8'), 'adm' => $id_admin_atual]);
                    $id_mov = $pdo->lastInsertId();

                    $sql = "INSERT INTO receitas (descricao, valor, data, vencimento, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_movimentacao_fk) VALUES (:desc, :val, :data, :venc, :metodo, 'ATIVA', :setor, :cat, :adm, :mov)";
                    $pdo->prepare($sql)->execute(['desc'=>$desc_atual, 'val'=>$valor_atual, 'data'=>$data_lancamento, 'venc'=>$vencimento_atual, 'metodo'=>$metodo_pagamento, 'setor'=>$id_setor, 'cat'=>$id_categoria, 'adm'=>$id_admin_atual, 'mov'=>$id_mov]);
                } else {
                    $num_p = ($total_parcelas > 1) ? $i : null;
                    $tot_p = ($total_parcelas > 1) ? $total_parcelas : null;
                    
                    criarContaReceber($desc_atual, $valor_atual, $data_lancamento, $vencimento_atual, $metodo_pagamento, $id_setor, $id_categoria, $id_admin_atual, $num_p, $tot_p, $codigo_parcelamento);
                }
            }
        }

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
