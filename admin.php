<?php
// Painel do administrador
require __DIR__ . '/config.php';
$u = exigirLogin();
if ((int)($u['admin'] ?? 0) !== 1) { http_response_code(403); exit('Acesso restrito ao administrador.'); }

$aviso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checaToken($_POST['csrf'] ?? null)) {
    $acao = (string)($_POST['acao'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);

    if ($acao === 'moedas' && $id) {
        $qtd = (int)($_POST['qtd'] ?? 0);
        db()->prepare('UPDATE usuarios SET moedas = GREATEST(0, moedas + ?) WHERE id = ?')->execute([$qtd, $id]);
        $aviso = 'ok|Moedas ajustadas em ' . ($qtd >= 0 ? '+' : '') . $qtd . '.';
    } elseif ($acao === 'definir_moedas' && $id) {
        $valor = max(0, min(99999999, (int)($_POST['valor'] ?? 0)));
        db()->prepare('UPDATE usuarios SET moedas = ? WHERE id = ?')->execute([$valor, $id]);
        $aviso = 'ok|Saldo definido em ' . $valor . ' moedas.';
    } elseif ($acao === 'liberar' && $id) {
        $ins = db()->prepare('INSERT IGNORE INTO skins_usuario (usuario_id, skin) VALUES (?,?)');
        foreach (array_keys(skins()) as $s) $ins->execute([$id, $s]);
        $aviso = 'ok|Todas as skins liberadas para o jogador.';
    } elseif ($acao === 'zerar' && $id) {
        db()->prepare('DELETE FROM recordes WHERE usuario_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM partidas WHERE usuario_id = ?')->execute([$id]);
        $aviso = 'ok|Histórico e recordes zerados.';
    } elseif ($acao === 'excluir' && $id && $id !== (int)$u['id']) {
        db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
        $aviso = 'ok|Jogador excluído.';
    } elseif ($acao === 'senha' && $id) {
        $nova = (string)($_POST['nova'] ?? '');
        if (strlen($nova) < 4) {
            $aviso = 'erro|Senha muito curta.';
        } else {
            db()->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')
                ->execute([password_hash($nova, PASSWORD_DEFAULT), $id]);
            $aviso = 'ok|Senha redefinida.';
        }
    }
}

$busca = trim((string)($_GET['q'] ?? ''));
if ($busca !== '') {
    $st = db()->prepare('SELECT * FROM usuarios WHERE usuario LIKE ? OR nome LIKE ? ORDER BY id DESC LIMIT 100');
    $st->execute(['%' . $busca . '%', '%' . $busca . '%']);
} else {
    $st = db()->query('SELECT * FROM usuarios ORDER BY id DESC LIMIT 100');
}
$jogadores = $st->fetchAll();

$tot = db()->query('SELECT
    (SELECT COUNT(*) FROM usuarios) u,
    (SELECT COUNT(*) FROM partidas) p,
    (SELECT COALESCE(MAX(pontos),0) FROM recordes) m')->fetch();

$ultimas = db()->query(
   'SELECT p.*, us.usuario FROM partidas p JOIN usuarios us ON us.id = p.usuario_id
    ORDER BY p.id DESC LIMIT 20')->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cobrinha — admin</title>
<link rel="stylesheet" href="estilo.css?v=<?= APP_VERSAO ?>">
</head>
<body>
<div class="container">
  <div class="topo" style="margin-bottom:14px">
    <div style="font-size:20px;font-weight:700">⚙️ Painel do administrador</div>
    <div style="display:flex;gap:8px"><a class="chip" href="menu.php">Jogar</a><a class="chip" href="sair.php">Sair</a></div>
  </div>

  <?php if ($aviso): [$t,$m] = explode('|', $aviso, 2); ?>
    <div class="<?= $t==='ok'?'ok':'erro' ?>"><?= e($m) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="grade">
      <div class="item"><div class="nome"><?= (int)$tot['u'] ?></div><div class="info">jogadores</div></div>
      <div class="item"><div class="nome"><?= (int)$tot['p'] ?></div><div class="info">partidas</div></div>
      <div class="item"><div class="nome"><?= (int)$tot['m'] ?></div><div class="info">maior pontuação</div></div>
    </div>
  </div>

  <div class="card">
    <h2>Jogadores</h2>
    <form method="get" style="display:flex;gap:8px;align-items:flex-end">
      <div style="flex:1"><input name="q" placeholder="Buscar por usuário ou nome" value="<?= e($busca) ?>"></div>
      <button class="btn peq">Buscar</button>
    </form>

    <div style="overflow-x:auto">
    <table style="margin-top:12px;min-width:640px">
      <thead><tr><th>#</th><th>Jogador</th><th>Moedas</th><th>Ações</th></tr></thead>
      <tbody>
      <?php foreach ($jogadores as $j): ?>
        <tr class="<?= (int)$j['id'] === (int)$u['id'] ? 'eu' : '' ?>">
          <td class="pos"><?= (int)$j['id'] ?></td>
          <td>
            <?= e($j['nome']) ?> <span class="mudo">@<?= e($j['usuario']) ?></span>
            <?= (int)$j['admin'] === 1 ? ' <span class="chip" style="padding:2px 8px;font-size:11px">admin</span>' : '' ?>
            <div class="mudo" style="font-size:11px">desde <?= date('d/m/Y', strtotime($j['criado_em'])) ?></div>
          </td>
          <td><?= (int)$j['moedas'] ?></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
              <form method="post" style="display:flex;gap:6px" title="Somar/subtrair moedas">
                <input type="hidden" name="csrf" value="<?= e(token()) ?>">
                <input type="hidden" name="acao" value="moedas">
                <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                <input name="qtd" type="number" value="100" style="width:82px;padding:7px" placeholder="+/-">
                <button class="btn peq sec">± 🪙</button>
              </form>
              <form method="post" style="display:flex;gap:6px" title="Definir o saldo exato">
                <input type="hidden" name="csrf" value="<?= e(token()) ?>">
                <input type="hidden" name="acao" value="definir_moedas">
                <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                <input name="valor" type="number" min="0" value="<?= (int)$j['moedas'] ?>" style="width:96px;padding:7px">
                <button class="btn peq sec">= 🪙</button>
              </form>
              <form method="post" style="display:flex;gap:6px">
                <input type="hidden" name="csrf" value="<?= e(token()) ?>">
                <input type="hidden" name="acao" value="senha">
                <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                <input name="nova" placeholder="nova senha" style="width:120px;padding:7px">
                <button class="btn peq sec">🔑</button>
              </form>
              <form method="post" onsubmit="return confirm('Liberar todas as skins?')">
                <input type="hidden" name="csrf" value="<?= e(token()) ?>">
                <input type="hidden" name="acao" value="liberar">
                <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                <button class="btn peq sec" title="Liberar todas as skins">🎨</button>
              </form>
              <form method="post" onsubmit="return confirm('Zerar partidas e recordes deste jogador?')">
                <input type="hidden" name="csrf" value="<?= e(token()) ?>">
                <input type="hidden" name="acao" value="zerar">
                <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                <button class="btn peq sec" title="Zerar histórico">♻️</button>
              </form>
              <?php if ((int)$j['id'] !== (int)$u['id']): ?>
              <form method="post" onsubmit="return confirm('Excluir o jogador @<?= e($j['usuario']) ?> e todos os dados dele?')">
                <input type="hidden" name="csrf" value="<?= e(token()) ?>">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                <button class="btn peq sec" style="color:#fca5a5" title="Excluir">🗑</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <div class="card">
    <h2>Últimas partidas</h2>
    <div style="overflow-x:auto">
    <table style="min-width:520px">
      <thead><tr><th>Quando</th><th>Jogador</th><th>Modo</th><th>Pontos</th><th>Tam.</th><th>Tempo</th></tr></thead>
      <tbody>
      <?php foreach ($ultimas as $p): ?>
        <tr>
          <td class="mudo"><?= date('d/m H:i', strtotime($p['criado_em'])) ?></td>
          <td>@<?= e($p['usuario']) ?></td>
          <td><?= e(modos()[$p['modo']]['nome'] ?? $p['modo']) ?></td>
          <td><b><?= (int)$p['pontos'] ?></b></td>
          <td><?= (int)$p['tamanho'] ?></td>
          <td><?= (int)$p['duracao'] ?>s</td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$ultimas): ?><tr><td colspan="6" class="mudo">Nenhuma partida ainda.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

  <p class="rodape">v<?= APP_VERSAO ?></p>
</div>
</body>
</html>
