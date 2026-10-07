<?php
// pages/perfil/perfil.php


// Voltar duas pastas (../../) para encontrar o config
require_once __DIR__ . '/../../config/conexao.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: ../autentificacao/login.php');
    exit;
}


$id_admin = $_SESSION['admin_id'];
$sucesso = (isset($_GET['sucesso']) && is_string($_GET['sucesso'])) ? $_GET['sucesso'] : 0;
$erro = (isset($_GET['erro']) && is_string($_GET['erro'])) ? $_GET['erro'] : '';

try {
    $stmt = $pdo->prepare("SELECT nome, email FROM administradores WHERE id_admin = :id");
    $stmt->execute(['id' => $id_admin]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        die("Erro: Administrador não encontrado na base de dados.");
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
  <title>Meu Perfil - MyCash</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <link rel="stylesheet" href="../assets/dashboard.css">
  <link rel="stylesheet" href="../partial/sidebar.css">
  <link rel="stylesheet" href="../assets/perfil.css">
</head>
<body>

  <header>
    <div class="logo-container">
      <button id="mobile-menu-btn" class="mobile-menu-btn" type="button" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
      <i class="fa-solid fa-chart-line logo-icon"></i><span>MyCash</span>
    </div>
    <div class="user-area">
    
    <span style="font-size: 14px; margin-right: 15px;">Olá, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
        <a href="../dashboard.php" class="nav-link">Voltar ao Dashboard</a>
    </div>
  </header>

  <div class="app-container">
    <?php include __DIR__ . '/../partial/sidebar.php'; ?>
    
    <main>
      <div class="page-header">
        <div>
          <h2><i class="fa-solid fa-user-gear"></i> Meu Perfil</h2>
          <p>Atualize os seus dados pessoais e credenciais de acesso ao sistema.</p>
        </div>
      </div>

      <div class="profile-card">
        
        <?php if ($sucesso == 1): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> Perfil atualizado com sucesso!</div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
            <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <div class="profile-icon-large"><i class="fa-solid fa-user"></i></div>

        <form action="../../actions/atualizar_perfil.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            
            <div class="form-section-title">Informações Básicas</div>
            
            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($admin['nome']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>E-mail de Acesso</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin['email']) ?>" required>
            </div>

            <div class="form-section-title">Segurança (Opcional)</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nova Senha <span class="info-text">Deixe em branco para manter a atual.</span></label>
                    <input type="password" name="nova_senha" class="form-control" placeholder="Digite a nova senha">
                </div>
                
                <div class="form-group">
                    <label>Confirmar Nova Senha <span class="info-text">Repita a senha para confirmar.</span></label>
                    <input type="password" name="confirmar_senha" class="form-control" placeholder="Repita a nova senha">
                </div>
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 20px;">
                <i class="fa-solid fa-floppy-disk" style="margin-right: 8px;"></i> Guardar Alterações
            </button>
            
            <div style="text-align: center; margin-top: 15px;">
                <a href="cadastro.php" style="color: #64748b; font-size: 13px; text-decoration: none; font-weight: 500;">Criar nova conta de Administrador</a>
            </div>
        </form>
      </div>
    </main>
  </div>
</body>
</html>
