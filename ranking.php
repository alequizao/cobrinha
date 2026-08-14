<?php
// Ranking global por modo
require __DIR__ . '/config.php';
$u = exigirLogin();

$modos = modos();
$modo  = (string)($_GET['modo'] ?? 'classico');
if (!isset($modos[$modo])) $modo = 'classico';

$st = db()->prepare(
    'SELECT r.pontos, r.atualizado_em, us.id, us.nome, us.usuario, us.skin
       FROM recordes r JOIN usuarios us ON us.id = r.usuario_id
      WHERE r.modo = ? ORDER BY r.pontos DESC, r.atualizado_em ASC LIMIT 50'
);
$st->execute([$modo]);
$lista = $st->fetchAll();

$st = db()->prepare('SELECT COUNT(*)+1 FROM recordes a
                     WHERE a.modo = ? AND a.pontos > (SELECT COALESCE(MAX(b.pontos),-1) FROM recordes b
                                                       WHERE b.usuario_id = ? AND b.modo = ?)');
$st->execute([$modo, $u['id'], $modo]);
$minhaPos = (int)$st->fetchColumn();

$skins = skins();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Cobrinha — ranking</title>
<link rel="stylesheet" href="estilo.css?v=<?= APP_VERSAO ?>">
</head>
<body>
<div class="container">
  <div class="topo" style="margin-bottom:14px">
    <a class="chip" href="menu.php">← Menu</a>
    <span class="chip">🏆 Ranking</span>
  </div>

  <div class="card">
    <div class="abas" style="flex-wrap:wrap">
      <?php foreach ($modos as $id => $m): ?>
        <a href="ranking.php?modo=<?= e($id) ?>" style="flex:1;min-width:110px">
          <button type="button" class="<?= $id === $modo ? 'on' : '' ?>" style="width:100%"><?= e($m['nome']) ?></button>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (!$lista): ?>
      <p class="mudo" style="margin-top:16px">Ninguém pontuou nesse modo ainda. Seja o primeiro!</p>
    <?php else: ?>
      <table style="margin-top:14px">
        <thead><tr><th class="pos">#</th><th>Jogador</th><th style="text-align:right">Pontos</th></tr></thead>
        <tbody>
        <?php foreach ($lista as $i => $l):
            $s = $skins[$l['skin']] ?? $skins['verde']; ?>
          <tr class="<?= (int)$l['id'] === (int)$u['id'] ? 'eu' : '' ?>">
            <td class="pos"><?= $i < 3 ? ['🥇','🥈','🥉'][$i] : ($i+1) ?></td>
            <td>
              <span style="display:inline-block;width:12px;height:12px;border-radius:50%;vertical-align:-1px;margin-right:7px;background:linear-gradient(135deg,<?= e($s['cores'][0]) ?>,<?= e($s['cores'][1]) ?>)"></span>
              @<?= e($l['usuario']) ?>
            </td>
            <td style="text-align:right"><b><?= (int)$l['pontos'] ?></b></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="mudo" style="margin-top:12px">Sua posição neste modo: <b>#<?= $minhaPos ?></b></p>
    <?php endif; ?>
  </div>

  <a class="btn" href="<?= e($modos[$modo]['arquivo']) ?>?modo=<?= e($modo) ?>">Jogar <?= e($modos[$modo]['nome']) ?></a>
  <p class="rodape">v<?= APP_VERSAO ?></p>
</div>
</body>
</html>
