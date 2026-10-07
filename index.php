<?php
require_once __DIR__ . '/config/conexao.php';
$pagina = empty($_SESSION['admin_id']) ? 'autentificacao/login.php' : 'dashboard.php';
header('Location: pages/' . $pagina);
exit;
