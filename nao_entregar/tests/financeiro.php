<?php
// Testes auxiliares: só executam no banco isolado validado por base.php.
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/../crud/crud_setores.php';

function consultaTeste(string $sql, array $dados = []): mixed {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dados);
    return $stmt->fetchColumn();
}

function saldosTeste(): array {
    global $pdo;
    $saldos = ['geral' => (string) consultaTeste('SELECT saldo_atual FROM saldo_geral WHERE id_saldo_geral = 1')];
    $stmt = $pdo->prepare('SELECT id_setor, saldo_atual FROM setores ORDER BY id_setor');
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $setor) $saldos[(int) $setor['id_setor']] = $setor['saldo_atual'];
    return $saldos;
}

function formularioTeste(): array {
    $r = requisicao('pages/dashboard.php');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $r['html'], $csrf);
    preg_match('/name="envio" value="([a-f0-9]+)"/', $r['html'], $envio);
    if (!isset($csrf[1], $envio[1])) throw new RuntimeException('Formulário financeiro indisponível.');
    return ['csrf' => $csrf[1], 'envio' => $envio[1]];
}

function enviarFinanceiro(string $acao, array $dados): array {
    $dados['csrf'] ??= csrf();
    return requisicao('actions/' . $acao . '.php', $dados);
}

function recusarFinanceiro(string $acao, array $dados, string $nome): array {
    $antes = estadoBanco();
    $r = enviarFinanceiro($acao, $dados);
    verificar($r['status'] === 302 && str_contains($r['location'], 'erro.php'), $nome . ': erro compreensível');
    verificar($antes === estadoBanco(), $nome . ': banco preservado');
    return $r;
}

function lancamentoTeste(string $tipo, string $status, string $valor, int $setor = 1): array {
    return ['tipo_operacao' => $tipo, 'status_pagamento' => $status, 'descricao' => 'Teste financeiro',
        'valor' => $valor, 'id_setor' => (string) $setor, 'id_categoria' => $tipo === 'receita' ? '1' : '4',
        'metodo_pagamento' => 'PIX', 'data_lancamento' => date('Y-m-d'), 'data_vencimento' => date('Y-m-d')];
}

function novoSetorTeste(string $nome): int {
    global $pdo;
    $pdo->prepare('INSERT INTO setores (nome) VALUES (?)')->execute([$nome]);
    return (int) $pdo->lastInsertId();
}

function ultimoMovimentoTeste(): int {
    return (int) consultaTeste('SELECT MAX(id_movimentacao) FROM movimentacoes');
}

// Clientes separados permitem testar dois administradores/sessões ao mesmo tempo.
function httpClienteTeste(CurlHandle $cliente, string $pagina, ?array $dados = null): array {
    global $base;
    curl_setopt($cliente, CURLOPT_URL, $base . '/' . $pagina);
    curl_setopt($cliente, CURLOPT_POST, $dados !== null);
    if ($dados !== null) curl_setopt($cliente, CURLOPT_POSTFIELDS, http_build_query($dados));
    $resposta = curl_exec($cliente);
    if ($resposta === false) throw new RuntimeException(curl_error($cliente));
    $tamanho = curl_getinfo($cliente, CURLINFO_HEADER_SIZE);
    return ['html' => substr($resposta, $tamanho), 'headers' => substr($resposta, 0, $tamanho)];
}

function clienteFinanceiroTeste(): CurlHandle {
    $cliente = curl_init();
    curl_setopt_array($cliente, [CURLOPT_COOKIEFILE => '', CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
    $r = httpClienteTeste($cliente, 'pages/autentificacao/login.php');
    preg_match('/name="csrf" value="([^"]+)"/', $r['html'], $token);
    $r = httpClienteTeste($cliente, 'pages/autentificacao/login.php', ['csrf' => $token[1], 'email' => 'admin@gmail.com', 'senha' => '123']);
    if (!str_contains($r['headers'], '302')) throw new RuntimeException('Login do cliente simultâneo falhou.');
    return $cliente;
}

function simultaneosTeste(array $clientes, string $acao, array $dados): array {
    global $base;
    $multi = curl_multi_init();
    foreach ($clientes as $cliente) {
        $r = httpClienteTeste($cliente, 'pages/dashboard.php');
        preg_match('/name="csrf" value="([^"]+)"/', $r['html'], $csrf);
        preg_match('/name="envio" value="([^"]+)"/', $r['html'], $envio);
        curl_setopt_array($cliente, [CURLOPT_URL => $base . '/actions/' . $acao . '.php',
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($dados + ['csrf' => $csrf[1], 'envio' => $envio[1]])]);
        curl_multi_add_handle($multi, $cliente);
    }
    do {
        $codigo = curl_multi_exec($multi, $ativos);
        if ($ativos) curl_multi_select($multi, 0.05);
    } while ($ativos && $codigo === CURLM_OK);
    $resultados = [];
    foreach ($clientes as $cliente) {
        $resposta = curl_multi_getcontent($cliente);
        preg_match('/^Location:\s*(.+)$/mi', $resposta, $location);
        $resultados[] = ['status' => curl_getinfo($cliente, CURLINFO_HTTP_CODE), 'location' => trim($location[1] ?? '')];
        curl_multi_remove_handle($multi, $cliente);
    }
    curl_multi_close($multi);
    return $resultados;
}

$token = csrf('pages/autentificacao/login.php');
$r = requisicao('pages/autentificacao/login.php', ['csrf' => $token, 'email' => 'admin@gmail.com', 'senha' => '123']);
verificar($r['status'] === 302, 'login para testes financeiros');

// Reproduz o esquema anterior apenas na cópia isolada, sem mexer em dados.
$pdo->exec('ALTER TABLE transferencias MODIFY id_setor_destino_fk INT UNSIGNED NOT NULL');
$r = recusarFinanceiro('recolher_saldo', ['id_setor_origem' => '1', 'valor_recolhimento' => '1', 'descricao_recolhimento' => 'Antes da migração'], 'recolhimento sem migração');
verificar(str_contains(urldecode($r['location']), 'migração'), 'dependência de migração informada');
$antes = estadoBanco();
$migracao = __DIR__ . '/../scripts/migrar_transferencias.php';
if (is_file($migracao)) {
    $comando = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($migracao);
    exec($comando, $saida, $codigo);
    verificar($codigo === 0 && consultaTeste("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transferencias' AND COLUMN_NAME = 'id_setor_destino_fk'") === 'NO', 'simulação não modifica esquema');
    exec($comando . ' --aplicar', $saida, $codigo);
    verificar($codigo === 0, 'migração aplicada na cópia isolada');
    exec($comando . ' --aplicar', $saida, $codigo);
    verificar($codigo === 0, 'migração repetida sem erro');
} else {
    // Prova do ajuste mínimo no banco de TESTE, não é migração do banco real.
    $pdo->exec('ALTER TABLE transferencias MODIFY id_setor_destino_fk INT UNSIGNED NULL');
    echo "Não testável: script de migração ainda não autorizado/criado.\n";
}
verificar($antes === estadoBanco(), 'ajuste de esquema preservou todos os registros');
verificar(consultaTeste("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transferencias' AND COLUMN_NAME = 'id_setor_destino_fk'") === 'YES', 'destino permite Saldo Geral');

$a = novoSetorTeste('Financeiro A');
$b = novoSetorTeste('Financeiro B');
$c = novoSetorTeste('Financeiro C');
$inativo = novoSetorTeste('Financeiro Inativo');
$pdo->prepare('UPDATE setores SET ativo = 0 WHERE id_setor = ?')->execute([$inativo]);
$pdo->prepare("INSERT INTO categorias (nome, tipo, ativo) VALUES ('Receita inativa de teste', 'RECEITA', 0)")->execute();
$categoriaInativa = (int) $pdo->lastInsertId();

$dados = lancamentoTeste('receita', 'efetivado', '100,01');
$dados['data_lancamento'] = '2026-10-01';
$dados['data_vencimento'] = '2026-10-15';
$dados['metodo_pagamento'] = 'BOLETO';
$antes = saldosTeste();
$r = enviarFinanceiro('salvar_lancamento_completo', $dados);
$idReceita = (int) consultaTeste('SELECT MAX(id_receita) FROM receitas');
$movReceita = ultimoMovimentoTeste();
verificar($r['status'] === 302 && !str_contains($r['location'], 'erro.php'), 'receita efetivada válida');
verificar(valorEmCentavos(saldosTeste()['geral'], true) === valorEmCentavos($antes['geral'], true) + 10001, 'receita entra apenas no Saldo Geral');
verificar(saldosTeste()[1] === $antes[1], 'receita não aumenta diretamente o setor');
verificar((string) consultaTeste('SELECT valor FROM receitas WHERE id_receita = ?', [$idReceita]) === '100.01', 'vírgula convertida sem perda de centavos');
verificar(consultaTeste('SELECT CONCAT(data, "|", vencimento, "|", metodo_pagamento) FROM receitas WHERE id_receita = ?', [$idReceita]) === '2026-10-01|2026-10-15|BOLETO', 'receita preserva data, vencimento e método');

$dados = lancamentoTeste('despesa', 'efetivado', '23.45');
$dados['data_lancamento'] = '2026-10-02';
$dados['data_vencimento'] = '2026-10-20';
$dados['metodo_pagamento'] = 'CARTAO_DEBITO';
$antes = saldosTeste();
enviarFinanceiro('salvar_lancamento_completo', $dados);
$idDespesa = (int) consultaTeste('SELECT MAX(id_despesa) FROM despesas');
$movDespesa = ultimoMovimentoTeste();
verificar(valorEmCentavos(saldosTeste()[1], true) === valorEmCentavos($antes[1], true) - 2345, 'despesa reduz o setor');
verificar(saldosTeste()['geral'] === $antes['geral'], 'despesa não reduz o Saldo Geral');
verificar(consultaTeste('SELECT CONCAT(data, "|", vencimento, "|", metodo_pagamento) FROM despesas WHERE id_despesa = ?', [$idDespesa]) === '2026-10-02|2026-10-20|CARTAO_DEBITO', 'despesa preserva data, vencimento e método');

// O mesmo formulário não pode cadastrar duas operações.
$formulario = formularioTeste();
$dados = lancamentoTeste('receita', 'efetivado', '1.23') + $formulario;
requisicao('actions/salvar_lancamento_completo.php', $dados, false);
$aposPrimeiro = estadoBanco();
$r = requisicao('actions/salvar_lancamento_completo.php', $dados, false);
verificar(str_contains($r['location'], 'erro.php') && $aposPrimeiro === estadoBanco(), 'envio repetido não duplica lançamento');
$r = requisicao('actions/salvar_lancamento_completo.php', lancamentoTeste('receita', 'efetivado', '1') + ['csrf' => csrf()], false);
verificar(str_contains($r['location'], 'erro.php') && $aposPrimeiro === estadoBanco(), 'cadastro sem número de envio bloqueado');

foreach (['0', '-1', '0.001', '1e3', 'abc', '1.234,56', '10000000000000'] as $valor) {
    recusarFinanceiro('salvar_lancamento_completo', lancamentoTeste('receita', 'efetivado', $valor), 'valor inválido ' . $valor);
}
foreach (['tipo_operacao' => 'invalido', 'status_pagamento' => 'invalido', 'metodo_pagamento' => 'invalido',
    'data_lancamento' => '2026-02-30', 'data_vencimento' => '2026-02-30', 'descricao' => '', 'id_categoria' => '999999', 'id_setor' => '999999'] as $campo => $valor) {
    $dados = lancamentoTeste('receita', 'efetivado', '10'); $dados[$campo] = $valor;
    recusarFinanceiro('salvar_lancamento_completo', $dados, 'campo inválido ' . $campo);
}
$dados = lancamentoTeste('despesa', 'efetivado', '10'); $dados['id_categoria'] = '1';
recusarFinanceiro('salvar_lancamento_completo', $dados, 'categoria de outro tipo');
$dados = lancamentoTeste('receita', 'efetivado', '10'); $dados['id_categoria'] = (string) $categoriaInativa;
recusarFinanceiro('salvar_lancamento_completo', $dados, 'categoria inativa');
recusarFinanceiro('salvar_lancamento_completo', lancamentoTeste('receita', 'efetivado', '10', $inativo), 'setor inativo');
$r = recusarFinanceiro('salvar_lancamento_completo', lancamentoTeste('despesa', 'efetivado', '999999'), 'despesa sem saldo');
verificar(str_contains(urldecode($r['location']), 'Saldo insuficiente. Transação não efetuada.'), 'mensagem de saldo do DRS');

// Parcelas: centavos exatos e fim de mês sem pular fevereiro.
$dados = lancamentoTeste('receita', 'pendente', '100.01');
$dados += ['is_parcelado' => 'on', 'qtd_parcelas' => '3'];
$dados['data_vencimento'] = '2027-01-31'; $dados['descricao'] = 'Parcelas exatas';
$antes = saldosTeste();
enviarFinanceiro('salvar_lancamento_completo', $dados);
$grupo = consultaTeste("SELECT codigo_parcelamento FROM contas_receber WHERE descricao LIKE 'Parcelas exatas%' ORDER BY id_conta_receber DESC LIMIT 1");
$stmt = $pdo->prepare('SELECT * FROM contas_receber WHERE codigo_parcelamento = ? ORDER BY numero_parcela');
$stmt->execute([$grupo]); $parcelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
verificar(count($parcelas) === 3, 'três parcelas criadas');
verificar(array_column($parcelas, 'valor') === ['33.33', '33.33', '33.35'], 'soma das parcelas preserva R$ 100,01');
verificar(array_column($parcelas, 'vencimento') === ['2027-01-31', '2027-02-28', '2027-03-31'], 'vencimentos de fim de mês');
verificar($antes === saldosTeste(), 'parcelas pendentes não mexem nos saldos');
$idParcela = (int) $parcelas[0]['id_conta_receber'];
enviarFinanceiro('editar_registro', ['tipo_registro' => 'conta_receber', 'id_registro' => (string) $idParcela,
    'id_setor' => '1', 'id_categoria' => '1', 'descricao' => 'Parcela editada', 'valor' => '34', 'data' => '2026-12-20',
    'vencimento' => '2027-01-31', 'metodo_pagamento' => 'BOLETO']);
verificar((int) consultaTeste('SELECT numero_parcela FROM contas_receber WHERE id_conta_receber = ?', [$idParcela]) === 1
    && (int) consultaTeste('SELECT total_parcelas FROM contas_receber WHERE id_conta_receber = ?', [$idParcela]) === 3
    && consultaTeste('SELECT codigo_parcelamento FROM contas_receber WHERE id_conta_receber = ?', [$idParcela]) === $grupo, 'edição preserva identificação da parcela');
foreach (['0', '-1', '121', 'abc'] as $quantidade) {
    $dados['qtd_parcelas'] = $quantidade;
    recusarFinanceiro('salvar_lancamento_completo', $dados, 'parcelas inválidas ' . $quantidade);
}
$dados['qtd_parcelas'] = '6'; $dados['valor'] = '0.04';
recusarFinanceiro('salvar_lancamento_completo', $dados, 'parcelas inferiores a um centavo');

// Distribuição, realocação e recolhimento não criam receitas/despesas.
$contagemReceitas = consultaTeste('SELECT COUNT(*) FROM receitas');
$contagemDespesas = consultaTeste('SELECT COUNT(*) FROM despesas');
$antes = saldosTeste();
enviarFinanceiro('realocar_saldo', ['id_setor_destino' => (string) $a, 'valor_transferencia' => '100', 'descricao_transferencia' => 'Orçamento']);
$movDistribuicao = ultimoMovimentoTeste();
verificar(saldosTeste()[$a] === '100.00' && valorEmCentavos(saldosTeste()['geral'], true) === valorEmCentavos($antes['geral'], true) - 10000, 'distribuição correta');
$antes = saldosTeste();
enviarFinanceiro('transferir_entre_setores', ['id_setor_origem' => (string) $a, 'id_setor_destino' => (string) $b,
    'valor_transferencia' => '20', 'descricao_transferencia' => 'Realocação']);
$movTransferencia = ultimoMovimentoTeste();
verificar(saldosTeste()[$a] === '80.00' && saldosTeste()[$b] === '20.00' && saldosTeste()['geral'] === $antes['geral'], 'transferência entre setores correta');
enviarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movTransferencia]);
verificar($antes === saldosTeste(), 'estorno devolve do destino para a origem');
$movEstorno = ultimoMovimentoTeste();
recusarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movTransferencia], 'estorno repetido');
recusarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movEstorno], 'estorno de estorno');
$antes = saldosTeste();
enviarFinanceiro('recolher_saldo', ['id_setor_origem' => (string) $a, 'valor_recolhimento' => '30', 'descricao_recolhimento' => 'Devolução']);
$movRecolhimento = ultimoMovimentoTeste();
verificar(saldosTeste()[$a] === '70.00' && valorEmCentavos(saldosTeste()['geral'], true) === valorEmCentavos($antes['geral'], true) + 3000, 'recolhimento devolve ao Saldo Geral');
verificar((int) consultaTeste('SELECT id_setor_origem_fk FROM transferencias WHERE id_movimentacao_fk = ?', [$movRecolhimento]) === $a
    && consultaTeste('SELECT id_setor_destino_fk FROM transferencias WHERE id_movimentacao_fk = ?', [$movRecolhimento]) === null, 'recolhimento possui origem e destino rastreáveis');
enviarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movRecolhimento]);
verificar($antes === saldosTeste(), 'estorno de recolhimento correto');
enviarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movDistribuicao]);
verificar(saldosTeste()[$a] === '0.00', 'estorno de distribuição correto');
verificar($contagemReceitas === consultaTeste('SELECT COUNT(*) FROM receitas') && $contagemDespesas === consultaTeste('SELECT COUNT(*) FROM despesas'), 'transferências não inflam receitas e despesas');

recusarFinanceiro('realocar_saldo', ['id_setor_destino' => (string) $a, 'valor_transferencia' => '999999', 'descricao_transferencia' => 'Sem saldo'], 'distribuição sem saldo');
recusarFinanceiro('realocar_saldo', ['id_setor_destino' => (string) $inativo, 'valor_transferencia' => '1', 'descricao_transferencia' => 'Inativo'], 'distribuição para inativo');
recusarFinanceiro('transferir_entre_setores', ['id_setor_origem' => (string) $a, 'id_setor_destino' => (string) $a, 'valor_transferencia' => '1', 'descricao_transferencia' => 'Mesmo setor'], 'transferência para a própria origem');
recusarFinanceiro('recolher_saldo', ['id_setor_origem' => (string) $a, 'valor_recolhimento' => '1', 'descricao_recolhimento' => 'Sem saldo'], 'recolhimento sem saldo');

// Edição usa o setor real e atualiza também o movimento correspondente.
$antes = saldosTeste();
enviarFinanceiro('editar_registro', ['tipo_registro' => 'despesa_efetivada', 'id_registro' => (string) $idDespesa,
    'id_setor' => (string) $b, 'id_categoria' => '4', 'descricao' => 'Despesa corrigida', 'valor' => '30', 'data' => date('Y-m-d')]);
verificar(valorEmCentavos(saldosTeste()[1], true) === valorEmCentavos($antes[1], true) - 655 && saldosTeste()[$b] === $antes[$b], 'edição ignora setor adulterado no formulário');
verificar(consultaTeste('SELECT valor FROM movimentacoes WHERE id_movimentacao = ?', [$movDespesa]) === '30.00'
    && str_contains((string) consultaTeste('SELECT descricao FROM movimentacoes WHERE id_movimentacao = ?', [$movDespesa]), 'Despesa corrigida'), 'histórico acompanha a edição');
enviarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movDespesa]);
verificar(valorEmCentavos(saldosTeste()[1], true) === valorEmCentavos($antes[1], true) + 2345, 'estorno da despesa editada devolve o valor correto');
$edicao = ['tipo_registro' => 'despesa_efetivada', 'id_registro' => (string) $idDespesa, 'id_setor' => '1',
    'id_categoria' => '4', 'descricao' => 'Não alterar', 'valor' => '50', 'data' => date('Y-m-d')];
recusarFinanceiro('editar_registro', $edicao, 'edição de registro estornado');
$edicao['id_registro'] = '999999';
recusarFinanceiro('editar_registro', $edicao, 'edição de registro inexistente');
$edicao['tipo_registro'] = 'invalido';
recusarFinanceiro('editar_registro', $edicao, 'edição de tipo inválido');
$antes = saldosTeste();
enviarFinanceiro('editar_registro', ['tipo_registro' => 'receita_efetivada', 'id_registro' => (string) $idReceita,
    'id_setor' => (string) $b, 'id_categoria' => '1', 'descricao' => 'Receita corrigida', 'valor' => '101.01', 'data' => date('Y-m-d')]);
verificar(valorEmCentavos(saldosTeste()['geral'], true) === valorEmCentavos($antes['geral'], true) + 100
    && consultaTeste('SELECT valor FROM movimentacoes WHERE id_movimentacao = ?', [$movReceita]) === '101.01', 'edição de receita ajusta saldo e histórico');

// Pendências são quitadas uma vez, e voltam ao status correto no estorno.
$dados = lancamentoTeste('receita', 'pendente', '15');
$dados['data_lancamento'] = '2025-12-20'; $dados['data_vencimento'] = '2026-01-01'; $dados['metodo_pagamento'] = 'BOLETO';
$antes = saldosTeste();
enviarFinanceiro('salvar_lancamento_completo', $dados);
$conta = (int) consultaTeste('SELECT MAX(id_conta_receber) FROM contas_receber');
verificar($antes === saldosTeste() && consultaTeste('SELECT status FROM contas_receber WHERE id_conta_receber = ?', [$conta]) === 'ATRASADO', 'conta vencida cadastrada sem alterar saldo');
$quitacao = ['tipo_quitacao' => 'receita', 'id_registro' => (string) $conta, 'valor_final' => '15', 'data_pagamento' => date('Y-m-d')];
enviarFinanceiro('quitar_pendencia', $quitacao);
$movPagamento = ultimoMovimentoTeste();
verificar(consultaTeste('SELECT status FROM contas_receber WHERE id_conta_receber = ?', [$conta]) === 'RECEBIDO', 'conta recebida');
verificar(consultaTeste('SELECT CONCAT(data, "|", vencimento, "|", metodo_pagamento) FROM receitas WHERE id_conta_receber_fk = ?', [$conta]) === date('Y-m-d') . '|2026-01-01|BOLETO', 'recebimento preserva vencimento e método da pendência');
recusarFinanceiro('quitar_pendencia', $quitacao, 'recebimento repetido');
enviarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movPagamento]);
verificar($antes === saldosTeste() && consultaTeste('SELECT status FROM contas_receber WHERE id_conta_receber = ?', [$conta]) === 'ATRASADO', 'estorno reabre conta vencida como atrasada');
enviarFinanceiro('quitar_pendencia', $quitacao);
verificar((int) consultaTeste("SELECT COUNT(*) FROM receitas WHERE id_conta_receber_fk = ? AND status = 'ATIVA'", [$conta]) === 1, 'novo recebimento após estorno mantém um único ativo');

$dados = lancamentoTeste('despesa', 'pendente', '12.34');
$dados['data_lancamento'] = '2025-12-20'; $dados['data_vencimento'] = '2026-01-01'; $dados['metodo_pagamento'] = 'TRANSFERENCIA';
enviarFinanceiro('salvar_lancamento_completo', $dados);
$compromisso = (int) consultaTeste('SELECT MAX(id_compromisso) FROM compromissos');
$antes = saldosTeste();
$quitacaoDespesa = ['tipo_quitacao' => 'despesa', 'id_registro' => (string) $compromisso, 'valor_final' => '12.34', 'data_pagamento' => date('Y-m-d')];
enviarFinanceiro('quitar_pendencia', $quitacaoDespesa);
$movPagamento = ultimoMovimentoTeste();
verificar(consultaTeste('SELECT status FROM compromissos WHERE id_compromisso = ?', [$compromisso]) === 'PAGO'
    && valorEmCentavos(saldosTeste()[1], true) === valorEmCentavos($antes[1], true) - 1234, 'compromisso pago reduz o setor');
verificar(consultaTeste('SELECT CONCAT(data, "|", vencimento, "|", metodo_pagamento) FROM despesas WHERE id_compromisso_fk = ?', [$compromisso]) === date('Y-m-d') . '|2026-01-01|TRANSFERENCIA', 'pagamento preserva vencimento e método da pendência');
recusarFinanceiro('quitar_pendencia', $quitacaoDespesa, 'pagamento repetido');
enviarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movPagamento]);
verificar($antes === saldosTeste() && consultaTeste('SELECT status FROM compromissos WHERE id_compromisso = ?', [$compromisso]) === 'ATRASADO', 'estorno reabre compromisso vencido');
$quitacaoDespesa['data_pagamento'] = '2026-02-30';
recusarFinanceiro('quitar_pendencia', $quitacaoDespesa, 'pagamento com data inválida');
$quitacaoDespesa['data_pagamento'] = date('Y-m-d'); $quitacaoDespesa['valor_final'] = '999999';
recusarFinanceiro('quitar_pendencia', $quitacaoDespesa, 'pagamento sem saldo');

// Desativação não pode abandonar pendências nem saldo.
enviarFinanceiro('salvar_lancamento_completo', lancamentoTeste('receita', 'pendente', '10', $c));
$contaC = (int) consultaTeste('SELECT MAX(id_conta_receber) FROM contas_receber');
recusarFinanceiro('excluir_setor', ['id_setor' => (string) $c], 'setor com pendência');
enviarFinanceiro('quitar_pendencia', ['tipo_quitacao' => 'receita', 'id_registro' => (string) $contaC, 'valor_final' => '10', 'data_pagamento' => date('Y-m-d')]);
enviarFinanceiro('excluir_setor', ['id_setor' => (string) $c]);
verificar((int) consultaTeste('SELECT ativo FROM setores WHERE id_setor = ?', [$c]) === 0, 'setor sem saldo ou pendências desativado');
recusarFinanceiro('excluir_setor', ['id_setor' => '1'], 'setor com saldo');

// Falha SQL depois de alterar saldo/movimento precisa desfazer tudo.
$pdo->exec("CREATE TRIGGER teste_falha_despesa BEFORE INSERT ON despesas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falha técnica deliberada do teste'");
try {
    $r = recusarFinanceiro('salvar_lancamento_completo', lancamentoTeste('despesa', 'efetivado', '1'), 'falha SQL no meio do lançamento');
    verificar(!str_contains(urldecode($r['location']), 'deliberada') && !str_contains($r['location'], 'SQLSTATE'), 'falha SQL não expõe detalhes');
} finally {
    $pdo->exec('DROP TRIGGER teste_falha_despesa');
}
$pdo->exec("CREATE TRIGGER teste_falha_parcela BEFORE INSERT ON compromissos FOR EACH ROW BEGIN IF NEW.descricao LIKE '%Parc. 2/%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falha na segunda parcela'; END IF; END");
try {
    $dados = lancamentoTeste('despesa', 'efetivado', '9'); $dados += ['is_parcelado' => 'on', 'qtd_parcelas' => '3'];
    recusarFinanceiro('salvar_lancamento_completo', $dados, 'falha na segunda parcela desfaz inclusive a primeira');
} finally {
    $pdo->exec('DROP TRIGGER teste_falha_parcela');
}


// Reversões não podem retirar dinheiro que já foi usado no destino.
$extra = novoSetorTeste('Destino de estorno');
enviarFinanceiro('realocar_saldo', ['id_setor_destino' => (string) $extra, 'valor_transferencia' => '10', 'descricao_transferencia' => 'Distribuição para testar estorno']);
$movSemSaldo = ultimoMovimentoTeste();
enviarFinanceiro('salvar_lancamento_completo', lancamentoTeste('despesa', 'efetivado', '10', $extra));
recusarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movSemSaldo], 'estorno de distribuição sem saldo no destino');

enviarFinanceiro('realocar_saldo', ['id_setor_destino' => (string) $a, 'valor_transferencia' => '10', 'descricao_transferencia' => 'Origem de teste']);
enviarFinanceiro('transferir_entre_setores', ['id_setor_origem' => (string) $a, 'id_setor_destino' => (string) $b, 'valor_transferencia' => '10', 'descricao_transferencia' => 'Transferência consumida']);
$movSemSaldo = ultimoMovimentoTeste();
enviarFinanceiro('salvar_lancamento_completo', lancamentoTeste('despesa', 'efetivado', '10', $b));
recusarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movSemSaldo], 'estorno de realocação sem saldo no destino');

// Deixa o Saldo Geral zerado usando uma distribuição válida, não um ajuste manual.
$totalGeral = saldosTeste()['geral'];
enviarFinanceiro('realocar_saldo', ['id_setor_destino' => (string) $extra, 'valor_transferencia' => $totalGeral, 'descricao_transferencia' => 'Recursos já distribuídos']);
$movEsvaziouGeral = ultimoMovimentoTeste();
verificar(saldosTeste()['geral'] === '0.00', 'Saldo Geral zerado por distribuição');
recusarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movReceita], 'estorno de receita sem saldo geral');
recusarFinanceiro('editar_registro', ['tipo_registro' => 'receita_efetivada', 'id_registro' => (string) $idReceita,
    'id_setor' => '1', 'id_categoria' => '1', 'descricao' => 'Não reduzir', 'valor' => '1', 'data' => date('Y-m-d')], 'redução de receita sem saldo geral');
enviarFinanceiro('recolher_saldo', ['id_setor_origem' => (string) $extra, 'valor_recolhimento' => '1', 'descricao_recolhimento' => 'Recolhimento consumido']);
$movRecolhimentoConsumido = ultimoMovimentoTeste();
enviarFinanceiro('realocar_saldo', ['id_setor_destino' => (string) $extra, 'valor_transferencia' => '1', 'descricao_transferencia' => 'Consumo do recolhimento']);
recusarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movRecolhimentoConsumido], 'estorno de recolhimento sem saldo geral');
enviarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movEsvaziouGeral]);

// Regressão do controlador simples ainda existente.
$simples = ['tipo_operacao' => 'receita', 'status_pagamento' => 'efetivado', 'descricao' => 'Receita pelo controlador simples',
    'valor' => '2.22', 'id_setor' => '1', 'data' => date('Y-m-d')];
$antes = saldosTeste();
$r = enviarFinanceiro('salvar_transacao', $simples);
verificar(!str_contains($r['location'], 'erro.php') && valorEmCentavos(saldosTeste()['geral'], true) === valorEmCentavos($antes['geral'], true) + 222, 'controlador simples preserva receita');
$simples['tipo_operacao'] = 'despesa'; $simples['valor'] = '1.11';
$antes = saldosTeste();
enviarFinanceiro('salvar_transacao', $simples);
verificar(valorEmCentavos(saldosTeste()[1], true) === valorEmCentavos($antes[1], true) - 111, 'controlador simples preserva despesa');
$simples['status_pagamento'] = 'pendente';
$antes = saldosTeste();
enviarFinanceiro('salvar_transacao', $simples);
verificar($antes === saldosTeste(), 'controlador simples preserva pendência');
$simples['tipo_operacao'] = 'invalido';
recusarFinanceiro('salvar_transacao', $simples, 'controlador simples rejeita tipo inválido');

// Cadastro com uma parcela já efetivada preserva o total contratado.
$dados = lancamentoTeste('receita', 'efetivado', '10.01');
$dados += ['is_parcelado' => 'on', 'qtd_parcelas' => '3'];
$dados['descricao'] = 'Parcelas com entrada';
$antes = saldosTeste();
enviarFinanceiro('salvar_lancamento_completo', $dados);
verificar(valorEmCentavos(saldosTeste()['geral'], true) === valorEmCentavos($antes['geral'], true) + 333, 'somente a primeira parcela efetivada aumenta saldo');
verificar(consultaTeste("SELECT SUM(valor) FROM contas_receber WHERE descricao LIKE 'Parcelas com entrada%'") === '6.68', 'parcelas restantes completam o total exato');
recusarFinanceiro('editar_registro', ['tipo_registro' => 'despesa_efetivada', 'id_registro' => '1', 'id_setor' => (string) $b,
    'id_categoria' => '4', 'descricao' => 'Aumento sem saldo', 'valor' => '999999', 'data' => date('Y-m-d')], 'aumento de despesa sem saldo');

// Dados legados sem vínculo não são inventados nem alterados automaticamente.
$pdo->prepare("INSERT INTO movimentacoes (tipo, valor, descricao, id_admin_fk) VALUES ('RECEITA', 1, 'Recolhimento antigo sem origem', 1)")->execute();
$movAntigo = (int) $pdo->lastInsertId();
recusarFinanceiro('estornar_movimentacao', ['id_movimentacao' => (string) $movAntigo], 'movimento legado sem vínculo');
$pdo->prepare('DELETE FROM movimentacoes WHERE id_movimentacao = ?')->execute([$movAntigo]);

// Duas sessões reais, enviadas em paralelo pelo curl_multi.
$concorrente = novoSetorTeste('Setor Concorrente');
enviarFinanceiro('realocar_saldo', ['id_setor_destino' => (string) $concorrente, 'valor_transferencia' => '30', 'descricao_transferencia' => 'Concorrência']);
$clientes = [clienteFinanceiroTeste(), clienteFinanceiroTeste()];
$r = simultaneosTeste($clientes, 'salvar_lancamento_completo', lancamentoTeste('despesa', 'efetivado', '20', $concorrente));
$sucessos = count(array_filter($r, fn($x) => $x['status'] === 302 && !str_contains($x['location'], 'erro.php')));
verificar($sucessos === 1 && saldosTeste()[$concorrente] === '10.00', 'despesas simultâneas não gastam o mesmo saldo');
verificar((int) consultaTeste("SELECT COUNT(*) FROM despesas WHERE id_setor_fk = ? AND status = 'ATIVA'", [$concorrente]) === 1, 'uma despesa concorrente gravada');

enviarFinanceiro('salvar_lancamento_completo', lancamentoTeste('receita', 'pendente', '8'));
$contaConcorrente = (int) consultaTeste('SELECT MAX(id_conta_receber) FROM contas_receber');
$antes = saldosTeste();
$r = simultaneosTeste($clientes, 'quitar_pendencia', ['tipo_quitacao' => 'receita', 'id_registro' => (string) $contaConcorrente, 'valor_final' => '8', 'data_pagamento' => date('Y-m-d')]);
verificar(count(array_filter($r, fn($x) => !str_contains($x['location'], 'erro.php'))) === 1, 'somente um recebimento simultâneo concluído');
verificar(valorEmCentavos(saldosTeste()['geral'], true) === valorEmCentavos($antes['geral'], true) + 800
    && (int) consultaTeste("SELECT COUNT(*) FROM receitas WHERE id_conta_receber_fk = ? AND status = 'ATIVA'", [$contaConcorrente]) === 1, 'recebimento concorrente não duplica receita/saldo');
$movConcorrente = ultimoMovimentoTeste();
$r = simultaneosTeste($clientes, 'estornar_movimentacao', ['id_movimentacao' => (string) $movConcorrente]);
verificar(count(array_filter($r, fn($x) => !str_contains($x['location'], 'erro.php'))) === 1
    && (int) consultaTeste('SELECT COUNT(*) FROM movimentacoes WHERE id_movimentacao_origem_fk = ?', [$movConcorrente]) === 1, 'estorno simultâneo acontece uma única vez');

enviarFinanceiro('salvar_lancamento_completo', lancamentoTeste('despesa', 'pendente', '7'));
$compromissoConcorrente = (int) consultaTeste('SELECT MAX(id_compromisso) FROM compromissos');
$antes = saldosTeste();
$r = simultaneosTeste($clientes, 'quitar_pendencia', ['tipo_quitacao' => 'despesa', 'id_registro' => (string) $compromissoConcorrente, 'valor_final' => '7', 'data_pagamento' => date('Y-m-d')]);
verificar(count(array_filter($r, fn($x) => $x['status'] === 302 && !str_contains($x['location'], 'erro.php'))) === 1, 'somente um pagamento simultâneo concluído');
verificar(valorEmCentavos(saldosTeste()[1], true) === valorEmCentavos($antes[1], true) - 700
    && (int) consultaTeste("SELECT COUNT(*) FROM despesas WHERE id_compromisso_fk = ? AND status = 'ATIVA'", [$compromissoConcorrente]) === 1, 'pagamento concorrente não duplica despesa/saldo');

foreach ($clientes as $cliente) curl_close($cliente);


// Mesmo sem SQL estrito, um crédito maior que DECIMAL(15,2) é recusado antes de gravar.
$modoSql = (string) consultaTeste('SELECT @@GLOBAL.sql_mode');
$pdo->prepare('SET GLOBAL sql_mode = ?')->execute(['']);
try {
    recusarFinanceiro('salvar_lancamento_completo', lancamentoTeste('receita', 'efetivado', '9999999999999.99'), 'limite de saldo sem SQL estrito');
} finally {
    $pdo->prepare('SET GLOBAL sql_mode = ?')->execute([$modoSql]);
}

// Balanço global: dinheiro recebido = dinheiro disponível + dinheiro gasto.
$receitas = valorEmCentavos((string) consultaTeste("SELECT COALESCE(SUM(valor),0) FROM receitas WHERE status = 'ATIVA'"), true);
$despesas = valorEmCentavos((string) consultaTeste("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE status = 'ATIVA'"), true);
$disponivel = array_sum(array_map(fn($v) => valorEmCentavos((string) $v, true), saldosTeste()));
verificar($receitas === $despesas + $disponivel, 'saldo consolidado reconciliado com receitas e despesas ativas');
foreach (['receitas', 'despesas', 'transferencias'] as $tabela) {
    verificar((int) consultaTeste("SELECT COUNT(*) FROM $tabela t JOIN movimentacoes m ON t.id_movimentacao_fk = m.id_movimentacao WHERE t.valor <> m.valor OR t.status <> m.status") === 0, 'valores e status coerentes: ' . $tabela);
}
verificar((int) consultaTeste('SELECT COUNT(*) FROM setores WHERE saldo_atual < 0') === 0, 'nenhum setor com saldo negativo');
verificar((int) consultaTeste("SELECT COUNT(*) FROM (SELECT id_conta_receber_fk FROM receitas WHERE status = 'ATIVA' AND id_conta_receber_fk IS NOT NULL GROUP BY id_conta_receber_fk HAVING COUNT(*) > 1) AS duplicadas") === 0, 'nenhum recebimento ativo duplicado');
verificar((int) consultaTeste("SELECT COUNT(*) FROM (SELECT id_compromisso_fk FROM despesas WHERE status = 'ATIVA' AND id_compromisso_fk IS NOT NULL GROUP BY id_compromisso_fk HAVING COUNT(*) > 1) AS duplicadas") === 0, 'nenhum pagamento ativo duplicado');
$r = requisicao('pages/historico.php');
verificar($r['status'] === 200 && str_contains($r['html'], 'Estorno registrado'), 'histórico identifica estornos sem permitir desfazê-los');
foreach (['pages/dashboard.php', 'pages/setores/detalhes.php?id=1'] as $pagina) {
    $r = requisicao($pagina);
    verificar($r['status'] === 200 && !preg_match('/Warning:|Fatal error:|SQLSTATE/', $r['html']), 'tela preservada: ' . $pagina);
}
echo "TOTAL FINANCEIRO: $testes testes; $falhas falha(s).\n";
curl_close($curl);
exit($falhas ? 1 : 0);

