<?php
// pages/dashboard.php

require_once __DIR__ . '/../config/conexao.php';

function formatarMoeda($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

try {
    // =======================================================
    // 1. CAPTURA DOS FILTROS DE DATA (Via GET)
    // =======================================================
    $dataInicio = $_GET['data_inicio'] ?? date('Y-01-01');
    $dataFim    = $_GET['data_fim'] ?? date('Y-m-d');

    // 2. MÉTRICAS DOS KPIs (Saldo Geral ignora o filtro, pois é o caixa atual)
    $stmtSaldo = $pdo->query("SELECT saldo_atual FROM saldo_geral WHERE id_saldo_geral = 1");
    $saldoGeral = $stmtSaldo->fetchColumn() ?: 0;

    // Receitas e Despesas respeitam o filtro de data
    $stmtReceitas = $pdo->prepare("SELECT SUM(valor) FROM receitas WHERE status = 'ATIVA' AND data BETWEEN ? AND ?");
    $stmtReceitas->execute([$dataInicio, $dataFim]);
    $totalReceitas = $stmtReceitas->fetchColumn() ?: 0;

    $stmtDespesas = $pdo->prepare("SELECT SUM(valor) FROM despesas WHERE status = 'ATIVA' AND data BETWEEN ? AND ?");
    $stmtDespesas->execute([$dataInicio, $dataFim]);
    $totalDespesas = $stmtDespesas->fetchColumn() ?: 0;

    // 3. MENU DE SETORES
    $stmtSetores = $pdo->query("SELECT id_setor, nome FROM setores WHERE ativo = 1 ORDER BY nome");
    $listaSetoresMenu = $stmtSetores->fetchAll(PDO::FETCH_ASSOC);

    // 4. TABELA DE COMPROMISSOS A PAGAR (Filtrada pela data de vencimento)
    $sqlCompromissos = "
        SELECT c.id_compromisso, s.nome AS setor, c.valor, c.vencimento AS data
        FROM compromissos c
        JOIN setores s ON c.id_setor_fk = s.id_setor
        WHERE c.status IN ('PENDENTE', 'ATRASADO') 
          AND c.vencimento BETWEEN ? AND ?
        ORDER BY c.vencimento ASC
        LIMIT 10
    ";
    $stmtCompromissos = $pdo->prepare($sqlCompromissos);
    $stmtCompromissos->execute([$dataInicio, $dataFim]);
    $listaCompromissos = $stmtCompromissos->fetchAll(PDO::FETCH_ASSOC);

    // =======================================================
    // 5. DADOS GRÁFICO PRINCIPAL (Mensal Filtrado)
    // =======================================================
    $sqlMain = "
        SELECT DATE_FORMAT(data, '%Y-%m') AS mes_ano, SUM(valor) AS total, 'RECEITA' AS tipo
        FROM receitas WHERE status = 'ATIVA' AND data BETWEEN ? AND ? GROUP BY mes_ano
        UNION ALL
        SELECT DATE_FORMAT(data, '%Y-%m') AS mes_ano, SUM(valor) AS total, 'DESPESA' AS tipo
        FROM despesas WHERE status = 'ATIVA' AND data BETWEEN ? AND ? GROUP BY mes_ano
        ORDER BY mes_ano ASC
    ";
    $stmtMain = $pdo->prepare($sqlMain);
    $stmtMain->execute([$dataInicio, $dataFim, $dataInicio, $dataFim]);
    $resMain = $stmtMain->fetchAll(PDO::FETCH_ASSOC);

    $labelsMain = [];
    foreach($resMain as $r) {
        if(!in_array($r['mes_ano'], $labelsMain)) $labelsMain[] = $r['mes_ano'];
    }
    sort($labelsMain);

    if (empty($labelsMain)) {
        $labelsMain = [date('Y-m', strtotime($dataInicio))];
    }

    $recMain = array_fill_keys($labelsMain, 0);
    $despMain = array_fill_keys($labelsMain, 0);

    foreach($resMain as $r) {
        if($r['tipo'] === 'RECEITA') $recMain[$r['mes_ano']] = (float)$r['total'];
        else $despMain[$r['mes_ano']] = (float)$r['total'];
    }

    $jsonLabelsMain = json_encode(array_values($labelsMain));
    $jsonRecMain = json_encode(array_values($recMain));
    $jsonDespMain = json_encode(array_values($despMain));

    // =======================================================
    // 6. DADOS GRÁFICO TRIMESTRAL (Fixo no Ano Atual para Benchmark)
    // =======================================================
    $sqlTri = "
        SELECT QUARTER(data) as tri, SUM(valor) AS total, 'RECEITA' AS tipo
        FROM receitas WHERE status = 'ATIVA' AND YEAR(data) = YEAR(CURDATE()) GROUP BY tri
        UNION ALL
        SELECT QUARTER(data) as tri, SUM(valor) AS total, 'DESPESA' AS tipo
        FROM despesas WHERE status = 'ATIVA' AND YEAR(data) = YEAR(CURDATE()) GROUP BY tri
    ";
    $resTri = $pdo->query($sqlTri)->fetchAll(PDO::FETCH_ASSOC);

    $labelsTri = ['1º TRI', '2º TRI', '3º TRI', '4º TRI'];
    $recTri = [0, 0, 0, 0];
    $despTri = [0, 0, 0, 0];

    foreach($resTri as $r) {
        $index = (int)$r['tri'] - 1;
        if($r['tipo'] === 'RECEITA') $recTri[$index] = (float)$r['total'];
        else $despTri[$index] = (float)$r['total'];
    }

    $jsonRecTri = json_encode($recTri);
    $jsonDespTri = json_encode($despTri);

    // =======================================================
    // 7. DADOS GRÁFICO POR SETOR (Filtrado)
    // =======================================================
    $sqlSetor = "
        SELECT s.nome AS setor, SUM(r.valor) AS total, 'RECEITA' AS tipo
        FROM receitas r JOIN setores s ON r.id_setor_fk = s.id_setor
        WHERE r.status = 'ATIVA' AND r.data BETWEEN ? AND ? GROUP BY s.nome
        UNION ALL
        SELECT s.nome AS setor, SUM(d.valor) AS total, 'DESPESA' AS tipo
        FROM despesas d JOIN setores s ON d.id_setor_fk = s.id_setor
        WHERE d.status = 'ATIVA' AND d.data BETWEEN ? AND ? GROUP BY s.nome
    ";
    $stmtSetor = $pdo->prepare($sqlSetor);
    $stmtSetor->execute([$dataInicio, $dataFim, $dataInicio, $dataFim]);
    $resSetor = $stmtSetor->fetchAll(PDO::FETCH_ASSOC);

    $labelsSetor = [];
    foreach($resSetor as $r) {
        if(!in_array($r['setor'], $labelsSetor)) $labelsSetor[] = $r['setor'];
    }
    sort($labelsSetor);

    if (empty($labelsSetor)) {
        $labelsSetor = ['Nenhum Setor'];
    }

    $recSetor = array_fill_keys($labelsSetor, 0);
    $despSetor = array_fill_keys($labelsSetor, 0);

    foreach($resSetor as $r) {
        if($r['tipo'] === 'RECEITA') $recSetor[$r['setor']] = (float)$r['total'];
        else $despSetor[$r['setor']] = (float)$r['total'];
    }

    $jsonLabelsSetor = json_encode(array_values($labelsSetor));
    $jsonRecSetor = json_encode(array_values($recSetor));
    $jsonDespSetor = json_encode(array_values($despSetor));

} catch (PDOException $e) {
    die("Erro ao carregar dados do Dashboard: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MyCash - Dashboard de Resultados</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/dashboard.css"> 
  <style>
    /* Estilo para o botão de filtrar sem quebrar o CSS base */
    .btn-filtrar {
      background-color: #8242a3;
      color: #ffffff;
      border: none;
      border-radius: 8px;
      padding: 6px 14px;
      font-family: 'Poppins', sans-serif;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.2s ease;
    }
    .btn-filtrar:hover { background-color: #5c4573; }
  </style>
</head>
<body>

  <header>
    <div class="logo-container">
      <i class="fa-solid fa-chart-line logo-icon"></i>
      <span>MyCash</span>
    </div>
    <div class="user-area">
      <a href="#" class="nav-link">Home</a>
      <i class="fa-solid fa-circle-user profile-icon"></i>
    </div>
  </header>

  <div class="app-container">
    <aside>
      <ul class="menu-list">
        <li>
          <a href="dashboard.php" class="menu-item active">
            <i class="fa-solid fa-table-cells-large"></i>
            <span>Resumo Financeiro</span>
          </a>
        </li>
        <li>
          <a href="home.php" class="menu-item">
            <i class="fa-regular fa-file-lines"></i>
            <span>Resumo administrativo</span>
          </a>
        </li>
        <li>
          <details class="menu-dropdown">
            <summary class="menu-item">
              <i class="fa-regular fa-building"></i>
              <span>Setores</span>
              <i class="fa-solid fa-chevron-down chevron"></i>
            </summary>
            <ul class="submenu-list">
              <?php foreach ($listaSetoresMenu as $setor): ?>
                <li><a href="setores/detalhes.php?id=<?= $setor['id_setor'] ?>" class="submenu-item"><?= htmlspecialchars($setor['nome']) ?></a></li>
              <?php endforeach; ?>
              <li><a href="setores/novo.php" class="submenu-item">Adicionar setor +</a></li>
            </ul>
          </details>
        </li>
        <li>
          <a href="#" class="menu-item">
            <i class="fa-solid fa-user-lock"></i>
            <span>Investimentos</span>
            <i class="fa-solid fa-chevron-down chevron"></i>
          </a>
        </li>
      </ul>
      <ul class="menu-list">
        <li>
          <a href="../actions/auth/logout.php" class="menu-item">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Sair</span>
          </a>
        </li>
      </ul>
    </aside>

    <main>
      <div class="dashboard-grid">
        <div class="left-section">
          
          <div class="kpi-container">
            <div class="kpi-card">
              <div class="kpi-label">Saldo Geral</div>
              <div class="kpi-value"><?= formatarMoeda($saldoGeral) ?></div>
            </div>
            <div class="kpi-card">
              <div class="kpi-label">Receitas (Efetivadas)</div>
              <div class="kpi-value" style="color: #2a0845;"><?= formatarMoeda($totalReceitas) ?></div>
            </div>
            <div class="kpi-card">
              <div class="kpi-label">Despesas (Efetivadas)</div>
              <div class="kpi-value" style="color: #8242a3;"><?= formatarMoeda($totalDespesas) ?></div>
            </div>
          </div>

          <div class="main-chart-card">
            <div class="chart-header">
              <h2>Evolução Financeira (Mês a Mês)</h2>
              
              <!-- FORMULÁRIO DE FILTRO ADICIONADO AQUI -->
              <form method="GET" action="dashboard.php" class="date-filter-container" style="margin: 0;">
                <label for="startDate"><i class="fa-regular fa-calendar"></i> Período:</label>
                <input type="date" id="startDate" name="data_inicio" class="date-input" value="<?= htmlspecialchars($dataInicio) ?>" required>
                <span style="font-size: 12px; color: #5c4573;">até</span>
                <input type="date" id="endDate" name="data_fim" class="date-input" value="<?= htmlspecialchars($dataFim) ?>" required>
                <button type="submit" class="btn-filtrar">Filtrar</button>
              </form>
            </div>

            <div class="chart-wrapper">
              <canvas id="mainChart"></canvas>
            </div>
          </div>

        </div>

        <div class="right-section">
          <div class="comparative-card">
            <h3>Comparativo Trimestre (<?= date('Y') ?>)</h3>
            <div class="small-chart-wrapper">
              <canvas id="compChart1"></canvas>
            </div>
          </div>
          <div class="comparative-card">
            <h3>Comparativo por Setor</h3>
            <div class="small-chart-wrapper">
              <canvas id="compChart2"></canvas>
            </div>
          </div>
        </div>

      </div>

      <div class="table-section">
        <div class="table-header">
          <h2>Compromissos Pendentes e Atrasados (No Período)</h2>
        </div>
        <div class="table-responsive">
          <table class="expense-table">
            <thead>
              <tr>
                <th>Setor</th>
                <th>Despesa (R$)</th>
                <th>Vencimento</th>
                <th>Pagar</th>
                <th>Visualizar</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($listaCompromissos) > 0): ?>
                <?php foreach ($listaCompromissos as $comp): ?>
                  <tr>
                    <td><?= htmlspecialchars($comp['setor']) ?></td>
                    <td><?= formatarMoeda($comp['valor']) ?></td>
                    <td><?= date('d/m/Y', strtotime($comp['data'])) ?></td>
                    <th>
                      <form action="../actions/compromissos/confirmar_pagamento.php" method="POST" style="margin: 0;">
                        <input type="hidden" name="id_compromisso" value="<?= $comp['id_compromisso'] ?>">
                        <button type="submit" style="cursor: pointer;">Pagar</button>
                      </form>
                    </th>
                    <th>
                      <a href="compromissos/detalhes.php?id=<?= $comp['id_compromisso'] ?>">
                        <button type="button" style="cursor: pointer;">Visualizar</button>
                      </a>
                    </th>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align: center; padding: 20px;">Nenhum compromisso pendente neste período.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <script>
    const labelsMain = <?= $jsonLabelsMain ?>;
    const recMain = <?= $jsonRecMain ?>;
    const despMain = <?= $jsonDespMain ?>;
    
    const labelsTri = ['1º TRI', '2º TRI', '3º TRI', '4º TRI'];
    const recTri = <?= $jsonRecTri ?>;
    const despTri = <?= $jsonDespTri ?>;

    const labelsSetor = <?= $jsonLabelsSetor ?>;
    const recSetor = <?= $jsonRecSetor ?>;
    const despSetor = <?= $jsonDespSetor ?>;

    const colorReceita = '#2a0845'; 
    const colorDespesa = '#8242a3'; 

    // 1. GRÁFICO PRINCIPAL
    const ctxMain = document.getElementById('mainChart').getContext('2d');
    new Chart(ctxMain, {
      type: 'bar',
      data: {
        labels: labelsMain,
        datasets: [
          { label: 'Receitas', data: recMain, backgroundColor: colorReceita, borderRadius: 4, barPercentage: 0.6, maxBarThickness: 60 },
          { label: 'Despesas', data: despMain, backgroundColor: colorDespesa, borderRadius: 4, barPercentage: 0.6, maxBarThickness: 60 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 25, font: { family: 'Poppins', size: 13 }, color: '#31134b' } }, tooltip: { mode: 'index', intersect: false } },
        scales: {
          x: { grid: { display: false }, ticks: { color: '#31134b', font: { family: 'Poppins', size: 13 } } },
          y: { beginAtZero: true, ticks: { color: '#31134b', font: { family: 'Poppins', size: 12 } }, grid: { color: '#c2dcd6', drawBorder: false } }
        }
      }
    });

    // 2. GRÁFICO TRIMESTRAL
    const ctxTri = document.getElementById('compChart1').getContext('2d');
    new Chart(ctxTri, {
      type: 'line',
      data: {
        labels: labelsTri,
        datasets: [
          { label: 'Receitas', data: recTri, borderColor: '#86efac', backgroundColor: '#86efac', pointRadius: 4, tension: 0.3, borderWidth: 2 },
          { label: 'Despesas', data: despTri, borderColor: '#f59e0b', backgroundColor: '#f59e0b', pointRadius: 4, tension: 0.3, borderWidth: 2 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { usePointStyle: true, color: '#ffffff', font: { family: 'Poppins', size: 11 } } } },
        scales: { x: { grid: { display: false }, ticks: { color: '#ffffff' } }, y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.15)' }, ticks: { color: '#ffffff' } } }
      }
    });

    // 3. GRÁFICO POR SETOR
    const ctxSetor = document.getElementById('compChart2').getContext('2d');
    new Chart(ctxSetor, {
      type: 'bar',
      data: {
        labels: labelsSetor,
        datasets: [
          { label: 'Receitas', data: recSetor, backgroundColor: '#86efac', borderRadius: 2, maxBarThickness: 30 },
          { label: 'Despesas', data: despSetor, backgroundColor: '#f59e0b', borderRadius: 2, maxBarThickness: 30 }
        ]
      },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { usePointStyle: true, color: '#ffffff', font: { family: 'Poppins', size: 11 } } } },
        scales: { x: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.15)' }, ticks: { color: '#ffffff' } }, y: { grid: { display: false }, ticks: { color: '#ffffff' } } }
      }
    });
  </script>
</body>
</html>