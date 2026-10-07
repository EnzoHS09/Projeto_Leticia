<?php
// pages/setores/detalhes.php


require_once __DIR__ . '/../../config/conexao.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: ../autentificacao/login.php');
    exit;
}
require_once __DIR__ . '/../../crud/crud_setores.php';
require_once __DIR__ . '/../../crud/crud_categorias.php';
require_once __DIR__ . '/../../crud/crud_compromissos.php';
require_once __DIR__ . '/../../crud/crud_contas_receber.php';

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

$id_setor = isset($_GET['id']) && is_string($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id_setor <= 0) {
    die("<h3>Setor inválido.</h3><a href='../dashboard.php'>Voltar</a>");
}

try {
    atualizarCompromissosAtrasados();
    atualizarContasReceberAtrasadas();

    $setor = buscarSetorPorId($id_setor);
    if (!$setor) die("<h3>Setor não encontrado.</h3><a href='../dashboard.php'>Voltar</a>");


    $listaCategorias = listarCategoriasAtivas();

    $filtro_busca  = (isset($_GET['busca']) && is_string($_GET['busca'])) ? $_GET['busca'] : '';
    $filtro_inicio = (isset($_GET['data_inicio']) && is_string($_GET['data_inicio'])) ? $_GET['data_inicio'] : '';
    $filtro_fim    = (isset($_GET['data_fim']) && is_string($_GET['data_fim'])) ? $_GET['data_fim'] : '';

    $sortRecPend = (isset($_GET['sort_rec_pend']) && is_string($_GET['sort_rec_pend'])) ? $_GET['sort_rec_pend'] : 'date_desc';
    if (!in_array($sortRecPend, ['date_asc', 'date_desc', 'val_asc', 'val_desc'], true)) {
        $sortRecPend = 'date_desc';
    }
    $orderRecPend = ['date_asc' => 'cr.vencimento ASC', 'date_desc' => 'cr.vencimento DESC', 'val_asc' => 'cr.valor ASC', 'val_desc' => 'cr.valor DESC'][$sortRecPend];

    $sortDespPend = (isset($_GET['sort_desp_pend']) && is_string($_GET['sort_desp_pend'])) ? $_GET['sort_desp_pend'] : 'date_desc';
    if (!in_array($sortDespPend, ['date_asc', 'date_desc', 'val_asc', 'val_desc'], true)) {
        $sortDespPend = 'date_desc';
    }
    $orderDespPend = ['date_asc' => 'c.vencimento ASC', 'date_desc' => 'c.vencimento DESC', 'val_asc' => 'c.valor ASC', 'val_desc' => 'c.valor DESC'][$sortDespPend];

    $sortRecEfet = (isset($_GET['sort_rec_efet']) && is_string($_GET['sort_rec_efet'])) ? $_GET['sort_rec_efet'] : 'date_desc';
    if (!in_array($sortRecEfet, ['date_asc', 'date_desc', 'val_asc', 'val_desc'], true)) {
        $sortRecEfet = 'date_desc';
    }
    $orderRecEfet = ['date_asc' => 'r.data ASC', 'date_desc' => 'r.data DESC', 'val_asc' => 'r.valor ASC', 'val_desc' => 'r.valor DESC'][$sortRecEfet];

    $sortDespEfet = (isset($_GET['sort_desp_efet']) && is_string($_GET['sort_desp_efet'])) ? $_GET['sort_desp_efet'] : 'date_desc';
    if (!in_array($sortDespEfet, ['date_asc', 'date_desc', 'val_asc', 'val_desc'], true)) {
        $sortDespEfet = 'date_desc';
    }
    $orderDespEfet = ['date_asc' => 'd.data ASC', 'date_desc' => 'd.data DESC', 'val_asc' => 'd.valor ASC', 'val_desc' => 'd.valor DESC'][$sortDespEfet];


    function buildQuery($baseSql, $dateColumn, $filtro_busca, $filtro_inicio, $filtro_fim, $orderBy) {
        $params = [];
        if (!empty($filtro_busca)) {
            $baseSql .= " AND descricao LIKE :busca ";
            $params[':busca'] = "%$filtro_busca%";
        }
        if (!empty($filtro_inicio) && !empty($filtro_fim)) {
            $baseSql .= " AND $dateColumn BETWEEN :inicio AND :fim ";
            $params[':inicio'] = $filtro_inicio;
            $params[':fim'] = $filtro_fim;
        }
        $baseSql .= " ORDER BY $orderBy";
        return ['sql' => $baseSql, 'params' => $params];
    }

    $sqlDesp = "SELECT d.*, c.nome AS categoria FROM despesas d LEFT JOIN categorias c ON d.id_categoria_fk = c.id_categoria WHERE d.id_setor_fk = :id AND d.status = 'ATIVA'";
    $qDesp = buildQuery($sqlDesp, 'd.data', $filtro_busca, $filtro_inicio, $filtro_fim, $orderDespEfet);
    $qDesp['params'][':id'] = $id_setor;
    $stmtDespesas = $pdo->prepare($qDesp['sql'] . " LIMIT 100");
    $stmtDespesas->execute($qDesp['params']);
    $despesasEfetivadas = $stmtDespesas->fetchAll(PDO::FETCH_ASSOC);

    $sqlRec = "SELECT r.*, c.nome AS categoria FROM receitas r LEFT JOIN categorias c ON r.id_categoria_fk = c.id_categoria WHERE r.id_setor_fk = :id AND r.status = 'ATIVA'";
    $qRec = buildQuery($sqlRec, 'r.data', $filtro_busca, $filtro_inicio, $filtro_fim, $orderRecEfet);
    $qRec['params'][':id'] = $id_setor;
    $stmtReceitas = $pdo->prepare($qRec['sql'] . " LIMIT 100");
    $stmtReceitas->execute($qRec['params']);
    $receitasEfetivadas = $stmtReceitas->fetchAll(PDO::FETCH_ASSOC);

    $sqlComp = "SELECT c.*, cat.nome AS categoria FROM compromissos c LEFT JOIN categorias cat ON c.id_categoria_fk = cat.id_categoria WHERE c.id_setor_fk = :id AND c.status IN ('PENDENTE', 'ATRASADO')";
    $qComp = buildQuery($sqlComp, 'c.vencimento', $filtro_busca, $filtro_inicio, $filtro_fim, $orderDespPend);
    $qComp['params'][':id'] = $id_setor;
    $stmtCompromissos = $pdo->prepare($qComp['sql']);
    $stmtCompromissos->execute($qComp['params']);
    $compromissosPendentes = $stmtCompromissos->fetchAll(PDO::FETCH_ASSOC);

    $sqlCR = "SELECT cr.*, cat.nome AS categoria FROM contas_receber cr LEFT JOIN categorias cat ON cr.id_categoria_fk = cat.id_categoria WHERE cr.id_setor_fk = :id AND cr.status IN ('PENDENTE', 'ATRASADO')";
    $qCR = buildQuery($sqlCR, 'cr.vencimento', $filtro_busca, $filtro_inicio, $filtro_fim, $orderRecPend);
    $qCR['params'][':id'] = $id_setor;
    $stmtCR = $pdo->prepare($qCR['sql']);
    $stmtCR->execute($qCR['params']);
    $contasReceberPendentes = $stmtCR->fetchAll(PDO::FETCH_ASSOC);

    $graficoInicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_inicio) ? $filtro_inicio : date('Y-01-01');
    $graficoFim = preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_fim) ? $filtro_fim : date('Y-12-31');
    $stmtGrafico = $pdo->prepare("
        SELECT DATE_FORMAT(data, '%Y-%m') AS mes_ano, SUM(valor) AS total, 'RECEITA' AS tipo
        FROM receitas WHERE id_setor_fk = ? AND status = 'ATIVA' AND data BETWEEN ? AND ? GROUP BY mes_ano
        UNION ALL
        SELECT DATE_FORMAT(data, '%Y-%m') AS mes_ano, SUM(valor) AS total, 'DESPESA' AS tipo
        FROM despesas WHERE id_setor_fk = ? AND status = 'ATIVA' AND data BETWEEN ? AND ? GROUP BY mes_ano
        ORDER BY mes_ano
    ");
    $stmtGrafico->execute([$id_setor, $graficoInicio, $graficoFim, $id_setor, $graficoInicio, $graficoFim]);
    $dadosGrafico = $stmtGrafico->fetchAll(PDO::FETCH_ASSOC);
    $mesesGrafico = [];
    foreach ($dadosGrafico as $item) {
        if (!in_array($item['mes_ano'], $mesesGrafico, true)) $mesesGrafico[] = $item['mes_ano'];
    }
    sort($mesesGrafico);
    if (empty($mesesGrafico)) $mesesGrafico = [date('Y-m')];
    $receitasGrafico = array_fill_keys($mesesGrafico, 0);
    $despesasGrafico = array_fill_keys($mesesGrafico, 0);
    foreach ($dadosGrafico as $item) {
        if ($item['tipo'] === 'RECEITA') $receitasGrafico[$item['mes_ano']] = (float) $item['total'];
        else $despesasGrafico[$item['mes_ano']] = (float) $item['total'];
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
  <title>Detalhes do Setor - <?= htmlspecialchars($setor['nome']) ?></title>
  <script src="../assets/chart.umd.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <link rel="stylesheet" href="../assets/dashboard.css">
  <link rel="stylesheet" href="../partial/sidebar.css">
  <link rel="stylesheet" href="../assets/setores.css">
  

</head>
<body>

  <header>
    <div class="logo-container">
      <button id="mobile-menu-btn" class="mobile-menu-btn" type="button" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
      <i class="fa-solid fa-chart-line logo-icon"></i><span>MyCash</span>
    </div>
    <div class="user-area"><a href="../dashboard.php" class="nav-link">Voltar ao Dashboard</a></div>
  </header>

  <div class="app-container">
    <?php include __DIR__ . '/../partial/sidebar.php'; ?>
    
    <main>
      <div class="page-header" style="flex-wrap: wrap; gap: 15px;">
        <div>
          <h2><i class="fa-solid fa-building"></i> <?= htmlspecialchars($setor['nome']) ?></h2>
          <p><?= htmlspecialchars($setor['descricao'] ?: 'Sem descricao cadastrada.', ENT_QUOTES, 'UTF-8') ?></p>
          <p>Saldo Atual Disponível: <span class="saldo-destaque"><?= formatarMoeda($setor['saldo_atual']) ?></span></p>
        </div>
        
        <form method="GET" class="table-filter-container">
            <input type="hidden" name="id" value="<?= $id_setor ?>">
            <input type="hidden" name="sort_rec_pend" value="<?= htmlspecialchars($sortRecPend, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="sort_desp_pend" value="<?= htmlspecialchars($sortDespPend, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="sort_rec_efet" value="<?= htmlspecialchars($sortRecEfet, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="sort_desp_efet" value="<?= htmlspecialchars($sortDespEfet, ENT_QUOTES, 'UTF-8') ?>">
            
            <input type="text" name="busca" class="filter-input" placeholder="Pesquisar descrição..." value="<?= htmlspecialchars($filtro_busca) ?>">
            <label>De: <input type="date" name="data_inicio" class="filter-input" value="<?= htmlspecialchars($filtro_inicio) ?>"></label>
            <label>Até: <input type="date" name="data_fim" class="filter-input" value="<?= htmlspecialchars($filtro_fim) ?>"></label>
            <button type="submit" class="btn-filtrar btn-sm">Buscar</button>
            <?php if($filtro_busca || $filtro_inicio): ?>
                <a href="?id=<?= $id_setor ?>" class="btn-filtrar btn-sm btn-limpar">Limpar</a>
            <?php endif; ?>
        </form>
      </div>

      <div class="table-section mb-30" style="width: 100%;">
        <div class="table-header"><h2>Receitas e despesas do setor</h2></div>
        <div class="chart-wrapper"><canvas id="setorChart"></canvas></div>
      </div>

      <div class="table-section mb-30" style="width: 100%;">
        <div class="tab-header">
            <h2 class="tab-btn active tab-receita" id="tab-btn-receber" onclick="switchTab('pendencias', 'receber')">
                <i class="fa-solid fa-hand-holding-dollar icon-receita"></i> Contas a Receber
            </h2>
            <h2 class="tab-btn tab-despesa" id="tab-btn-pagar" onclick="switchTab('pendencias', 'pagar')">
                <i class="fa-solid fa-file-invoice-dollar icon-despesa"></i> Contas a Pagar
            </h2>
        </div>

        <div class="table-responsive tab-content active" id="content-receber">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
                <form method="GET" style="margin: 0; display:flex; align-items:center; gap:8px;">
                    <input type="hidden" name="id" value="<?= $id_setor ?>"><input type="hidden" name="busca" value="<?= htmlspecialchars($filtro_busca, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="data_inicio" value="<?= htmlspecialchars($filtro_inicio, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="data_fim" value="<?= htmlspecialchars($filtro_fim, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="sort_desp_pend" value="<?= htmlspecialchars($sortDespPend, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="sort_rec_efet" value="<?= htmlspecialchars($sortRecEfet, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="sort_desp_efet" value="<?= htmlspecialchars($sortDespEfet, ENT_QUOTES, 'UTF-8') ?>">
                    <label style="font-size: 11px; font-weight:600; color:#64748b; text-transform:uppercase;">Ordenar por:</label>
                    <select name="sort_rec_pend" class="filter-input" style="padding: 4px 10px; font-size: 12px; min-width: 150px; border-radius: 6px;" onchange="this.form.submit()">
                        <option value="date_desc" <?= $sortRecPend == 'date_desc' ? 'selected' : '' ?>>Data (Mais Recentes)</option>
                        <option value="date_asc" <?= $sortRecPend == 'date_asc' ? 'selected' : '' ?>>Data (Mais Antigos)</option>
                        <option value="val_desc" <?= $sortRecPend == 'val_desc' ? 'selected' : '' ?>>Valor (Maior ➝ Menor)</option>
                        <option value="val_asc" <?= $sortRecPend == 'val_asc' ? 'selected' : '' ?>>Valor (Menor ➝ Maior)</option>
                    </select>
                </form>
            </div>
            <table class="expense-table">
                <thead><tr><th>Descrição</th><th>Categoria</th><th>Lançamento</th><th>Vencimento</th><th>Método</th><th>Valor</th><th>Status</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach($contasReceberPendentes as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['descricao']) ?>
                                <?php if(isset($r['numero_parcela']) && $r['numero_parcela'] > 0) echo "<br><small class='text-info'>Parc {$r['numero_parcela']}/{$r['total_parcelas']}</small>"; ?>
                            </td>
                            <td><?= htmlspecialchars($r['categoria']) ?></td>
                            <td><?= date('d/m/Y', strtotime($r['data'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($r['vencimento'])) ?></td>
                            <td><?= htmlspecialchars(str_replace('_', ' ', $r['metodo_pagamento'])) ?></td>
                            <td class="text-receita"><?= formatarMoeda($r['valor']) ?></td>
                            <td class="<?= $r['status'] === 'ATRASADO' ? 'text-despesa' : '' ?>"><?= $r['status'] === 'ATRASADO' ? 'Atrasada' : 'Pendente' ?></td>
                            <td class="action-cell">
                                <button class="btn-filtrar btn-sm btn-receber" onclick="abrirModalQuitacao('receita', <?= $r['id_conta_receber'] ?>, '<?= $r['valor'] ?>')">Baixar</button>
                                <button type="button" class="btn-icon btn-edit" onclick="abrirModalEditar('conta_receber', <?= $r['id_conta_receber'] ?>, <?= htmlspecialchars(json_encode($r['descricao'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, '<?= $r['valor'] ?>', '<?= $r['data'] ?>', '<?= $r['vencimento'] ?>', '<?= $r['metodo_pagamento'] ?>', <?= $r['id_categoria_fk'] ?>)" title="Editar"><i class="fa-solid fa-pen"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($contasReceberPendentes)) echo "<tr><td colspan='8' class='text-center'>Nenhuma receita pendente.</td></tr>"; ?>
                </tbody>
            </table>
        </div>

        <div class="table-responsive tab-content" id="content-pagar">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
                <form method="GET" style="margin: 0; display:flex; align-items:center; gap:8px;">
                    <input type="hidden" name="id" value="<?= $id_setor ?>"><input type="hidden" name="busca" value="<?= htmlspecialchars($filtro_busca, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="data_inicio" value="<?= htmlspecialchars($filtro_inicio, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="data_fim" value="<?= htmlspecialchars($filtro_fim, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="sort_rec_pend" value="<?= htmlspecialchars($sortRecPend, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="sort_rec_efet" value="<?= htmlspecialchars($sortRecEfet, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="sort_desp_efet" value="<?= htmlspecialchars($sortDespEfet, ENT_QUOTES, 'UTF-8') ?>">
                    <label style="font-size: 11px; font-weight:600; color:#64748b; text-transform:uppercase;">Ordenar por:</label>
                    <select name="sort_desp_pend" class="filter-input" style="padding: 4px 10px; font-size: 12px; min-width: 150px; border-radius: 6px;" onchange="this.form.submit()">
                        <option value="date_desc" <?= $sortDespPend == 'date_desc' ? 'selected' : '' ?>>Data (Mais Recentes)</option>
                        <option value="date_asc" <?= $sortDespPend == 'date_asc' ? 'selected' : '' ?>>Data (Mais Antigos)</option>
                        <option value="val_desc" <?= $sortDespPend == 'val_desc' ? 'selected' : '' ?>>Valor (Maior ➝ Menor)</option>
                        <option value="val_asc" <?= $sortDespPend == 'val_asc' ? 'selected' : '' ?>>Valor (Menor ➝ Maior)</option>
                    </select>
                </form>
            </div>
            <table class="expense-table">
                <thead><tr><th>Descrição</th><th>Categoria</th><th>Lançamento</th><th>Vencimento</th><th>Método</th><th>Valor</th><th>Status</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach($compromissosPendentes as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['descricao']) ?></td>
                            <td><?= htmlspecialchars($c['categoria']) ?></td>
                            <td><?= date('d/m/Y', strtotime($c['data'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($c['vencimento'])) ?></td>
                            <td><?= htmlspecialchars(str_replace('_', ' ', $c['metodo_pagamento'])) ?></td>
                            <td class="text-despesa"><?= formatarMoeda($c['valor']) ?></td>
                            <td class="<?= $c['status'] === 'ATRASADO' ? 'text-despesa' : '' ?>"><?= $c['status'] === 'ATRASADO' ? 'Atrasado' : 'Pendente' ?></td>
                            <td class="action-cell">
                                <button class="btn-filtrar btn-sm" onclick="abrirModalQuitacao('despesa', <?= $c['id_compromisso'] ?>, '<?= $c['valor'] ?>')">Pagar</button>
                                <button type="button" class="btn-icon btn-edit" onclick="abrirModalEditar('compromisso', <?= $c['id_compromisso'] ?>, <?= htmlspecialchars(json_encode($c['descricao'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, '<?= $c['valor'] ?>', '<?= $c['data'] ?>', '<?= $c['vencimento'] ?>', '<?= $c['metodo_pagamento'] ?>', <?= $c['id_categoria_fk'] ?>)" title="Editar"><i class="fa-solid fa-pen"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($compromissosPendentes)) echo "<tr><td colspan='8' class='text-center'>Tudo em dia!</td></tr>"; ?>
                </tbody>
            </table>
        </div>
      </div>

      <div class="table-section" style="width: 100%;">
        <div class="tab-header">
            <h2 class="tab-btn active tab-receita" id="tab-btn-entradas" onclick="switchTab('historico', 'entradas')">
                <i class="fa-solid fa-arrow-trend-up icon-receita"></i> Entradas Efetivadas
            </h2>
            <h2 class="tab-btn tab-despesa" id="tab-btn-saidas" onclick="switchTab('historico', 'saidas')">
                <i class="fa-solid fa-arrow-trend-down icon-despesa"></i> Saídas Efetivadas
            </h2>
        </div>

        <div class="table-responsive tab-content active" id="content-entradas">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
                <form method="GET" style="margin: 0; display:flex; align-items:center; gap:8px;">
                    <input type="hidden" name="id" value="<?= $id_setor ?>"><input type="hidden" name="busca" value="<?= htmlspecialchars($filtro_busca, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="data_inicio" value="<?= htmlspecialchars($filtro_inicio, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="data_fim" value="<?= htmlspecialchars($filtro_fim, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="sort_rec_pend" value="<?= htmlspecialchars($sortRecPend, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="sort_desp_pend" value="<?= htmlspecialchars($sortDespPend, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="sort_desp_efet" value="<?= htmlspecialchars($sortDespEfet, ENT_QUOTES, 'UTF-8') ?>">
                    <label style="font-size: 11px; font-weight:600; color:#64748b; text-transform:uppercase;">Ordenar por:</label>
                    <select name="sort_rec_efet" class="filter-input" style="padding: 4px 10px; font-size: 12px; min-width: 150px; border-radius: 6px;" onchange="this.form.submit()">
                        <option value="date_desc" <?= $sortRecEfet == 'date_desc' ? 'selected' : '' ?>>Data (Mais Recentes)</option>
                        <option value="date_asc" <?= $sortRecEfet == 'date_asc' ? 'selected' : '' ?>>Data (Mais Antigos)</option>
                        <option value="val_desc" <?= $sortRecEfet == 'val_desc' ? 'selected' : '' ?>>Valor (Maior ➝ Menor)</option>
                        <option value="val_asc" <?= $sortRecEfet == 'val_asc' ? 'selected' : '' ?>>Valor (Menor ➝ Maior)</option>
                    </select>
                </form>
            </div>
            <table class="expense-table">
                <thead><tr><th>Data</th><th>Vencimento</th><th>Método</th><th>Descrição</th><th>Categoria</th><th>Valor</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach($receitasEfetivadas as $r): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($r['data'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($r['vencimento'])) ?></td>
                            <td><?= htmlspecialchars(str_replace('_', ' ', $r['metodo_pagamento'])) ?></td>
                            <td><?= htmlspecialchars($r['descricao']) ?></td>
                            <td><?= htmlspecialchars($r['categoria']) ?></td>
                            <td class="text-receita">+ <?= formatarMoeda($r['valor']) ?></td>
                            <td class="action-cell">
                                <button type="button" class="btn-icon btn-edit" onclick="abrirModalEditar('receita_efetivada', <?= $r['id_receita'] ?>, <?= htmlspecialchars(json_encode($r['descricao'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, '<?= $r['valor'] ?>', '<?= $r['data'] ?>', '<?= $r['vencimento'] ?>', '<?= $r['metodo_pagamento'] ?>', <?= $r['id_categoria_fk'] ?>)" title="Editar"><i class="fa-solid fa-pen"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($receitasEfetivadas)) echo "<tr><td colspan='7' class='text-center'>Nenhuma entrada registrada.</td></tr>"; ?>
                </tbody>
            </table>
        </div>

        <div class="table-responsive tab-content" id="content-saidas">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
                <form method="GET" style="margin: 0; display:flex; align-items:center; gap:8px;">
                    <input type="hidden" name="id" value="<?= $id_setor ?>"><input type="hidden" name="busca" value="<?= htmlspecialchars($filtro_busca, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="data_inicio" value="<?= htmlspecialchars($filtro_inicio, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="data_fim" value="<?= htmlspecialchars($filtro_fim, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="sort_rec_pend" value="<?= htmlspecialchars($sortRecPend, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="sort_desp_pend" value="<?= htmlspecialchars($sortDespPend, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="sort_rec_efet" value="<?= htmlspecialchars($sortRecEfet, ENT_QUOTES, 'UTF-8') ?>">
                    <label style="font-size: 11px; font-weight:600; color:#64748b; text-transform:uppercase;">Ordenar por:</label>
                    <select name="sort_desp_efet" class="filter-input" style="padding: 4px 10px; font-size: 12px; min-width: 150px; border-radius: 6px;" onchange="this.form.submit()">
                        <option value="date_desc" <?= $sortDespEfet == 'date_desc' ? 'selected' : '' ?>>Data (Mais Recentes)</option>
                        <option value="date_asc" <?= $sortDespEfet == 'date_asc' ? 'selected' : '' ?>>Data (Mais Antigos)</option>
                        <option value="val_desc" <?= $sortDespEfet == 'val_desc' ? 'selected' : '' ?>>Valor (Maior ➝ Menor)</option>
                        <option value="val_asc" <?= $sortDespEfet == 'val_asc' ? 'selected' : '' ?>>Valor (Menor ➝ Maior)</option>
                    </select>
                </form>
            </div>
            <table class="expense-table">
                <thead><tr><th>Data</th><th>Vencimento</th><th>Método</th><th>Descrição</th><th>Categoria</th><th>Valor</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach($despesasEfetivadas as $d): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($d['data'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($d['vencimento'])) ?></td>
                            <td><?= htmlspecialchars(str_replace('_', ' ', $d['metodo_pagamento'])) ?></td>
                            <td><?= htmlspecialchars($d['descricao']) ?></td>
                            <td><?= htmlspecialchars($d['categoria']) ?></td>
                            <td class="text-despesa">- <?= formatarMoeda($d['valor']) ?></td>
                            <td class="action-cell">
                                <button type="button" class="btn-icon btn-edit" onclick="abrirModalEditar('despesa_efetivada', <?= $d['id_despesa'] ?>, <?= htmlspecialchars(json_encode($d['descricao'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, '<?= $d['valor'] ?>', '<?= $d['data'] ?>', '<?= $d['vencimento'] ?>', '<?= $d['metodo_pagamento'] ?>', <?= $d['id_categoria_fk'] ?>)" title="Editar"><i class="fa-solid fa-pen"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($despesasEfetivadas)) echo "<tr><td colspan='7' class='text-center'>Nenhuma saída registrada.</td></tr>"; ?>
                </tbody>
            </table>
        </div>
      </div>
    </main>
  </div>

  <button class="fab-btn" onclick="abrirModalLancamento()" title="Novo Lançamento neste Setor">
    <i class="fa-solid fa-plus"></i>
  </button>


  <div id="modalLancamento" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Registrar Lançamento - <?= htmlspecialchars($setor['nome']) ?></h3>
        <button type="button" class="close-modal" onclick="document.getElementById('modalLancamento').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
      </div>
      
      <form action="../../actions/salvar_lancamento_completo.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="envio" value="<?= htmlspecialchars($envio, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id_setor" value="<?= $id_setor ?>">
        <input type="hidden" name="redirect_to" value="../pages/setores/detalhes.php?id=<?= $id_setor ?>">

        <div class="form-row">
            <div class="form-group">
                <label>Tipo de Operação</label>
                <select name="tipo_operacao" id="tipoOperacao" class="form-control" onchange="filtrarCategorias()" required>
                    <option value="despesa">Despesa (Saída do Setor)</option>
                    <option value="receita">Receita (Faturamento)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status Atual</label>
                <select name="status_pagamento" class="form-control" required>
                    <option value="efetivado">Efetivado (Afetar Saldo Agora)</option>
                    <option value="pendente">Pendente (Agendar / A Prazo)</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Descrição do Lançamento</label>
            <input type="text" name="descricao" class="form-control" placeholder="Ex: Material de Limpeza..." required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Valor Total (R$)</label>
                <input type="number" step="0.01" min="0.01" name="valor" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Data do Lançamento</label>
                <input type="date" name="data_lancamento" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Data de Vencimento</label>
                <input type="date" name="data_vencimento" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
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
        </div>
        <div class="form-group">
            <label>Método de Pagamento</label>
            <select name="metodo_pagamento" class="form-control" required>
                <option value="PIX">PIX</option>
                <option value="DINHEIRO">Dinheiro</option>
                <option value="CARTAO_CREDITO">Cartão de Crédito</option>
                <option value="CARTAO_DEBITO">Cartão de Débito</option>
                <option value="BOLETO">Boleto</option>
                <option value="TRANSFERENCIA">Transferência Bancária</option>
                <option value="OUTRO">Outro</option>
            </select>
        </div>

        <div class="form-group-checkbox">
            <input type="checkbox" id="checkParcelado" name="is_parcelado" onchange="toggleParcelamento()">
            <label for="checkParcelado" class="checkbox-label">Operação Parcelada?</label>
        </div>

        <div id="div_parcelamento" style="display:none;">
            <div class="form-group m-0">
                <label>Quantidade de Parcelas</label>
                <input type="number" min="2" max="120" name="qtd_parcelas" id="inputParcelas" class="form-control" placeholder="Ex: 5">
            </div>
        </div>

        <button type="submit" class="btn-primary mt-15">Salvar Lançamento no Setor</button>
      </form>
    </div>
  </div>

  <div id="modalQuitacao" class="modal-overlay">
    <div class="modal-content modal-sm">
      <div class="modal-header">
        <h3 id="tituloModalQuitacao">Confirmar Baixa</h3>
        <button type="button" class="close-modal" onclick="document.getElementById('modalQuitacao').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form action="../../actions/quitar_pendencia.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="envio" value="<?= htmlspecialchars($envio, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id_registro" id="quitacao_id">
        <input type="hidden" name="tipo_quitacao" id="quitacao_tipo">
        <input type="hidden" name="redirect_to" value="../pages/setores/detalhes.php?id=<?= $id_setor ?>">

        <div class="form-group">
            <label>Data Efetiva</label>
            <input type="date" name="data_pagamento" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
            <label>Valor Final (R$)</label>
            <input type="number" step="0.01" name="valor_final" id="quitacao_valor" class="form-control" required>
        </div>
        <button type="submit" class="btn-primary" id="btnSubmitQuitacao">Confirmar</button>
      </form>
    </div>
  </div>

  <div id="modalEditar" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Editar Informações</h3>
        <button type="button" class="close-modal" onclick="document.getElementById('modalEditar').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form action="../../actions/editar_registro.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="envio" value="<?= htmlspecialchars($envio, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id_setor" value="<?= $id_setor ?>">
        <input type="hidden" name="id_registro" id="edit_id">
        <input type="hidden" name="tipo_registro" id="edit_tipo">
        <input type="hidden" name="redirect_to" value="../pages/setores/detalhes.php?id=<?= $id_setor ?>">

        <div class="form-group">
            <label>Descrição</label>
            <input type="text" name="descricao" id="edit_desc" class="form-control" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Valor (R$)</label>
                <input type="number" step="0.01" min="0.01" name="valor" id="edit_valor" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Data do Lançamento</label>
                <input type="date" name="data" id="edit_data" class="form-control" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Data de Vencimento</label>
                <input type="date" name="vencimento" id="edit_vencimento" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Método de Pagamento</label>
                <select name="metodo_pagamento" id="edit_metodo" class="form-control" required>
                    <option value="PIX">PIX</option>
                    <option value="DINHEIRO">Dinheiro</option>
                    <option value="CARTAO_CREDITO">Cartão de Crédito</option>
                    <option value="CARTAO_DEBITO">Cartão de Débito</option>
                    <option value="BOLETO">Boleto</option>
                    <option value="TRANSFERENCIA">Transferência Bancária</option>
                    <option value="OUTRO">Outro</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Categoria</label>
            <select name="id_categoria" id="edit_cat" class="form-control" required>
                <?php foreach($listaCategorias as $c): ?>
                    <option value="<?= $c['id_categoria'] ?>"><?= htmlspecialchars($c['nome']) ?> (<?= strtoupper($c['tipo']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-primary mt-15"><i class="fa-solid fa-floppy-disk"></i> Salvar Alterações</button>
      </form>
    </div>
  </div>
  

  <script>
    const setorChart = document.getElementById('setorChart').getContext('2d');
    new Chart(setorChart, {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_values($mesesGrafico)) ?>,
        datasets: [
          { label: 'Receitas', data: <?= json_encode(array_values($receitasGrafico)) ?>, backgroundColor: '#2a0845', borderRadius: 4 },
          { label: 'Despesas', data: <?= json_encode(array_values($despesasGrafico)) ?>, backgroundColor: '#8242a3', borderRadius: 4 }
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
            localStorage.setItem('activeTab_pendencias', tabName);
        } else if (group === 'historico') {
            document.getElementById('tab-btn-entradas').classList.remove('active');
            document.getElementById('tab-btn-saidas').classList.remove('active');
            document.getElementById('content-entradas').classList.remove('active');
            document.getElementById('content-saidas').classList.remove('active');
            document.getElementById('tab-btn-' + tabName).classList.add('active');
            document.getElementById('content-' + tabName).classList.add('active');
            localStorage.setItem('activeTab_historico', tabName);
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        let pendTab = localStorage.getItem('activeTab_pendencias');
        if(pendTab) switchTab('pendencias', pendTab);
        let histTab = localStorage.getItem('activeTab_historico');
        if(histTab) switchTab('historico', histTab);
    });

    function filtrarCategorias() {
        const tipoSelecionado = document.getElementById('tipoOperacao').value; 
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
            titulo.innerText = 'Baixar Receita';
            btn.className = 'btn-success';
        } else {
            titulo.innerText = 'Pagar Despesa';
            btn.className = 'btn-primary';
        }

        document.getElementById('modalQuitacao').style.display = 'flex';
    }

    function abrirModalEditar(tipo, id, desc, valor, data, vencimento, metodo, id_cat) {
        document.getElementById('edit_tipo').value = tipo;
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_desc').value = desc;
        document.getElementById('edit_valor').value = valor;
        document.getElementById('edit_data').value = data;
        document.getElementById('edit_vencimento').value = vencimento;
        document.getElementById('edit_metodo').value = metodo;
        document.getElementById('edit_cat').value = id_cat;
        document.getElementById('modalEditar').style.display = 'flex';
    }
  </script>
</body>
</html>
