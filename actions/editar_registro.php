<?php
// actions/editar_registro.php

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_setor = (int) ($_POST['id_setor'] ?? '');
    $id_registro = (int) ($_POST['id_registro'] ?? '');
    $tipo_registro = ($_POST['tipo_registro'] ?? ''); 
    $redirect_to = $_POST['redirect_to'] ?? '../pages/dashboard.php';
    $retorno = parse_url($redirect_to);
    $paginas = ['../pages/dashboard.php', '../pages/historico.php', '../pages/setores/detalhes.php',
        '/Projeto_Leticia/pages/dashboard.php', '/Projeto_Leticia/pages/historico.php',
        '/Projeto_Leticia/pages/setores/detalhes.php', '/Projeto_Leticia/pages/perfil/perfil.php'];
    if ($retorno === false || isset($retorno['host']) || isset($retorno['scheme'])
        || strpbrk($redirect_to, "\r\n\\\\") !== false || !in_array($retorno['path'] ?? '', $paginas, true)) {
        $redirect_to = '../pages/dashboard.php';
    }

    $descricao = trim($_POST['descricao'] ?? '');
    $data = trim($_POST['data'] ?? '');
    $vencimento = trim($_POST['vencimento'] ?? $data);
    $metodo_pagamento = trim($_POST['metodo_pagamento'] ?? 'OUTRO');
    $id_categoria = (int) ($_POST['id_categoria'] ?? '');
    
    $valorRecebido = ($_POST['valor'] ?? '');
    $novo_valor = str_replace(',', '.', trim($valorRecebido));

    try {
        $novos_centavos = valorEmCentavos($novo_valor);
        $novo_valor = valorParaBanco($novos_centavos);
        validarDataContaReceber($data, 'data');
        validarDataContaReceber($vencimento, 'vencimento');
        $metodo_pagamento = validarMetodoPagamento($metodo_pagamento);
        if ((int) substr($data, 0, 4) < 1000 || (int) substr($vencimento, 0, 4) < 1000 || $descricao === '' || mb_strlen($descricao, 'UTF-8') > 255) throw new InvalidArgumentException('Informe uma descrição e datas válidas.');
        if (!filter_var($_POST['id_registro'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            || !filter_var($_POST['id_categoria'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('Registro ou categoria inválidos.');
        if (!in_array($tipo_registro, ['compromisso', 'conta_receber', 'despesa_efetivada', 'receita_efetivada'], true)) throw new InvalidArgumentException('Tipo de registro inválido.');

        $pdo->beginTransaction();
        $saldo_geral = bloquearSaldoGeral();

        if ($tipo_registro === 'compromisso') {
            editarCompromisso($id_registro, $descricao, $novo_valor, $data, $vencimento, $metodo_pagamento, $id_setor, $id_categoria);
        } elseif ($tipo_registro === 'conta_receber') {
            editarContaReceber($id_registro, $descricao, $novo_valor, $data, $vencimento, $metodo_pagamento, $id_setor, $id_categoria);
        } else {
            $despesa = $tipo_registro === 'despesa_efetivada';
            $tabela = $despesa ? 'despesas' : 'receitas';
            $chave = $despesa ? 'id_despesa' : 'id_receita';
            $stmt = $pdo->prepare("SELECT * FROM $tabela WHERE $chave = :id FOR UPDATE");
            $stmt->execute(['id' => $id_registro]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$registro || $registro['status'] !== 'ATIVA') throw new DomainException('O registro não existe ou já foi estornado.');

            $stmt = $pdo->prepare('SELECT tipo, valor, status FROM movimentacoes WHERE id_movimentacao = :id FOR UPDATE');
            $stmt->execute(['id' => $registro['id_movimentacao_fk']]);
            $mov = $stmt->fetch(PDO::FETCH_ASSOC);
            $antigos_centavos = valorEmCentavos((string) $registro['valor']);
            if (!$mov || $mov['status'] !== 'ATIVA' || $mov['tipo'] !== ($despesa ? 'DESPESA' : 'RECEITA') || valorEmCentavos((string) $mov['valor']) !== $antigos_centavos) {
                throw new DomainException('O registro e o histórico estão inconsistentes. A edição não foi aplicada.');
            }

            $categoria_mudou = (int) $registro['id_categoria_fk'] !== $id_categoria;
            if ($despesa) validarCategoriaCompromisso($id_categoria, $categoria_mudou);
            else validarCategoriaContaReceber($id_categoria, $categoria_mudou);

            // O saldo pertence ao setor gravado, não ao ID enviado pelo formulário.
            $setor_real = (int) $registro['id_setor_fk'];
            $setor = buscarSetorPorId($setor_real, true);
            if (!$setor) throw new DomainException('O setor do registro não foi encontrado.');
            $diferenca = $novos_centavos - $antigos_centavos;
            if ($despesa) {
                if ($diferenca > valorEmCentavos((string) $setor['saldo_atual'], true)) throw new DomainException('Saldo insuficiente. Transação não efetuada.');
                validarLimiteSaldo(valorEmCentavos((string) $setor['saldo_atual'], true) - $diferenca);
                $pdo->prepare('UPDATE setores SET saldo_atual = saldo_atual - :dif WHERE id_setor = :id')
                    ->execute(['dif' => valorParaBanco($diferenca), 'id' => $setor_real]);
            } else {
                if (-$diferenca > $saldo_geral) throw new DomainException('Saldo insuficiente. Transação não efetuada.');
                validarLimiteSaldo($saldo_geral + $diferenca);
                $pdo->prepare('UPDATE saldo_geral SET saldo_atual = saldo_atual + :dif WHERE id_saldo_geral = 1')
                    ->execute(['dif' => valorParaBanco($diferenca)]);
            }

            $pdo->prepare("UPDATE $tabela SET descricao = :desc, valor = :val, data = :data, vencimento = :venc, metodo_pagamento = :metodo, id_categoria_fk = :cat WHERE $chave = :id")
                ->execute(['desc' => $descricao, 'val' => $novo_valor, 'data' => $data, 'venc' => $vencimento, 'metodo' => $metodo_pagamento, 'cat' => $id_categoria, 'id' => $id_registro]);
            $pdo->prepare('UPDATE movimentacoes SET valor = :val, descricao = :desc WHERE id_movimentacao = :id')
                ->execute(['val' => $novo_valor, 'desc' => mb_substr(($despesa ? 'Despesa: ' : 'Receita: ') . $descricao, 0, 255, 'UTF-8'), 'id' => $registro['id_movimentacao_fk']]);
        }

        $pdo->commit();
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
