<?php
// Configuração geral — Cobrinha
declare(strict_types=1);

const DB_HOST = 'SEU_VALOR_AQUI';
const DB_NAME = 'SEU_VALOR_AQUI';
const DB_USER = 'SEU_VALOR_AQUI';
const DB_PASS = 'SEU_VALOR_AQUI';

// Mesmo valor de SEGREDO em servidor/.env — assina o bilhete de entrada na arena online.
const ARENA_SEGREDO = '6f4277b464a2db7b2f3d47c0e903d626c0eb2aa431b7bbaa';

// REGRA DO PROJETO: toda alteração de código sobe a versão (veja CHANGELOG.md).
const APP_VERSAO = '1.9.0';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

function sessao(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('COBRINHA');
        session_start();
    }
}

function usuario(): ?array {
    sessao();
    if (empty($_SESSION['uid'])) return null;
    static $cache = null;
    if ($cache === null) {
        $st = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
        $st->execute([$_SESSION['uid']]);
        $cache = $st->fetch() ?: null;
        if (!$cache) { unset($_SESSION['uid']); }
    }
    return $cache ?: null;
}

function exigirLogin(): array {
    $u = usuario();
    if (!$u) { header('Location: index.php'); exit; }
    return $u;
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function token(): string {
    sessao();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function checaToken(?string $t): bool {
    sessao();
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

function json($dados, int $codigo = 200): void {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

// Catálogo de skins — tudo desbloqueável jogando, nada pago.
// padrao: solido | listras | bolinhas | anelado | escamas | zebra | neon
function skins(): array {
    return [
        'verde'    => ['nome' => 'Clássica',   'cores' => ['#4ade80','#16a34a'], 'padrao' => 'solido',   'preco' => 0],
        'azul'     => ['nome' => 'Oceano',     'cores' => ['#60a5fa','#1d4ed8'], 'padrao' => 'listras',  'preco' => 50],
        'abelha'   => ['nome' => 'Abelha',     'cores' => ['#fbbf24','#111827'], 'padrao' => 'listras',  'preco' => 90],
        'fogo'     => ['nome' => 'Fogo',       'cores' => ['#fbbf24','#dc2626'], 'padrao' => 'anelado',  'preco' => 120],
        'roxo'     => ['nome' => 'Neon Roxo',  'cores' => ['#c084fc','#7c3aed'], 'padrao' => 'neon',     'preco' => 200],
        'onca'     => ['nome' => 'Onça',       'cores' => ['#fcd34d','#78350f'], 'padrao' => 'bolinhas', 'preco' => 250],
        'rosa'     => ['nome' => 'Chiclete',   'cores' => ['#f9a8d4','#db2777'], 'padrao' => 'bolinhas', 'preco' => 300],
        'zebra'    => ['nome' => 'Zebra',      'cores' => ['#f8fafc','#0f172a'], 'padrao' => 'zebra',    'preco' => 320],
        'dragao'   => ['nome' => 'Dragão',     'cores' => ['#34d399','#065f46'], 'padrao' => 'escamas',  'preco' => 450],
        'ouro'     => ['nome' => 'Ouro',       'cores' => ['#fde047','#ca8a04'], 'padrao' => 'escamas',  'preco' => 500],
        'cobra'    => ['nome' => 'Naja',       'cores' => ['#a3e635','#3f6212'], 'padrao' => 'anelado',  'preco' => 550],
        'gelo'     => ['nome' => 'Gelo',       'cores' => ['#a5f3fc','#0e7490'], 'padrao' => 'neon',     'preco' => 700],
        'arcoiris' => ['nome' => 'Arco-íris',  'cores' => ['#ff0080','#00e0ff'], 'padrao' => 'solido',   'preco' => 800, 'arcoiris' => true],
        'sombra'   => ['nome' => 'Sombra',     'cores' => ['#94a3b8','#0f172a'], 'padrao' => 'zebra',    'preco' => 1000],
        'galaxia'  => ['nome' => 'Galáxia',    'cores' => ['#818cf8','#1e1b4b'], 'padrao' => 'escamas',  'preco' => 1500],
    ];
}

// Acessórios — chapéus, asas e até montaria, igual ao original.
function acessorios(): array {
    return [
        ''          => ['nome' => 'Nenhum',       'emoji' => '🚫', 'preco' => 0],
        'chapeu'    => ['nome' => 'Chapéu',       'emoji' => '🧢', 'preco' => 80],
        'oculos'    => ['nome' => 'Óculos',       'emoji' => '🕶️', 'preco' => 150],
        'laco'      => ['nome' => 'Laço',         'emoji' => '🎀', 'preco' => 250],
        'chifres'   => ['nome' => 'Chifrinhos',   'emoji' => '😈', 'preco' => 350],
        'coroa'     => ['nome' => 'Coroa',        'emoji' => '👑', 'preco' => 400],
        'cartola'   => ['nome' => 'Cartola',      'emoji' => '🎩', 'preco' => 600],
        'asas'      => ['nome' => 'Asas',         'emoji' => '🪽', 'preco' => 1200],
        'asas_fogo' => ['nome' => 'Asas de fogo', 'emoji' => '🔥', 'preco' => 2000],
        'aura'      => ['nome' => 'Aura de fogo', 'emoji' => '✨', 'preco' => 900],
        'prancha'   => ['nome' => 'Montaria: prancha', 'emoji' => '🛹', 'preco' => 1600],
        'foguete'   => ['nome' => 'Montaria: foguete', 'emoji' => '🚀', 'preco' => 2500],
    ];
}

// Bilhete assinado que o navegador entrega ao servidor da arena (Node).
function bilheteArena(array $u): string {
    $skins = skins();
    $s = $skins[$u['skin']] ?? $skins['verde'];
    $corpo = rtrim(strtr(base64_encode(json_encode([
        'uid'   => (int)$u['id'],
        'nome'  => $u['usuario'],
        'cores' => $s['cores'],
        'p'     => $s['padrao'] ?? 'solido',
        'ai'    => !empty($s['arcoiris']),
        'ac'    => (string)($u['acessorio'] ?? ''),
        'exp'   => time() + 300,
    ], JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    return $corpo . '.' . hash_hmac('sha256', $corpo, ARENA_SEGREDO);
}

function modos(): array {
    return [
        'online'    => ['nome' => 'Snake.io',   'desc' => 'AO VIVO: todo mundo na mesma arena!',  'mult' => 1.0, 'arquivo' => 'online.php'],
        'arena'     => ['nome' => 'Treino',     'desc' => 'A mesma arena, mas só contra o robô.', 'mult' => 1.0, 'arquivo' => 'arena.php'],
        'classico'  => ['nome' => 'Clássico',   'desc' => 'Bateu na parede, acabou.',            'mult' => 1.0, 'arquivo' => 'jogo.php'],
        'infinito'  => ['nome' => 'Sem Paredes','desc' => 'Atravessa as bordas do mapa.',        'mult' => 0.8, 'arquivo' => 'jogo.php'],
        'turbo'     => ['nome' => 'Turbo',      'desc' => 'Bem mais rápido, pontos em dobro.',   'mult' => 2.0, 'arquivo' => 'jogo.php'],
        'obstaculo' => ['nome' => 'Labirinto',  'desc' => 'Mapa com blocos no caminho.',         'mult' => 1.5, 'arquivo' => 'jogo.php'],
    ];
}
