<?php
require_once __DIR__ . '/base.php';

$acoes = ['atualizar_perfil', 'ativar_setor', 'categoria_salvar', 'categoria_status', 'editar_registro', 'estornar_movimentacao', 'excluir_setor', 'quitar_pendencia',
    'realocar_saldo', 'recolher_saldo', 'salvar_lancamento_completo', 'salvar_transacao', 'setor_novo', 'setor_renomear', 'transferir_entre_setores'];
$paginas = ['pages/dashboard.php', 'pages/historico.php', 'pages/setores/detalhes.php?id=1',
    'pages/categorias.php', 'pages/perfil/perfil.php', 'pages/perfil/cadastro.php'];
$inicio = estadoBanco();
foreach ($paginas as $pagina) {
    $r = requisicao($pagina);
    $primeiroCookie ??= $r['headers'];
    verificar($r['status'] === 302 && str_contains($r['location'], 'login.php'), 'página protegida: ' . $pagina);
}
foreach (array_map(fn($a) => 'actions/' . $a . '.php', $acoes) as $acao) {
    $r = requisicao($acao, ['nome' => 'Anônimo', 'email' => 'anonimo@teste.local', 'senha' => '123']);
    verificar($r['status'] === 302 && str_contains($r['location'], 'login.php'), 'POST anônimo bloqueado: ' . $acao);
}
verificar($inicio === estadoBanco(), 'acessos anônimos não alteraram o banco');
verificar(requisicao('pages/autentificacao/login.php', ['email' => 'admin@gmail.com', 'senha' => '123'])['status'] === 403, 'login sem CSRF rejeitado');

// Inclui uma conta antiga em texto puro e uma que já possui hash.
$hash = password_hash('senha-do-teste', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO administradores (nome, email, senha) VALUES (?, ?, ?)');
$stmt->execute(['Legado', 'legado@teste.local', 'senha-do-teste']);
$legado = (int) $pdo->lastInsertId();
$stmt->execute(['Hash', 'hash@teste.local', $hash]);
$idHash = (int) $pdo->lastInsertId();
$antes = estadoBanco();
$comando = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../scripts/migrar_senhas.php');
exec($comando, $saida, $codigo);
verificar($codigo === 0 && $antes === estadoBanco(), 'simulação da migração não escreve');
exec($comando . ' --aplicar', $saida, $codigo);
$senhas = $pdo->query('SELECT id_admin, senha FROM administradores')->fetchAll(PDO::FETCH_KEY_PAIR);
verificar($codigo === 0 && password_verify('senha-do-teste', $senhas[$legado]), 'senha antiga migrada');
verificar($senhas[$idHash] === $hash, 'hash anterior preservado');
$migrado = estadoBanco();
exec($comando . ' --aplicar', $saida, $codigo);
verificar($codigo === 0 && $migrado === estadoBanco(), 'segunda migração não altera hashes');
verificar(password_verify('123', $senhas[1]), 'senha inicial do SQL possui hash válido');

$r = requisicao('pages/autentificacao/login.php');
verificar(stripos($primeiroCookie, 'httponly') !== false && stripos($primeiroCookie, 'samesite=Lax') !== false, 'cookie protegido');
$token = csrf('pages/autentificacao/login.php');
$r = requisicao('pages/autentificacao/login.php', ['csrf' => $token, 'email' => "' OR 1=1 --", 'senha' => '123']);
verificar($r['status'] === 200 && str_contains($r['html'], 'incorretos'), 'SQL Injection não autentica');
$r = requisicao('pages/autentificacao/login.php', ['csrf' => $token, 'email' => 'admin@gmail.com', 'senha' => 'errada']);
verificar($r['status'] === 200 && str_contains($r['html'], 'incorretos'), 'senha incorreta rejeitada');
$r = requisicao('pages/autentificacao/login.php', ['csrf' => $token, 'email' => 'admin@gmail.com', 'senha' => '123']);
verificar($r['status'] === 302 && str_contains($r['location'], 'dashboard.php'), 'login válido');
verificar(str_contains($r['headers'], 'Set-Cookie:'), 'sessão regenerada no login');
$token = csrf();

foreach (['pages/perfil/cadastro.php'] as $cadastro) {
    verificar(requisicao($cadastro, ['nome' => 'Sem token', 'email' => 'sem-token@teste.local', 'senha' => '123'])['status'] === 403, 'cadastro sem CSRF: ' . $cadastro);
}
verificar(requisicao('pages/autentificacao/logout.php', ['csrf' => 'invalido'])['status'] === 403, 'logout com CSRF inválido rejeitado');

foreach ($paginas as $pagina) {
    $r = requisicao($pagina);
    verificar($r['status'] === 200, 'página acessível após login: ' . $pagina);
    verificar(!preg_match('/Warning:|Fatal error:|SQLSTATE|Stack trace/', $r['html']), 'sem erro técnico: ' . $pagina);
}
$antes = estadoBanco();
foreach ($acoes as $acao) {
    verificar(requisicao('actions/' . $acao . '.php')['status'] === 405, 'GET não executa: ' . $acao);
    verificar(requisicao('actions/' . $acao . '.php', [])['status'] === 403, 'sem CSRF: ' . $acao);
    verificar(requisicao('actions/' . $acao . '.php', ['csrf' => 'invalido'])['status'] === 403, 'CSRF inválido: ' . $acao);
}
verificar($antes === estadoBanco(), 'requisições rejeitadas não alteraram o banco');
verificar(requisicao('actions/setor_novo.php', ['csrf' => $token, 'nome_setor' => ['array']])['status'] === 400, 'entrada estruturada rejeitada');

$r = requisicao('pages/perfil/cadastro.php', ['csrf' => $token, 'nome' => 'Aluno', 'email' => 'aluno@teste.local', 'senha' => 'senha-aluno', 'confirmar_senha' => 'senha-aluno']);
$aluno = $pdo->query("SELECT * FROM administradores WHERE email = 'aluno@teste.local'")->fetch(PDO::FETCH_ASSOC);
verificar($r['status'] === 200 && $aluno && password_verify('senha-aluno', $aluno['senha']), 'cadastro com hash');
$idAluno = (int) $aluno['id_admin'];
requisicao('pages/autentificacao/logout.php', ['csrf' => $token]);
$token = csrf('pages/autentificacao/login.php');
$r = requisicao('pages/autentificacao/login.php', ['csrf' => $token, 'email' => 'aluno@teste.local', 'senha' => 'senha-aluno']);
verificar($r['status'] === 302, 'novo administrador consegue entrar');
$token = csrf();
$r = requisicao('actions/atualizar_perfil.php', ['csrf' => $token, 'nome' => 'Aluno atualizado', 'email' => 'aluno@teste.local', 'nova_senha' => 'nova-senha', 'confirmar_senha' => 'nova-senha']);
$aluno = $pdo->query('SELECT * FROM administradores WHERE id_admin = ' . $idAluno)->fetch(PDO::FETCH_ASSOC);
verificar($r['status'] === 302 && $aluno['nome'] === 'Aluno atualizado' && password_verify('nova-senha', $aluno['senha']), 'perfil e senha atualizados');
$antes = estadoBanco();
requisicao('actions/atualizar_perfil.php', ['csrf' => $token, 'nome' => 'Não salvar', 'email' => 'aluno@teste.local', 'nova_senha' => 'outra', 'confirmar_senha' => 'diferente']);
verificar($antes === estadoBanco(), 'confirmação de senha diferente não altera perfil');

$r = requisicao('actions/setor_novo.php', ['csrf' => $token, 'nome_setor' => 'Teste retorno', 'redirect_to' => 'https://example.com']);
verificar($r['status'] === 302 && $r['location'] === '../pages/dashboard.php', 'redirecionamento externo recusado');
$payload = '<svg onload="alert(1)">';
requisicao('actions/setor_novo.php', ['csrf' => $token, 'nome_setor' => $payload]);
$r = requisicao('pages/dashboard.php');
verificar(!str_contains($r['html'], $payload) && str_contains($r['html'], '&lt;svg'), 'HTML armazenado escapado');
$r = requisicao('pages/setores/detalhes.php?id=1&busca=' . urlencode('" autofocus onfocus="alert(1)') . '&sort_rec_pend=invalido');
verificar($r['status'] === 200 && !str_contains($r['html'], 'value="" autofocus') && !str_contains($r['html'], 'Warning:'), 'filtros escapados e ordenação inválida controlada');
$r = requisicao('pages/erro.php?link=' . urlencode('javascript:alert(1)') . '&msg=' . urlencode($payload));
verificar(!str_contains($r['html'], 'href="javascript:') && !str_contains($r['html'], $payload), 'página de erro segura');

// Regressão de operações que já eram válidas; não testa as correções da etapa 2.
$geral = (float) $pdo->query('SELECT saldo_atual FROM saldo_geral')->fetchColumn();
$lancamento = ['csrf' => $token, 'tipo_operacao' => 'receita', 'status_pagamento' => 'efetivado', 'descricao' => 'Receita de teste',
    'valor' => '25', 'id_setor' => '1', 'id_categoria' => '1', 'metodo_pagamento' => 'PIX',
    'data_lancamento' => '2026-10-01', 'data_vencimento' => '2026-10-04'];
$r = requisicao('actions/salvar_lancamento_completo.php', $lancamento);
verificar($r['status'] === 302 && (float) $pdo->query('SELECT saldo_atual FROM saldo_geral')->fetchColumn() === $geral + 25, 'receita válida preservada');
verificar((int) $pdo->query('SELECT id_admin_fk FROM movimentacoes ORDER BY id_movimentacao DESC LIMIT 1')->fetchColumn() === $idAluno, 'autor correto, sem fallback para administrador 1');
$setor = (float) $pdo->query('SELECT saldo_atual FROM setores WHERE id_setor = 1')->fetchColumn();
$lancamento['tipo_operacao'] = 'despesa'; $lancamento['id_categoria'] = '4'; $lancamento['valor'] = '10';
$r = requisicao('actions/salvar_lancamento_completo.php', $lancamento);
verificar($r['status'] === 302 && (float) $pdo->query('SELECT saldo_atual FROM setores WHERE id_setor = 1')->fetchColumn() === $setor - 10, 'despesa válida preservada');
$mov = $pdo->query('SELECT MAX(id_movimentacao) FROM movimentacoes')->fetchColumn();
requisicao('actions/estornar_movimentacao.php', ['csrf' => $token, 'id_movimentacao' => $mov]);
verificar((float) $pdo->query('SELECT saldo_atual FROM setores WHERE id_setor = 1')->fetchColumn() === $setor, 'estorno válido de despesa preservado');
$lancamento['tipo_operacao'] = 'receita'; $lancamento['status_pagamento'] = 'pendente'; $lancamento['id_categoria'] = '1';
$antesSaldo = (float) $pdo->query('SELECT saldo_atual FROM saldo_geral')->fetchColumn();
requisicao('actions/salvar_lancamento_completo.php', $lancamento);
verificar((float) $pdo->query('SELECT saldo_atual FROM saldo_geral')->fetchColumn() === $antesSaldo, 'pendência não altera saldo');
$conta = $pdo->query('SELECT MAX(id_conta_receber) FROM contas_receber')->fetchColumn();
requisicao('actions/quitar_pendencia.php', ['csrf' => $token, 'tipo_quitacao' => 'receita', 'id_registro' => $conta, 'valor_final' => '10', 'data_pagamento' => '2026-10-04']);
verificar((float) $pdo->query('SELECT saldo_atual FROM saldo_geral')->fetchColumn() === $antesSaldo + 10, 'recebimento válido preservado');
$lancamento['is_parcelado'] = 'on'; $lancamento['qtd_parcelas'] = '0';
$antes = estadoBanco();
$r = requisicao('actions/salvar_lancamento_completo.php', $lancamento);
verificar($r['status'] === 302 && !str_contains($r['location'], 'DivisionByZero') && $antes === estadoBanco(), 'parcelamento zero rejeitado sem alteração parcial');
unset($lancamento['is_parcelado'], $lancamento['qtd_parcelas']);
$lancamento['status_pagamento'] = 'efetivado';
$lancamento['id_categoria'] = '999999';
$antes = estadoBanco();
$r = requisicao('actions/salvar_lancamento_completo.php', $lancamento);
verificar($r['status'] === 302 && !str_contains($r['location'], 'SQLSTATE') && $antes === estadoBanco(), 'categoria inexistente rejeitada e sem alteração parcial');

foreach (['config/conexao.php', 'crud/crud_administradores.php', 'scripts/migrar_senhas.php', 'tests/base.php',
    'banco_my_cash.sql', '.git/config', 'Documentos_atualizados/Documento-04-My-Cash-DRS-Final.docx',
    'backups/my_cash_antes_migracoes_20261004_171148.sql', 'pages/partial/sidebar.php'] as $interno) {
    verificar(requisicao($interno)['status'] === 403, 'Apache bloqueia: ' . $interno);
}
verificar(requisicao('pages/partial/sidebar.css')['status'] === 200, 'CSS da barra lateral permanece acessível');
verificar(requisicao('pages/assets/chart.umd.min.js')['status'] === 200, 'Chart.js local permanece acessível');
verificar(requisicao('pages/autentificacao/logout.php')['status'] === 405, 'logout não aceita GET');
verificar(requisicao('pages/dashboard.php')['status'] === 200, 'GET de logout não encerrou a sessão');
$pdo->exec('UPDATE administradores SET ativo = 0 WHERE id_admin = ' . $idAluno);
verificar(requisicao('pages/dashboard.php')['status'] === 302, 'desativação revoga sessão já aberta');
$pdo->exec('UPDATE administradores SET ativo = 1 WHERE id_admin = ' . $idAluno);
$token = csrf('pages/autentificacao/login.php');
verificar(requisicao('pages/autentificacao/login.php', ['csrf' => $token, 'email' => 'aluno@teste.local', 'senha' => 'senha-aluno'])['status'] === 200, 'senha antiga não autentica após troca');
verificar(requisicao('pages/autentificacao/login.php', ['csrf' => $token, 'email' => 'aluno@teste.local', 'senha' => 'nova-senha'])['status'] === 302, 'senha nova autentica após troca');
$token = csrf();
requisicao('pages/autentificacao/logout.php', ['csrf' => $token]);
verificar(requisicao('pages/dashboard.php')['status'] === 302, 'logout bloqueia acesso posterior');
$pdo->exec('UPDATE administradores SET ativo = 0 WHERE id_admin = ' . $idAluno);
$token = csrf('pages/autentificacao/login.php');
$r = requisicao('pages/autentificacao/login.php', ['csrf' => $token, 'email' => 'aluno@teste.local', 'senha' => 'nova-senha']);
verificar($r['status'] === 200 && str_contains($r['html'], 'incorretos'), 'administrador inativo não entra');
echo "TOTAL: $testes testes; $falhas falha(s).\n";
curl_close($curl);
exit($falhas ? 1 : 0);
