<?php
// Apoio aos testes, não é carregado pela aplicação.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
$porta = getenv('MY_CASH_DB_PORT');
$banco = getenv('MY_CASH_DB_NAME');
$base = getenv('MY_CASH_TEST_URL');
if (!$porta || (int) $porta === 3306 || !$banco || !preg_match('/^[a-z0-9_]+_test$/', $banco)
    || !$base || parse_url($base, PHP_URL_HOST) !== '127.0.0.1'
    || !parse_url($base, PHP_URL_PORT) || in_array((int) parse_url($base, PHP_URL_PORT), [80, 443], true)) {
    exit("Use um servidor isolado, banco terminado em _test e portas diferentes das originais.\n");
}
$servidor = new PDO('mysql:host=127.0.0.1;port=' . $porta . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$consulta = $servidor->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
$consulta->execute([$banco]);
if ($consulta->fetchColumn()) {
    exit("O banco de teste já existe. Não será apagado ou recriado. Use outra cópia isolada.\n");
}
$sql = str_replace('my_cash', $banco, file_get_contents(__DIR__ . '/../banco_my_cash.sql'));
$servidor->exec($sql);
$pdo = new PDO('mysql:host=127.0.0.1;port=' . $porta . ';dbname=' . $banco . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$curl = curl_init();
curl_setopt_array($curl, [CURLOPT_COOKIEFILE => '', CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
$falhas = 0;
$testes = 0;

function verificar(bool $ok, string $nome): void
{
    global $falhas, $testes;
    $testes++;
    $falhas += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . $nome . "\n";
}

function requisicao(string $pagina, ?array $dados = null, bool $preencherEnvio = true): array
{
    global $curl, $base;
    $cadastros = ['salvar_lancamento_completo.php', 'salvar_transacao.php', 'realocar_saldo.php', 'recolher_saldo.php', 'transferir_entre_setores.php'];
    if ($preencherEnvio && $dados !== null && isset($dados['csrf']) && !isset($dados['envio']) && in_array(basename($pagina), $cadastros, true)) {
        $formulario = requisicao('pages/dashboard.php');
        if (preg_match('/name="envio" value="([a-f0-9]+)"/', $formulario['html'], $numero)) $dados['envio'] = $numero[1];
    }
    curl_setopt($curl, CURLOPT_URL, rtrim($base, '/') . '/' . ltrim($pagina, '/'));
    curl_setopt($curl, CURLOPT_POST, $dados !== null);
    if ($dados !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($dados));
    }
    $resposta = curl_exec($curl);
    if ($resposta === false) {
        throw new RuntimeException(curl_error($curl));
    }
    $tamanho = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($resposta, 0, $tamanho);
    preg_match('/^Location:\s*(.+)$/mi', $headers, $location);
    return ['status' => curl_getinfo($curl, CURLINFO_HTTP_CODE), 'headers' => $headers,
        'location' => trim($location[1] ?? ''), 'html' => substr($resposta, $tamanho)];
}

function csrf(string $pagina = 'pages/dashboard.php'): string
{
    $resposta = requisicao($pagina);
    if (!preg_match('/name="csrf" value="([a-f0-9]+)"/', $resposta['html'], $token)) {
        throw new RuntimeException('Token não encontrado em ' . $pagina);
    }
    return $token[1];
}

function estadoBanco(): string
{
    global $pdo;
    $estado = [];
    foreach (['administradores', 'categorias', 'setores', 'saldo_geral', 'movimentacoes', 'receitas', 'despesas', 'transferencias', 'contas_receber', 'compromissos'] as $tabela) {
        $estado[$tabela] = $pdo->query('SELECT * FROM ' . $tabela . ' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC);
    }
    return hash('sha256', serialize($estado));
}
