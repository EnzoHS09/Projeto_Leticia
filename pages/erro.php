<?php
ini_set('display_errors', '0');

$mensagem = (isset($_GET['msg']) && is_string($_GET['msg'])) ? $_GET['msg'] : 'Ocorreu um erro interno ou uma operação inválida no sistema.';
$link_voltar = (isset($_GET['link']) && is_string($_GET['link'])) ? $_GET['link'] : 'dashboard.php';
$retorno = parse_url($link_voltar);
$paginas = ['dashboard.php', 'historico.php', 'autentificacao/login.php', 'setores/detalhes.php',
    '../pages/dashboard.php', '../pages/historico.php', '../pages/setores/detalhes.php',
    '/Projeto_Leticia/pages/dashboard.php', '/Projeto_Leticia/pages/historico.php',
    '/Projeto_Leticia/pages/setores/detalhes.php', '/Projeto_Leticia/pages/autentificacao/login.php'];
if ($retorno === false || isset($retorno['host']) || isset($retorno['scheme'])
    || strpbrk($link_voltar, "\r\n\\\\") !== false || !in_array($retorno['path'] ?? '', $paginas, true)) {
    $link_voltar = 'dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aviso do Sistema - MyCash</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    body {
        background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        color: #31134b;
    }
    .error-card {
        background: #ffffff;
        width: 90%;
        max-width: 450px;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
        text-align: center;
        border-top: 6px solid #ef4444;
    }
    .error-icon {
        font-size: 65px;
        color: #31134b;;
        margin-bottom: 20px;
        animation: pulse 2s infinite;
    }
    .error-title {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 15px;
        color: #1e293b;
    }
    .error-message {
        font-size: 14px;
        color: #64748b;
        line-height: 1.6;
        margin-bottom: 30px;
        padding: 15px;
        background: #f8fafc;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }
    .btn-voltar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        background: #8242a3;
        color: #ffffff;
        text-decoration: none;
        padding: 12px 25px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s ease;
        width: 100%;
    }
    .btn-voltar:hover {
        background: #6b338a;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(130, 66, 163, 0.3);
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
  </style>
</head>
<body>

  <div class="error-card">
    <div class="error-icon">
        <i class="fa-solid fa-triangle-exclamation"></i>
    </div>
    
    <h1 class="error-title">Ação Interrompida</h1>
    
    <div class="error-message">
        <?= htmlspecialchars($mensagem) ?>
    </div>
    
    <a href="<?= htmlspecialchars($link_voltar) ?>" class="btn-voltar">
        <i class="fa-solid fa-arrow-left"></i> Voltar ao Sistema
    </a>
  </div>

</body>
</html>
