<?php
require_once __DIR__ . '/base.php';

$token = csrf('pages/autentificacao/login.php');
$resposta = requisicao('pages/autentificacao/login.php', [
    'csrf' => $token,
    'email' => 'admin@gmail.com',
    'senha' => '123',
]);
verificar($resposta['status'] === 302, 'login para os testes da etapa 3');
$token = csrf();

$resposta = requisicao('pages/categorias.php');
verificar($resposta['status'] === 200 && str_contains($resposta['html'], 'Nova categoria'), 'tela de categorias acessivel');
verificar(str_contains($resposta['html'], 'categoria_salvar.php') && str_contains($resposta['html'], 'categoria_status.php'), 'formularios de categoria disponiveis');

$nomeMalicioso = 'Categoria <svg onload=alert(1)>';
$resposta = requisicao('actions/categoria_salvar.php', [
    'csrf' => $token,
    'modo' => 'criar',
    'nome' => $nomeMalicioso,
    'tipo' => 'RECEITA',
]);
$stmt = $pdo->prepare('SELECT * FROM categorias WHERE nome = ? AND tipo = ?');
$stmt->execute([$nomeMalicioso, 'RECEITA']);
$categoria = $stmt->fetch(PDO::FETCH_ASSOC);
verificar($resposta['status'] === 302 && $categoria !== false, 'categoria criada');

$resposta = requisicao('pages/categorias.php');
verificar(!str_contains($resposta['html'], $nomeMalicioso) && str_contains($resposta['html'], '&lt;svg'), 'nome de categoria escapado contra XSS');

$idCategoria = (int) $categoria['id_categoria'];
$resposta = requisicao('actions/categoria_salvar.php', [
    'csrf' => $token,
    'modo' => 'editar',
    'id_categoria' => (string) $idCategoria,
    'nome' => 'Categoria Etapa 3',
    'tipo' => 'DESPESA',
]);
$categoria = $pdo->query('SELECT * FROM categorias WHERE id_categoria = ' . $idCategoria)->fetch(PDO::FETCH_ASSOC);
verificar($resposta['status'] === 302 && $categoria['nome'] === 'Categoria Etapa 3' && $categoria['tipo'] === 'DESPESA', 'categoria editada');

requisicao('actions/categoria_status.php', ['csrf' => $token, 'id_categoria' => (string) $idCategoria, 'status' => 'desativar']);
verificar((int) $pdo->query('SELECT ativo FROM categorias WHERE id_categoria = ' . $idCategoria)->fetchColumn() === 0, 'categoria desativada');
requisicao('actions/categoria_status.php', ['csrf' => $token, 'id_categoria' => (string) $idCategoria, 'status' => 'ativar']);
verificar((int) $pdo->query('SELECT ativo FROM categorias WHERE id_categoria = ' . $idCategoria)->fetchColumn() === 1, 'categoria reativada');

$antes = estadoBanco();
requisicao('actions/categoria_salvar.php', ['csrf' => $token, 'modo' => 'criar', 'nome' => 'Tipo invalido', 'tipo' => 'OUTRO']);
verificar($antes === estadoBanco(), 'tipo de categoria invalido nao altera o banco');
$antes = estadoBanco();
requisicao('actions/categoria_status.php', ['csrf' => $token, 'id_categoria' => (string) $idCategoria, 'status' => 'apagar']);
verificar($antes === estadoBanco(), 'status de categoria invalido nao altera o banco');

$descricaoMaliciosa = 'Setor <img src=x onerror=alert(1)>';
$resposta = requisicao('actions/setor_novo.php', [
    'csrf' => $token,
    'nome_setor' => 'Setor Etapa 3',
    'descricao_setor' => 'Descricao inicial',
]);
$stmt = $pdo->prepare('SELECT * FROM setores WHERE nome = ?');
$stmt->execute(['Setor Etapa 3']);
$setor = $stmt->fetch(PDO::FETCH_ASSOC);
verificar($resposta['status'] === 302 && $setor && $setor['descricao'] === 'Descricao inicial', 'setor criado com descricao');

$idSetor = (int) $setor['id_setor'];
$resposta = requisicao('actions/setor_renomear.php', [
    'csrf' => $token,
    'id_setor' => (string) $idSetor,
    'novo_nome' => 'Setor Etapa 3 Editado',
    'nova_descricao' => $descricaoMaliciosa,
]);
$setor = $pdo->query('SELECT * FROM setores WHERE id_setor = ' . $idSetor)->fetch(PDO::FETCH_ASSOC);
verificar($resposta['status'] === 302 && $setor['nome'] === 'Setor Etapa 3 Editado' && $setor['descricao'] === $descricaoMaliciosa, 'nome e descricao do setor editados');

requisicao('actions/excluir_setor.php', ['csrf' => $token, 'id_setor' => (string) $idSetor]);
verificar((int) $pdo->query('SELECT ativo FROM setores WHERE id_setor = ' . $idSetor)->fetchColumn() === 0, 'setor sem saldo e pendencias desativado');
$resposta = requisicao('pages/dashboard.php');
verificar(str_contains($resposta['html'], 'Setor Etapa 3 Editado') && str_contains($resposta['html'], 'ativar_setor.php'), 'setor inativo aparece com opcao de reativar');
requisicao('actions/ativar_setor.php', ['csrf' => $token, 'id_setor' => (string) $idSetor]);
verificar((int) $pdo->query('SELECT ativo FROM setores WHERE id_setor = ' . $idSetor)->fetchColumn() === 1, 'setor reativado');

$categoriaReceita = (int) $pdo->query("SELECT id_categoria FROM categorias WHERE tipo = 'RECEITA' AND ativo = 1 ORDER BY id_categoria LIMIT 1")->fetchColumn();
$ontem = $pdo->query("SELECT DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
$stmt = $pdo->prepare("INSERT INTO contas_receber (descricao, valor, data, vencimento, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk) VALUES (?, 17.50, CURDATE(), ?, 'BOLETO', 'PENDENTE', ?, ?, 1)");
$stmt->execute(['Conta vencida etapa 3', $ontem, $idSetor, $categoriaReceita]);
$idConta = (int) $pdo->lastInsertId();
$stmt = $pdo->prepare("INSERT INTO compromissos (descricao, valor, data, vencimento, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk) VALUES (?, 11.25, CURDATE(), ?, 'BOLETO', 'PENDENTE', ?, ?, 1)");
$stmt->execute(['Compromisso vencido etapa 3', $ontem, $idSetor, $idCategoria]);
$idCompromisso = (int) $pdo->lastInsertId();
$stmt = $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, descricao, status, id_admin_fk) VALUES ('RECEITA', 12.34, ?, 'ATIVA', 1)");
$stmt->execute(['Movimento visivel etapa 3']);

$resposta = requisicao('pages/dashboard.php');
verificar($resposta['status'] === 200, 'dashboard carrega depois das alteracoes');
verificar($pdo->query('SELECT status FROM contas_receber WHERE id_conta_receber = ' . $idConta)->fetchColumn() === 'ATRASADO', 'conta vencida atualizada automaticamente');
verificar($pdo->query('SELECT status FROM compromissos WHERE id_compromisso = ' . $idCompromisso)->fetchColumn() === 'ATRASADO', 'compromisso vencido atualizado automaticamente');
verificar(str_contains($resposta['html'], 'Recursos nos Setores') && str_contains($resposta['html'], 'Total Disponivel'), 'dashboard mostra saldos por setores e consolidado');
verificar(str_contains($resposta['html'], 'Recursos por Setor') && str_contains($resposta['html'], 'Setor Etapa 3 Editado'), 'dashboard lista recursos por setor');
verificar(str_contains($resposta['html'], 'Ultimas Movimentacoes') && str_contains($resposta['html'], 'Movimento visivel etapa 3'), 'dashboard mostra ultimas movimentacoes');
verificar(str_contains($resposta['html'], 'Conta vencida etapa 3') && str_contains($resposta['html'], 'Atrasada'), 'dashboard identifica conta atrasada');
verificar(str_contains($resposta['html'], 'Compromisso vencido etapa 3') && str_contains($resposta['html'], 'Atrasado'), 'dashboard identifica compromisso atrasado');
verificar(!preg_match('/Warning:|Fatal error:|SQLSTATE|Stack trace/', $resposta['html']), 'dashboard sem erro tecnico exposto');

$resposta = requisicao('pages/setores/detalhes.php?id=' . $idSetor);
verificar($resposta['status'] === 200 && str_contains($resposta['html'], 'setorChart'), 'detalhes do setor mostra grafico');
verificar(str_contains($resposta['html'], '../assets/chart.umd.min.js') && !str_contains($resposta['html'], 'cdn.jsdelivr.net/npm/chart.js'), 'grafico usa Chart.js local');
verificar(!str_contains($resposta['html'], $descricaoMaliciosa) && str_contains($resposta['html'], '&lt;img'), 'descricao do setor escapada contra XSS');
verificar(str_contains($resposta['html'], 'Conta vencida etapa 3') && str_contains($resposta['html'], 'Compromisso vencido etapa 3'), 'detalhes mostra pendencias do setor');
verificar(!preg_match('/Warning:|Fatal error:|SQLSTATE|Stack trace/', $resposta['html']), 'detalhes do setor sem erro tecnico exposto');

echo "TOTAL: $testes testes; $falhas falha(s).\n";
curl_close($curl);
exit($falhas ? 1 : 0);
