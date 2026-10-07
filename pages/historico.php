<?php
// pages/historico.php


require_once __DIR__ . '/../config/conexao.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: autentificacao/login.php');
    exit;
}
require_once __DIR__ . '/../crud/crud_administradores.php';



function formatarMoeda($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

$filtro_busca  = (isset($_GET['busca']) && is_string($_GET['busca'])) ? $_GET['busca'] : '';
$filtro_inicio = (isset($_GET['data_inicio']) && is_string($_GET['data_inicio'])) ? $_GET['data_inicio'] : '';
$filtro_fim    = (isset($_GET['data_fim']) && is_string($_GET['data_fim'])) ? $_GET['data_fim'] : '';
$filtro_tipo   = (isset($_GET['tipo']) && is_string($_GET['tipo'])) ? $_GET['tipo'] : '';

try {
    $sql = "
        SELECT m.id_movimentacao, m.tipo, m.valor, m.criado_em, m.descricao, m.status, 
               a.nome AS admin_nome,
               (EXISTS (SELECT 1 FROM receitas r WHERE r.id_movimentacao_fk = m.id_movimentacao)
                OR EXISTS (SELECT 1 FROM despesas d WHERE d.id_movimentacao_fk = m.id_movimentacao)
                OR EXISTS (SELECT 1 FROM transferencias t WHERE t.id_movimentacao_fk = m.id_movimentacao)) AS possui_registro
        FROM movimentacoes m
        LEFT JOIN administradores a ON m.id_admin_fk = a.id_admin
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filtro_busca)) {
        $sql .= " AND m.descricao LIKE :busca ";
        $params[':busca'] = "%$filtro_busca%";
    }
    if (!empty($filtro_inicio) && !empty($filtro_fim)) {
        $sql .= " AND DATE(m.criado_em) BETWEEN :inicio AND :fim ";
        $params[':inicio'] = $filtro_inicio;
        $params[':fim'] = $filtro_fim;
    }
    if (!empty($filtro_tipo)) {
        $sql .= " AND m.tipo = :tipo ";
        $params[':tipo'] = $filtro_tipo;
    }

    $sql .= " ORDER BY m.criado_em DESC LIMIT 150";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log($e->getMessage());
    $msg_url = urlencode('Não foi possível carregar o histórico. Tente novamente mais tarde.');
    header("Location: erro.php?msg={$msg_url}&link=dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Histórico de Ações - MyCash</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <link rel="stylesheet" href="assets/dashboard.css">
  <link rel="stylesheet" href="partial/sidebar.css">
  <link rel="stylesheet" href="assets/historico.css">
</head>
<body>

  <header>
    <div class="logo-container">
      <button id="mobile-menu-btn" class="mobile-menu-btn" type="button" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
      <i class="fa-solid fa-chart-line logo-icon"></i><span>MyCash</span>
    </div>
    <div class="user-area">
        <a href="dashboard.php" class="nav-link">Voltar ao Dashboard</a>
    </div>
  </header>

  <div class="app-container">
    <?php include __DIR__ . '/partial/sidebar.php'; ?>
    
    <main>
      <div class="page-header">
        <div>
          <h2><i class="fa-solid fa-clock-rotate-left"></i> Histórico de Ações</h2>
          <p>Auditoria central. Visualize ou desfaça movimentações financeiras no sistema.</p>
        </div>
        
        <form method="GET" class="table-filter-container">
            <input type="text" name="busca" class="filter-input" placeholder="Pesquisar descrição..." value="<?= htmlspecialchars($filtro_busca) ?>">
            
            <select name="tipo" class="filter-input">
                <option value="">Todos os Tipos</option>
                <option value="RECEITA" <?= $filtro_tipo == 'RECEITA' ? 'selected' : '' ?>>Receitas</option>
                <option value="DESPESA" <?= $filtro_tipo == 'DESPESA' ? 'selected' : '' ?>>Despesas</option>
                <option value="DISTRIBUICAO" <?= $filtro_tipo == 'DISTRIBUICAO' ? 'selected' : '' ?>>Transferências</option>
            </select>
            
            <label>De: <input type="date" name="data_inicio" class="filter-input" value="<?= htmlspecialchars($filtro_inicio) ?>"></label>
            <label>Até: <input type="date" name="data_fim" class="filter-input" value="<?= htmlspecialchars($filtro_fim) ?>"></label>
            
            <button type="submit" class="btn-filtrar btn-sm">Filtrar</button>
            <?php if($filtro_busca || $filtro_inicio || $filtro_tipo): ?>
                <a href="historico.php" class="btn-filtrar btn-sm btn-limpar">Limpar</a>
            <?php endif; ?>
        </form>
      </div>

      <div class="history-table-wrapper">
        <table class="history-table">
            <thead>
                <tr>
                    <th>Data e Hora</th>
                    <th>Autor (Admin)</th>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($historico as $mov): ?>
                    <tr>
                        <td style="font-size: 13px; color: #64748b;">
                            <?= date('d/m/Y \à\s H:i', strtotime($mov['criado_em'])) ?>
                        </td>
                        
                        <td style="font-weight: 500;">
                            <?= htmlspecialchars($mov['admin_nome'] ?? 'Sistema') ?>
                        </td>
                        
                        <td>
                            <span class="tipo-badge">
                                <?= htmlspecialchars($mov['tipo']) ?>
                            </span>
                        </td>
                        
                        <td><?= htmlspecialchars($mov['descricao']) ?></td>
                        
                        <td class="<?= $mov['tipo'] == 'DESPESA' ? 'valor-negativo' : 'valor-positivo' ?>">
                            <?= $mov['tipo'] == 'DESPESA' ? '-' : '+' ?> <?= formatarMoeda($mov['valor']) ?>
                        </td>
                        
                        <td>
                            <?php if($mov['status'] === 'ATIVA'): ?>
                                <span class="status-badge status-efetivada"><i class="fa-solid fa-check"></i> Efetivada</span>
                            <?php else: ?>
                                <span class="status-badge status-estornada"><i class="fa-solid fa-xmark"></i> Estornada</span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <?php if($mov['status'] === 'ATIVA' && $mov['tipo'] !== 'ESTORNO' && $mov['possui_registro']): ?>
                                <form action="../actions/estornar_movimentacao.php" method="POST" style="margin:0;" onsubmit="return confirm('Tem a certeza que deseja desfazer esta operação? Os saldos serão devolvidos.')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id_movimentacao" value="<?= $mov['id_movimentacao'] ?>">
                                    <button type="submit" class="btn-estorno" title="Desfazer operação e devolver dinheiro">
                                        <i class="fa-solid fa-rotate-left"></i> Desfazer
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-anulada"><i class="fa-solid fa-ban"></i> <?= $mov['tipo'] === 'ESTORNO' ? 'Estorno registrado' : ($mov['status'] === 'ESTORNADA' ? 'Anulada' : 'Conferir vínculo') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if(empty($historico)) echo "<tr><td colspan='7' style='text-align:center; padding: 40px; color:#94a3b8;'>Nenhum registo de auditoria encontrado.</td></tr>"; ?>
            </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>
