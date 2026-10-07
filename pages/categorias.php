<?php
require_once __DIR__ . '/../config/conexao.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: autentificacao/login.php');
    exit;
}
require_once __DIR__ . '/../crud/crud_categorias.php';

try {
    $categorias = listarCategorias();
} catch (Throwable $e) {
    error_log($e->getMessage());
    exit('Nao foi possivel carregar as categorias. Tente novamente mais tarde.');
}

$mensagens = [
    'criada' => 'Categoria criada com sucesso.',
    'editada' => 'Categoria atualizada com sucesso.',
    'ativada' => 'Categoria reativada com sucesso.',
    'desativada' => 'Categoria desativada com sucesso.',
];
$ok = isset($_GET['ok']) && is_string($_GET['ok']) ? $_GET['ok'] : '';
$erro = isset($_GET['erro']) && is_string($_GET['erro']) ? $_GET['erro'] : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Categorias - MyCash</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/dashboard.css">
  <link rel="stylesheet" href="partial/sidebar.css">
  <link rel="stylesheet" href="assets/setores.css">
</head>
<body>
  <header>
    <div class="logo-container">
      <button id="mobile-menu-btn" class="mobile-menu-btn" type="button" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
      <i class="fa-solid fa-chart-line logo-icon"></i><span>MyCash</span>
    </div>
    <div class="user-area"><a href="dashboard.php" class="nav-link">Voltar ao Dashboard</a></div>
  </header>

  <div class="app-container">
    <?php include __DIR__ . '/partial/sidebar.php'; ?>
    <main>
      <div class="page-header">
        <div>
          <h2><i class="fa-solid fa-tags"></i> Categorias</h2>
          <p>Cadastre e organize as categorias de receitas e despesas.</p>
        </div>
        <button type="button" class="btn-novo-setor" onclick="abrirCategoria()"><i class="fa-solid fa-plus"></i> Nova categoria</button>
      </div>

      <?php if (isset($mensagens[$ok])): ?>
        <div class="table-section" style="margin-top:0; padding:14px 20px; color:#0d7a46;"><?= htmlspecialchars($mensagens[$ok], ENT_QUOTES, 'UTF-8') ?></div>
      <?php elseif ($erro !== ''): ?>
        <div class="table-section" style="margin-top:0; padding:14px 20px; color:#c71f1f;">Confira os dados informados e tente novamente.</div>
      <?php endif; ?>

      <div class="table-section">
        <div class="table-responsive">
          <table class="data-table">
            <thead><tr><th>Nome</th><th>Tipo</th><th>Status</th><th>Acoes</th></tr></thead>
            <tbody>
              <?php foreach ($categorias as $categoria): ?>
                <tr>
                  <td class="fw-600"><?= htmlspecialchars($categoria['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= $categoria['tipo'] === 'RECEITA' ? 'Receita' : 'Despesa' ?></td>
                  <td><span class="badge <?= $categoria['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>"><?= $categoria['ativo'] ? 'Ativa' : 'Inativa' ?></span></td>
                  <td class="td-actions">
                    <button type="button" class="btn-acao btn-editar" onclick="abrirCategoria(<?= (int) $categoria['id_categoria'] ?>, <?= htmlspecialchars(json_encode($categoria['nome'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, '<?= $categoria['tipo'] ?>')"><i class="fa-solid fa-pen"></i> Editar</button>
                    <form action="../actions/categoria_status.php" method="POST">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                      <input type="hidden" name="id_categoria" value="<?= (int) $categoria['id_categoria'] ?>">
                      <input type="hidden" name="status" value="<?= $categoria['ativo'] ? 'desativar' : 'ativar' ?>">
                      <button type="submit" class="btn-acao <?= $categoria['ativo'] ? 'btn-editar' : 'btn-ver' ?>"><?= $categoria['ativo'] ? 'Desativar' : 'Reativar' ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($categorias)): ?><tr><td colspan="4">Nenhuma categoria cadastrada.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <div id="modalCategoria" class="modal-overlay">
    <div class="modal-content modal-sm">
      <div class="modal-header">
        <h3 id="tituloCategoria">Nova Categoria</h3>
        <button type="button" class="close-modal" onclick="fecharCategoria()"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form action="../actions/categoria_salvar.php" method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="modo" id="categoria_modo" value="criar">
        <input type="hidden" name="id_categoria" id="categoria_id" value="">
        <div class="form-group">
          <label for="categoria_nome">Nome</label>
          <input type="text" class="form-control" name="nome" id="categoria_nome" maxlength="100" required>
        </div>
        <div class="form-group">
          <label for="categoria_tipo">Tipo</label>
          <select class="form-control" name="tipo" id="categoria_tipo" required>
            <option value="RECEITA">Receita</option>
            <option value="DESPESA">Despesa</option>
          </select>
        </div>
        <button type="submit" class="btn-sidebar-primary">Salvar Categoria</button>
      </form>
    </div>
  </div>

  <script>
    function abrirCategoria(id = '', nome = '', tipo = 'RECEITA') {
      document.getElementById('categoria_modo').value = id ? 'editar' : 'criar';
      document.getElementById('categoria_id').value = id;
      document.getElementById('categoria_nome').value = nome;
      document.getElementById('categoria_tipo').value = tipo;
      document.getElementById('tituloCategoria').textContent = id ? 'Editar Categoria' : 'Nova Categoria';
      document.getElementById('modalCategoria').style.display = 'flex';
    }
    function fecharCategoria() {
      document.getElementById('modalCategoria').style.display = 'none';
    }
  </script>
</body>
</html>
