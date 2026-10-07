<?php
// pages/dashboard.php

require_once __DIR__ . '/../config/conexao.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: autentificacao/login.php');
    exit;
}

require_once __DIR__ . '/../crud/crud_categorias.php';
require_once __DIR__ . '/../crud/crud_setores.php';
require_once __DIR__ . '/../crud/crud_compromissos.php';
require_once __DIR__ . '/../crud/crud_contas_receber.php';

// Cada página aberta recebe um número de envio diferente.
$_SESSION['envios'] ??= [];
foreach ($_SESSION['envios'] as $numero => $hora) {
    if ($hora < time() - 3600) unset($_SESSION['envios'][$numero]);
}
if (count($_SESSION['envios']) >= 200) array_shift($_SESSION['envios']);
$envio = bin2hex(random_bytes(16));
$_SESSION['envios'][$envio] = time();

function formatarMoeda($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

try {
    atualizarCompromissosAtrasados();
    atualizarContasReceberAtrasadas();

    $chartInicio = (isset($_GET['chart_inicio']) && is_string($_GET['chart_inicio'])) ? $_GET['chart_inicio'] : date('Y-01-01'); 
    $chartFim    = (isset($_GET['chart_fim']) && is_string($_GET['chart_fim'])) ? $_GET['chart_fim'] : date('Y-12-31');

    $tableInicio = (isset($_GET['table_inicio']) && is_string($_GET['table_inicio'])) ? $_GET['table_inicio'] : '';
    $tableFim    = (isset($_GET['table_fim']) && is_string($_GET['table_fim'])) ? $_GET['table_fim'] : '';
    $tableBusca  = (isset($_GET['table_busca']) && is_string($_GET['table_busca'])) ? $_GET['table_busca'] : '';
    $tableSetor  = (isset($_GET['table_setor']) && is_string($_GET['table_setor'])) ? $_GET['table_setor'] : '';

    $stmtSaldo = $pdo->prepare("SELECT saldo_atual FROM saldo_geral WHERE id_saldo_geral = 1");
    $stmtSaldo->execute();
    $saldoGeral = $stmtSaldo->fetchColumn() ?: 0;

    $stmtTotalSetores = $pdo->prepare("SELECT COALESCE(SUM(saldo_atual), 0) FROM setores");
    $stmtTotalSetores->execute();
    $totalSetores = $stmtTotalSetores->fetchColumn() ?: 0;
    $totalConsolidado = (float) $saldoGeral + (float) $totalSetores;

    $stmtReceitas = $pdo->prepare("SELECT SUM(valor) FROM receitas WHERE status = 'ATIVA' AND data BETWEEN ? AND ?");
    $stmtReceitas->execute([$chartInicio, $chartFim]);
    $totalReceitas = $stmtReceitas->fetchColumn() ?: 0;

    $stmtDespesas = $pdo->prepare("SELECT SUM(valor) FROM despesas WHERE status = 'ATIVA' AND data BETWEEN ? AND ?");
    $stmtDespesas->execute([$chartInicio, $chartFim]);
    $totalDespesas = $stmtDespesas->fetchColumn() ?: 0;

    $stmtSetoresAtivos = $pdo->prepare("SELECT id_setor, nome, saldo_atual FROM setores WHERE ativo = TRUE ORDER BY nome ASC");
    $stmtSetoresAtivos->execute();
    $listaSetores = $stmtSetoresAtivos->fetchAll(PDO::FETCH_ASSOC);
    
    $listaCategorias = listarCategoriasAtivas();

    $sqlCompromissos = "
        SELECT c.id_compromisso, s.nome AS setor, c.descricao, c.valor, c.data, c.vencimento, c.metodo_pagamento, c.status
        FROM compromissos c
        JOIN setores s ON c.id_setor_fk = s.id_setor
        WHERE c.status IN ('PENDENTE', 'ATRASADO')
    ";
    $paramsComp = [];
    
    if (!empty($tableInicio) && !empty($tableFim)) {
        $sqlCompromissos .= " AND c.vencimento BETWEEN ? AND ? ";
        $paramsComp[] = $tableInicio;
        $paramsComp[] = $tableFim;
    }
    if (!empty($tableBusca)) {
        $sqlCompromissos .= " AND c.descricao LIKE ? ";
        $paramsComp[] = '%' . $tableBusca . '%';
    }
    if (!empty($tableSetor)) {
        $sqlCompromissos .= " AND c.id_setor_fk = ? ";
        $paramsComp[] = $tableSetor;
    }
    $sqlCompromissos .= " ORDER BY c.descricao ASC";
    
    $stmtComp = $pdo->prepare($sqlCompromissos);
    $stmtComp->execute($paramsComp);
    $listaCompromissos = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

    $listaContasReceber = [];
    try { 
        $sqlContasReceber = "
            SELECT cr.id_conta_receber, s.nome AS setor, cr.descricao, cr.valor, cr.data, cr.vencimento, cr.metodo_pagamento, cr.status
            FROM contas_receber cr
            JOIN setores s ON cr.id_setor_fk = s.id_setor
            WHERE cr.status IN ('PENDENTE', 'ATRASADO')
        ";
        $paramsRec = [];
        
        if (!empty($tableInicio) && !empty($tableFim)) {
            $sqlContasReceber .= " AND cr.vencimento BETWEEN ? AND ? ";
            $paramsRec[] = $tableInicio;
            $paramsRec[] = $tableFim;
        }
        if (!empty($tableBusca)) {
            $sqlContasReceber .= " AND cr.descricao LIKE ? ";
            $paramsRec[] = '%' . $tableBusca . '%';
        }
        if (!empty($tableSetor)) {
            $sqlContasReceber .= " AND cr.id_setor_fk = ? ";
            $paramsRec[] = $tableSetor;
        }
        $sqlContasReceber .= " ORDER BY cr.descricao ASC";

        $stmtCR = $pdo->prepare($sqlContasReceber);
        $stmtCR->execute($paramsRec);
        $listaContasReceber = $stmtCR->fetchAll(PDO::FETCH_ASSOC); 
    } catch(Exception $e){}

    $stmtMovimentos = $pdo->prepare("
        SELECT m.tipo, m.descricao, m.valor, m.status, m.criado_em, a.nome AS administrador
        FROM movimentacoes m
        LEFT JOIN administradores a ON a.id_admin = m.id_admin_fk
        ORDER BY m.criado_em DESC, m.id_movimentacao DESC
        LIMIT 8
    ");
    $stmtMovimentos->execute();
    $ultimasMovimentacoes = $stmtMovimentos->fetchAll(PDO::FETCH_ASSOC);

    $sqlMain = "
        SELECT DATE_FORMAT(data, '%Y-%m') AS mes_ano, SUM(valor) AS total, 'RECEITA' AS tipo FROM receitas WHERE status = 'ATIVA' AND data BETWEEN ? AND ? GROUP BY mes_ano
        UNION ALL
        SELECT DATE_FORMAT(data, '%Y-%m') AS mes_ano, SUM(valor) AS total, 'DESPESA' AS tipo FROM despesas WHERE status = 'ATIVA' AND data BETWEEN ? AND ? GROUP BY mes_ano
        ORDER BY mes_ano ASC
    ";
    $stmtMain = $pdo->prepare($sqlMain);
    $stmtMain->execute([$chartInicio, $chartFim, $chartInicio, $chartFim]);
    $resMain = $stmtMain->fetchAll(PDO::FETCH_ASSOC);

    $labelsMain = [];
    foreach($resMain as $r) { if(!in_array($r['mes_ano'], $labelsMain)) $labelsMain[] = $r['mes_ano']; }
    sort($labelsMain);
    if(empty($labelsMain)) $labelsMain = [date('Y-m', strtotime($chartInicio))];
    $recMain = array_fill_keys($labelsMain, 0);
    $despMain = array_fill_keys($labelsMain, 0);
    foreach($resMain as $r) {
        if($r['tipo'] === 'RECEITA') $recMain[$r['mes_ano']] = (float)$r['total'];
        else $despMain[$r['mes_ano']] = (float)$r['total'];
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    exit('Não foi possível carregar esta página. Tente novamente mais tarde.');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MyCash - Dashboard</title>
  <script src="assets/chart.umd.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/dashboard.css"> 
  <link rel="stylesheet" href="partial/sidebar.css"> 
</head>
<body>

  <header>
    <div class="logo-container">
      <button id="mobile-menu-btn" class="mobile-menu-btn" type="button" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
      <i class="fa-solid fa-chart-line logo-icon"></i><span>MyCash</span>
    </div>
  </header>

  <div class="app-container">
    <?php include __DIR__ . '/partial/sidebar.php'; ?>
    
    <main>
      <div class="dashboard-grid">
        <div class="left-section">
          <div class="kpi-container">
            <div class="kpi-card"><div class="kpi-label">Saldo Geral (Caixa)</div><div class="kpi-value"><?= formatarMoeda($saldoGeral) ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Recursos nos Setores</div><div class="kpi-value"><?= formatarMoeda($totalSetores) ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Total Disponivel</div><div class="kpi-value"><?= formatarMoeda($totalConsolidado) ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Receitas Efetivadas</div><div class="kpi-value receitas"><?= formatarMoeda($totalReceitas) ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Despesas Efetivadas</div><div class="kpi-value despesas"><?= formatarMoeda($totalDespesas) ?></div></div>
          </div>

          <div class="main-chart-card">
            <div class="chart-header">
              <h2>Evolução Financeira</h2>
              <form method="GET" class="table-filter-container">
                  <input type="hidden" name="table_inicio" value="<?= htmlspecialchars($tableInicio) ?>">
                  <input type="hidden" name="table_fim" value="<?= htmlspecialchars($tableFim) ?>">
                  <input type="hidden" name="table_busca" value="<?= htmlspecialchars($tableBusca) ?>">
                  <input type="hidden" name="table_setor" value="<?= htmlspecialchars($tableSetor) ?>">
                  
                  <label>De: <input type="date" name="chart_inicio" class="filter-input" value="<?= htmlspecialchars($chartInicio) ?>" required></label>
                  <label>Até: <input type="date" name="chart_fim" class="filter-input" value="<?= htmlspecialchars($chartFim) ?>" required></label>
                  <button type="submit" class="btn-filtrar btn-sm">Filtrar Gráfico</button>
              </form>
            </div>
            <div class="chart-wrapper"><canvas id="mainChart"></canvas></div>
          </div>
        </div>
      </div>

      <div class="dashboard-row">
        <div class="table-section flex-1">
          <div class="table-header"><h2>Recursos por Setor</h2></div>
          <div class="table-responsive">
            <table class="expense-table">
              <thead><tr><th>Setor</th><th>Saldo</th><th>Detalhes</th></tr></thead>
              <tbody>
                <?php foreach ($listaSetores as $setorResumo): ?>
                  <tr>
                    <td><?= htmlspecialchars($setorResumo['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= formatarMoeda($setorResumo['saldo_atual']) ?></td>
                    <td><a class="btn-filtrar btn-sm" href="setores/detalhes.php?id=<?= (int) $setorResumo['id_setor'] ?>">Abrir</a></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($listaSetores)): ?><tr><td colspan="3" class="text-center">Nenhum setor ativo.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="table-section flex-1">
          <div class="table-header"><h2>Ultimas Movimentacoes</h2></div>
          <div class="table-responsive">
            <table class="expense-table">
              <thead><tr><th>Data</th><th>Tipo</th><th>Descricao</th><th>Valor</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($ultimasMovimentacoes as $movimento): ?>
                  <tr>
                    <td><?= date('d/m/Y H:i', strtotime($movimento['criado_em'])) ?></td>
                    <td><?= htmlspecialchars($movimento['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($movimento['descricao'], ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars($movimento['administrador'] ?? 'Sistema', ENT_QUOTES, 'UTF-8') ?></small></td>
                    <td><?= formatarMoeda($movimento['valor']) ?></td>
                    <td><?= htmlspecialchars($movimento['status'], ENT_QUOTES, 'UTF-8') ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($ultimasMovimentacoes)): ?><tr><td colspan="5" class="text-center">Nenhuma movimentacao registrada.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="table-section">
        <div class="tab-header-wrapper">
            <div class="tab-header-row" style="width: 100%;">
                <div class="tab-header">
                    <h2 class="tab-btn active tab-receita" id="tab-btn-receber" onclick="switchTab('pendencias', 'receber')"><i class="fa-solid fa-arrow-down-long icon-receita"></i> Contas a Receber</h2>
                    <h2 class="tab-btn tab-despesa" id="tab-btn-pagar" onclick="switchTab('pendencias', 'pagar')"><i class="fa-solid fa-arrow-up-right-dots icon-despesa"></i> Contas a Pagar</h2>
                </div>
            </div>

            <form method="GET" class="table-filter-container" style="width: 100%;">
                <input type="hidden" name="chart_inicio" value="<?= htmlspecialchars($chartInicio) ?>">
                <input type="hidden" name="chart_fim" value="<?= htmlspecialchars($chartFim) ?>">
                
                <input type="text" name="table_busca" class="filter-input" placeholder="Pesquisar por nome..." value="<?= htmlspecialchars($tableBusca) ?>">
                
                <select name="table_setor" class="filter-input">
                    <option value="">Todos os Setores</option>
                    <?php foreach($listaSetores as $s): ?>
                        <option value="<?= $s['id_setor'] ?>" <?= ($tableSetor == $s['id_setor']) ? 'selected' : '' ?>><?= htmlspecialchars($s['nome']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Vencimento De: <input type="date" name="table_inicio" class="filter-input" value="<?= htmlspecialchars($tableInicio) ?>"></label>
                <label>Até: <input type="date" name="table_fim" class="filter-input" value="<?= htmlspecialchars($tableFim) ?>"></label>
                <button type="submit" class="btn-filtrar btn-sm">Buscar</button>
                
                <?php if(!empty($tableInicio) || !empty($tableBusca) || !empty($tableSetor)): ?>
                    <a href="dashboard.php?<?= htmlspecialchars(http_build_query(['chart_inicio' => $chartInicio, 'chart_fim' => $chartFim]), ENT_QUOTES, 'UTF-8') ?>" class="btn-filtrar btn-sm btn-limpar">Limpar</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive tab-content active" id="content-receber">
          <table class="expense-table">
            <thead><tr><th>Setor</th><th>Descrição (A-Z)</th><th>Lançamento</th><th>Vencimento</th><th>Método</th><th>Valor</th><th>Status</th><th>Ação</th></tr></thead>
            <tbody>
              <?php foreach ($listaContasReceber as $rec): ?>
                <tr>
                  <td><?= htmlspecialchars($rec['setor']) ?></td>
                  <td><?= htmlspecialchars($rec['descricao']) ?></td>
                  <td><?= date('d/m/Y', strtotime($rec['data'])) ?></td>
                  <td><?= date('d/m/Y', strtotime($rec['vencimento'])) ?></td>
                  <td><?= htmlspecialchars(str_replace('_', ' ', $rec['metodo_pagamento'])) ?></td>
                  <td class="text-receita"><?= formatarMoeda($rec['valor']) ?></td>
                  <td class="<?= $rec['status'] === 'ATRASADO' ? 'text-despesa' : '' ?>"><?= $rec['status'] === 'ATRASADO' ? 'Atrasada' : 'Pendente' ?></td>
                  <td><button class="btn-filtrar btn-sm btn-receber" onclick="abrirModalQuitacao('receita', <?= $rec['id_conta_receber'] ?>, '<?= $rec['valor'] ?>')">Baixar</button></td>
                </tr>
              <?php endforeach; ?>
              <?php if(empty($listaContasReceber)) echo "<tr><td colspan='8' class='text-center'>Nenhuma receita pendente no filtro atual.</td></tr>"; ?>
            </tbody>
          </table>
        </div>

        <div class="table-responsive tab-content" id="content-pagar">
          <table class="expense-table">
            <thead><tr><th>Setor</th><th>Descrição (A-Z)</th><th>Lançamento</th><th>Vencimento</th><th>Método</th><th>Valor</th><th>Status</th><th>Ação</th></tr></thead>
            <tbody>
              <?php foreach ($listaCompromissos as $comp): ?>
                <tr>
                  <td><?= htmlspecialchars($comp['setor']) ?></td>
                  <td><?= htmlspecialchars($comp['descricao']) ?></td>
                  <td><?= date('d/m/Y', strtotime($comp['data'])) ?></td>
                  <td><?= date('d/m/Y', strtotime($comp['vencimento'])) ?></td>
                  <td><?= htmlspecialchars(str_replace('_', ' ', $comp['metodo_pagamento'])) ?></td>
                  <td class="text-despesa"><?= formatarMoeda($comp['valor']) ?></td>
                  <td class="<?= $comp['status'] === 'ATRASADO' ? 'text-despesa' : '' ?>"><?= $comp['status'] === 'ATRASADO' ? 'Atrasado' : 'Pendente' ?></td>
                  <td><button class="btn-filtrar btn-sm" onclick="abrirModalQuitacao('despesa', <?= $comp['id_compromisso'] ?>, '<?= $comp['valor'] ?>')">Pagar</button></td>
                </tr>
              <?php endforeach; ?>
              <?php if(empty($listaCompromissos)) echo "<tr><td colspan='8' class='text-center'>Nenhum compromisso pendente no filtro atual.</td></tr>"; ?>
            </tbody>
          </table>
        </div>
      </div>


        <div class="table-section mt-30">
        <div class="tab-header-wrapper" style="border-bottom: 2px solid #f3ebfc; margin-bottom: 0px; padding-bottom: 15px;">
            <div class="tab-header">
                <h2 class="tab-btn active tab-receita" id="tab-btn-recolher" onclick="switchTab('transferencias', 'recolher')">Recolher para Saldo Geral</h2>
                <h2 class="tab-btn tab-despesa" id="tab-btn-realocar" onclick="switchTab('transferencias', 'realocar')">Saldo Geral para Setor</h2>
                
                <h2 class="tab-btn tab-alerta" id="tab-btn-entre-setores" onclick="switchTab('transferencias', 'entre-setores')">Setor para Setor</h2>
            </div>
        </div>

        <div class="tab-content active" id="content-recolher">
            <div class="transfer-panel panel-recolher">
                <p class="table-desc"><i class="fa-solid fa-circle-down" style="color:#2ecc71;"></i> Devolver dinheiro do caixa de um setor para o Saldo Geral.</p>
                <form action="../actions/recolher_saldo.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="envio" value="<?= htmlspecialchars($envio, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-row">
                    <div class="form-group">
                        <select name="id_setor_origem" class="form-control" required>
                        <option value="">Recolher do Setor...</option>
                        <?php foreach($listaSetores as $s) echo "<option value='{$s['id_setor']}'>" . htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') . " (Disp: R$ ".number_format($s['saldo_atual'],2,',','.').")</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group"><input type="number" step="0.01" name="valor_recolhimento" class="form-control" placeholder="Valor R$" required></div>
                    </div>
                    <div class="form-group"><input type="text" name="descricao_recolhimento" class="form-control" placeholder="Motivo/Descrição (Ex: Retorno de orçamento excedente)" required></div>
                    <button type="submit" class="btn-success">Recolher Dinheiro</button>
                </form>
            </div>
        </div>

        <div class="tab-content" id="content-realocar">
            <div class="transfer-panel panel-realocar">
                <p class="table-desc"><i class="fa-solid fa-building-columns" style="color:#8242a3;"></i> Saldo Geral Disponível: <strong><?= formatarMoeda($saldoGeral) ?></strong></p>
                <form action="../actions/realocar_saldo.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="envio" value="<?= htmlspecialchars($envio, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-row">
                    <div class="form-group">
                        <select name="id_setor_destino" class="form-control" required>
                            <option value="">Selecione o Setor...</option>
                            <?php foreach($listaSetores as $s) echo "<option value='{$s['id_setor']}'>" . htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') . "</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group"><input type="number" step="0.01" name="valor_transferencia" class="form-control" placeholder="Valor R$" required></div>
                    </div>
                    <div class="form-group"><input type="text" name="descricao_transferencia" class="form-control" placeholder="Motivo/Descrição" required></div>
                    <button type="submit" class="btn-primary">Transferir para Setor</button>
                </form>
            </div>
        </div>
        
        <div class="tab-content" id="content-entre-setores">
            <div class="transfer-panel panel-entre-setores">
                <p class="table-desc"><i class="fa-solid fa-right-left" style="color:#e67e22;"></i> Transferir dinheiro diretamente de um Setor para outro.</p>
                <form action="../actions/transferir_entre_setores.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="envio" value="<?= htmlspecialchars($envio, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <select name="id_setor_origem" class="form-control" required>
                                <option value="">Retirar do Setor (Origem)...</option>
                                <?php foreach($listaSetores as $s) echo "<option value='{$s['id_setor']}'>" . htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') . " (Disp: R$ ".number_format($s['saldo_atual'],2,',','.').")</option>"; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="id_setor_destino" class="form-control" required>
                                <option value="">Enviar para o Setor (Destino)...</option>
                                <?php foreach($listaSetores as $s) echo "<option value='{$s['id_setor']}'>" . htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') . "</option>"; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><input type="number" step="0.01" name="valor_transferencia" class="form-control" placeholder="Valor (R$)" required></div>
                        <div class="form-group"><input type="text" name="descricao_transferencia" class="form-control" placeholder="Motivo/Descrição" required></div>
                    </div>
                    <button type="submit" class="btn-warning">Confirmar Transferência</button>
                </form>
            </div>
        </div>
      </div>

  <button class="fab-btn" onclick="abrirModalLancamento()" title="Novo Lançamento">
      <i class="fa-solid fa-plus"></i>
  </button>

  <div id="modalLancamento" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Registrar Novo Lançamento</h3>
        <button class="close-modal" onclick="document.getElementById('modalLancamento').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
      </div>
      
      <form action="../actions/salvar_lancamento_completo.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="envio" value="<?= htmlspecialchars($envio, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Tipo de Operação</label>
                <select name="tipo_operacao" id="tipoOperacao" class="form-control" onchange="filtrarCategorias()" required>
                    <option value="despesa">Despesa (Saída)</option>
                    <option value="receita">Receita (Entrada)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status Atual</label>
                <select name="status_pagamento" class="form-control" required>
                    <option value="efetivado">Efetivado (Pago/Recebido agora)</option>
                    <option value="pendente">Pendente (A Pagar/Receber)</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Descrição do Lançamento</label>
            <input type="text" name="descricao" class="form-control" placeholder="Ex: Compra de Servidores, Pagamento X..." required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Valor (R$)</label>
                <input type="number" step="0.01" min="0.01" name="valor" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Setor Responsável</label>
                <select name="id_setor" class="form-control" required>
                    <option value="">Selecione...</option>
                    <?php foreach($listaSetores as $s) echo "<option value='{$s['id_setor']}'>" . htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') . "</option>"; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Data do Lançamento</label>
                <input type="date" name="data_lancamento" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Data de Vencimento</label>
                <input type="date" name="data_vencimento" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Categoria</label>
                <select name="id_categoria" id="selectCategoria" class="form-control" required>
                    <?php foreach($listaCategorias as $c): ?>
                        <option value="<?= $c['id_categoria'] ?>" data-tipo="<?= strtolower($c['tipo']) ?>">
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Método de Pagamento</label>
                <select name="metodo_pagamento" class="form-control" required>
                    <option value="PIX">PIX</option>
                    <option value="DINHEIRO">Dinheiro Espécie</option>
                    <option value="CARTAO_CREDITO">Cartão de Crédito</option>
                    <option value="CARTAO_DEBITO">Cartão de Débito</option>
                    <option value="BOLETO">Boleto</option>
                    <option value="TRANSFERENCIA">Transferência Bancária</option>
                    <option value="OUTRO">Outro</option>
                </select>
            </div>
        </div>

        <div class="form-group-checkbox">
            <input type="checkbox" id="checkParcelado" name="is_parcelado" onchange="toggleParcelamento()">
            <label for="checkParcelado" class="checkbox-label">Esta operação é parcelada?</label>
        </div>

        <div id="div_parcelamento">
            <div class="form-group m-0">
                <label>Quantidade Total de Parcelas</label>
                <input type="number" min="2" max="120" name="qtd_parcelas" id="inputParcelas" class="form-control" placeholder="Ex: 12">
            </div>
        </div>

        <button type="submit" class="btn-primary mt-15">Salvar Lançamento</button>
      </form>
    </div>
  </div>

  <div id="modalQuitacao" class="modal-overlay">
    <div class="modal-content modal-sm">
      <div class="modal-header">
        <h3 id="tituloModalQuitacao">Confirmar Baixa</h3>
        <button class="close-modal" onclick="document.getElementById('modalQuitacao').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form action="../actions/quitar_pendencia.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="envio" value="<?= htmlspecialchars($envio, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id_registro" id="quitacao_id">
        <input type="hidden" name="tipo_quitacao" id="quitacao_tipo">

        <div class="form-group">
            <label>Data Efetiva</label>
            <input type="date" name="data_pagamento" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
            <label>Valor Final (R$)</label>
            <input type="number" step="0.01" name="valor_final" id="quitacao_valor" class="form-control" required>
        </div>
        <button type="submit" class="btn-success" id="btnSubmitQuitacao">Confirmar</button>
      </form>
    </div>
  </div>

  <script>
    const ctxMain = document.getElementById('mainChart').getContext('2d');
    new Chart(ctxMain, {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_values($labelsMain)) ?>,
        datasets: [
          { label: 'Receitas', data: <?= json_encode(array_values($recMain)) ?>, backgroundColor: '#2a0845', borderRadius: 4, maxBarThickness: 45 },
          { label: 'Despesas', data: <?= json_encode(array_values($despMain)) ?>, backgroundColor: '#8242a3', borderRadius: 4, maxBarThickness: 45 }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });

function switchTab(group, tabName) {
        if (group === 'pendencias') {
            document.getElementById('tab-btn-receber').classList.remove('active');
            document.getElementById('tab-btn-pagar').classList.remove('active');
            document.getElementById('content-receber').classList.remove('active');
            document.getElementById('content-pagar').classList.remove('active');
            
            document.getElementById('tab-btn-' + tabName).classList.add('active');
            document.getElementById('content-' + tabName).classList.add('active');
        } else if (group === 'transferencias') {
            document.getElementById('tab-btn-recolher').classList.remove('active');
            document.getElementById('tab-btn-realocar').classList.remove('active');
            document.getElementById('tab-btn-entre-setores').classList.remove('active');
            
            document.getElementById('content-recolher').classList.remove('active');
            document.getElementById('content-realocar').classList.remove('active');
            document.getElementById('content-entre-setores').classList.remove('active');
            
            document.getElementById('tab-btn-' + tabName).classList.add('active');
            document.getElementById('content-' + tabName).classList.add('active');
        }
    }

    function filtrarCategorias() {
        const tipoSelecionado = document.getElementById('tipoOperacao').value; // 'receita' ou 'despesa'
        const selectCategoria = document.getElementById('selectCategoria');
        const options = selectCategoria.querySelectorAll('option[data-tipo]');
        
        let primeiraOpcaoValida = null;

        options.forEach(opt => {
            if (opt.getAttribute('data-tipo') === tipoSelecionado) {
                opt.style.display = '';
                opt.disabled = false;
                if (!primeiraOpcaoValida) primeiraOpcaoValida = opt.value;
            } else {
                opt.style.display = 'none';
                opt.disabled = true;
            }
        });
        
        if(primeiraOpcaoValida) {
            selectCategoria.value = primeiraOpcaoValida;
        }
    }

    function abrirModalLancamento() {
        document.getElementById('modalLancamento').style.display = 'flex';
        filtrarCategorias(); 
    }

    function toggleParcelamento() {
        const check = document.getElementById('checkParcelado');
        const div = document.getElementById('div_parcelamento');
        const input = document.getElementById('inputParcelas');
        if(check.checked) { div.style.display = 'block'; input.required = true; } 
        else { div.style.display = 'none'; input.required = false; input.value = ''; }
    }

    function abrirModalQuitacao(tipo, id, valorOriginal) {
        document.getElementById('quitacao_tipo').value = tipo;
        document.getElementById('quitacao_id').value = id;
        document.getElementById('quitacao_valor').value = valorOriginal;
        
        const titulo = document.getElementById('tituloModalQuitacao');
        const btn = document.getElementById('btnSubmitQuitacao');

        if(tipo === 'receita') {
            titulo.innerText = 'Baixar Receita (Recebimento)';
            btn.className = 'btn-success';
            btn.innerText = 'Confirmar Recebimento';
        } else {
            titulo.innerText = 'Pagar Compromisso (Despesa)';
            btn.className = 'btn-primary';
            btn.innerText = 'Confirmar Pagamento';
        }

        document.getElementById('modalQuitacao').style.display = 'flex';
    }
  </script>
</body>
</html>
