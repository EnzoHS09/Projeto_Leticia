<?php
// actions/estornar_movimentacao.php


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

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_movimentacao = (int)($_POST['id_movimentacao'] ?? 0);
    $id_admin_logado = (int) $_SESSION['admin_id'];
    $redirect_to = '../pages/historico.php';

    if ($id_movimentacao <= 0) {
        $msg_url = urlencode("O ID da movimentação é inválido.");
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }

    try {
        if (!filter_var($_POST['id_movimentacao'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('Movimentação inválida.');
        $pdo->beginTransaction();
        $saldo_geral = bloquearSaldoGeral();
        $stmt = $pdo->prepare('SELECT * FROM movimentacoes WHERE id_movimentacao = :id FOR UPDATE');
        $stmt->execute(['id' => $id_movimentacao]);
        $mov = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$mov) throw new DomainException('Movimentação não encontrada.');
        if ($mov['tipo'] === 'ESTORNO') throw new DomainException('Não é permitido estornar outro estorno.');
        if ($mov['status'] !== 'ATIVA') throw new DomainException('Esta movimentação já foi estornada.');
        $stmt = $pdo->prepare('SELECT id_movimentacao FROM movimentacoes WHERE id_movimentacao_origem_fk = :id FOR UPDATE');
        $stmt->execute(['id' => $id_movimentacao]);
        if ($stmt->fetchColumn()) throw new DomainException('Esta movimentação já possui um estorno.');

        $stmt = $pdo->prepare('SELECT * FROM despesas WHERE id_movimentacao_fk = :id FOR UPDATE');
        $stmt->execute(['id' => $id_movimentacao]);
        $desp = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('SELECT * FROM receitas WHERE id_movimentacao_fk = :id FOR UPDATE');
        $stmt->execute(['id' => $id_movimentacao]);
        $rec = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('SELECT * FROM transferencias WHERE id_movimentacao_fk = :id FOR UPDATE');
        $stmt->execute(['id' => $id_movimentacao]);
        $transf = $stmt->fetch(PDO::FETCH_ASSOC);
        if ((int) (bool) $desp + (int) (bool) $rec + (int) (bool) $transf !== 1) {
            throw new DomainException('Esta movimentação antiga não possui um único registro financeiro vinculado. Nenhum saldo foi alterado; é necessária conferência dos dados.');
        }
        $registro = $desp ?: ($rec ?: $transf);
        $centavos = valorEmCentavos((string) $registro['valor']);
        $valor = valorParaBanco($centavos);
        $tipo = $desp ? 'DESPESA' : ($rec ? 'RECEITA' : $transf['tipo']);
        if ($registro['status'] !== 'ATIVA' || $mov['tipo'] !== $tipo || valorEmCentavos((string) $mov['valor']) !== $centavos) {
            throw new DomainException('A movimentação e o registro financeiro estão inconsistentes. O estorno não foi aplicado.');
        }

        if ($desp) {
            $setor = buscarSetorPorId((int) $desp['id_setor_fk'], true);
            if (!$setor) throw new DomainException('O setor da despesa não foi encontrado.');
            if ($desp['id_compromisso_fk']) {
                $stmt = $pdo->prepare("SELECT id_despesa FROM despesas WHERE id_compromisso_fk = :id AND status = 'ATIVA' AND id_despesa <> :desp FOR UPDATE");
                $stmt->execute(['id' => $desp['id_compromisso_fk'], 'desp' => $desp['id_despesa']]);
                if ($stmt->fetchColumn()) throw new DomainException('O compromisso possui pagamentos duplicados. Confira os dados antes do estorno.');
                $pdo->prepare("UPDATE compromissos SET status = CASE WHEN vencimento < CURDATE() THEN 'ATRASADO' ELSE 'PENDENTE' END WHERE id_compromisso = :id")
                    ->execute(['id' => $desp['id_compromisso_fk']]);
            }
            validarLimiteSaldo(valorEmCentavos((string) $setor['saldo_atual'], true) + $centavos);
            $pdo->prepare('UPDATE setores SET saldo_atual = saldo_atual + :val WHERE id_setor = :id')
                ->execute(['val' => $valor, 'id' => $desp['id_setor_fk']]);
            $pdo->prepare("UPDATE despesas SET status = 'ESTORNADA' WHERE id_despesa = :id")->execute(['id' => $desp['id_despesa']]);
        } elseif ($rec) {
            if ($saldo_geral < $centavos) throw new DomainException('Saldo insuficiente. Transação não efetuada.');
            if ($rec['id_conta_receber_fk']) {
                $stmt = $pdo->prepare("SELECT id_receita FROM receitas WHERE id_conta_receber_fk = :id AND status = 'ATIVA' AND id_receita <> :rec FOR UPDATE");
                $stmt->execute(['id' => $rec['id_conta_receber_fk'], 'rec' => $rec['id_receita']]);
                if ($stmt->fetchColumn()) throw new DomainException('A conta possui recebimentos duplicados. Confira os dados antes do estorno.');
                $pdo->prepare("UPDATE contas_receber SET status = CASE WHEN vencimento < CURDATE() THEN 'ATRASADO' ELSE 'PENDENTE' END WHERE id_conta_receber = :id")
                    ->execute(['id' => $rec['id_conta_receber_fk']]);
            }
            $pdo->prepare('UPDATE saldo_geral SET saldo_atual = saldo_atual - :val WHERE id_saldo_geral = 1')->execute(['val' => $valor]);
            $pdo->prepare("UPDATE receitas SET status = 'ESTORNADA' WHERE id_receita = :id")->execute(['id' => $rec['id_receita']]);
        } else {
            $origem = $transf['id_setor_origem_fk'] === null ? null : (int) $transf['id_setor_origem_fk'];
            $destino = $transf['id_setor_destino_fk'] === null ? null : (int) $transf['id_setor_destino_fk'];
            if (($tipo === 'DISTRIBUICAO' && ($origem !== null || $destino === null))
                || ($tipo === 'REALOCACAO' && ($origem === null || $origem === $destino))) {
                throw new DomainException('A origem ou o destino da transferência estão inconsistentes.');
            }
            if ($destino !== null) {
                $setor_destino = buscarSetorPorId($destino, true);
                if (!$setor_destino || valorEmCentavos((string) $setor_destino['saldo_atual'], true) < $centavos) throw new DomainException('Saldo insuficiente. Transação não efetuada.');
            } elseif ($saldo_geral < $centavos) {
                throw new DomainException('Saldo insuficiente. Transação não efetuada.');
            }
            $setor_origem = $origem === null ? null : buscarSetorPorId($origem, true);
            if ($origem !== null && !$setor_origem) throw new DomainException('Setor de origem não encontrado.');
            validarLimiteSaldo(($origem === null ? $saldo_geral : valorEmCentavos((string) $setor_origem['saldo_atual'], true)) + $centavos);

            // Retira de quem recebeu e devolve a quem enviou, inclusive o Saldo Geral.
            if ($destino === null) $pdo->prepare('UPDATE saldo_geral SET saldo_atual = saldo_atual - :val WHERE id_saldo_geral = 1')->execute(['val' => $valor]);
            else $pdo->prepare('UPDATE setores SET saldo_atual = saldo_atual - :val WHERE id_setor = :id')->execute(['val' => $valor, 'id' => $destino]);
            if ($origem === null) $pdo->prepare('UPDATE saldo_geral SET saldo_atual = saldo_atual + :val WHERE id_saldo_geral = 1')->execute(['val' => $valor]);
            else $pdo->prepare('UPDATE setores SET saldo_atual = saldo_atual + :val WHERE id_setor = :id')->execute(['val' => $valor, 'id' => $origem]);
            $pdo->prepare("UPDATE transferencias SET status = 'ESTORNADA' WHERE id_transferencia = :id")->execute(['id' => $transf['id_transferencia']]);
        }

        $pdo->prepare("UPDATE movimentacoes SET status = 'ESTORNADA' WHERE id_movimentacao = :id")
            ->execute(['id' => $id_movimentacao]);

        $descricao_estorno = mb_substr('ESTORNO: ' . $mov['descricao'], 0, 255, 'UTF-8');
        $stmtEstorno = $pdo->prepare("
            INSERT INTO movimentacoes 
            (tipo, valor, criado_em, descricao, status, id_admin_fk, id_movimentacao_origem_fk) 
            VALUES 
            ('ESTORNO', :valor, NOW(), :descricao, 'ATIVA', :id_admin, :id_origem)
        ");
        $stmtEstorno->execute([
            'valor' => $mov['valor'],
            'descricao' => $descricao_estorno,
            'id_admin' => $id_admin_logado,
            'id_origem' => $id_movimentacao
        ]);

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
