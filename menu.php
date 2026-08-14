<?php
// Menu principal: escolher modo, skins e ver estatísticas
require __DIR__ . '/config.php';
$u = exigirLogin();

$aviso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checaToken($_POST['csrf'] ?? null)) {
    $skin  = (string)($_POST['skin'] ?? '');
    $skins = skins();
    if (isset($skins[$skin])) {
        $st = db()->prepare('SELECT 1 FROM skins_usuario WHERE usuario_id = ? AND skin = ?');
        $st->execute([$u['id'], $skin]);
        $tem = (bool)$st->fetch();

        if (!$tem) {
            $preco = (int)$skins[$skin]['preco'];
            if ((int)$u['moedas'] < $preco) {
                $aviso = 'erro|Você precisa de ' . $preco . ' moedas para essa skin. Jogue mais!';
            } else {
                db()->beginTransaction();
                db()->prepare('UPDATE usuarios SET moedas = moedas - ? WHERE id = ?')->execute([$preco, $u['id']]);
                db()->prepare('INSERT IGNORE INTO skins_usuario (usuario_id, skin) VALUES (?,?)')->execute([$u['id'], $skin]);
                db()->prepare('UPDATE usuarios SET skin = ? WHERE id = ?')->execute([$skin, $u['id']]);
                db()->commit();
                $aviso = 'ok|Skin "' . $skins[$skin]['nome'] . '" desbloqueada!';
                $tem = true;
            }
        } else {
            db()->prepare('UPDATE usuarios SET skin = ? WHERE id = ?')->execute([$skin, $u['id']]);
        }
        if ($tem && !$aviso) $aviso = 'ok|Skin equipada.';
    }
    $acess = (string)($_POST['acessorio'] ?? '__nao__');
    $cat   = acessorios();
    if (isset($cat[$acess])) {
        $st = db()->prepare('SELECT 1 FROM acessorios_usuario WHERE usuario_id = ? AND acessorio = ?');
        $st->execute([$u['id'], $acess]);
        $tem = (bool)$st->fetch();

        if (!$tem) {
            $preco = (int)$cat[$acess]['preco'];
            if ((int)$u['moedas'] < $preco) {
                $aviso = 'erro|Você precisa de ' . $preco . ' moedas para esse acessório. Jogue mais!';
            } else {
                db()->beginTransaction();
                db()->prepare('UPDATE usuarios SET moedas = moedas - ? WHERE id = ?')->execute([$preco, $u['id']]);
                db()->prepare('INSERT IGNORE INTO acessorios_usuario (usuario_id, acessorio) VALUES (?,?)')->execute([$u['id'], $acess]);
                db()->prepare('UPDATE usuarios SET acessorio = ? WHERE id = ?')->execute([$acess, $u['id']]);
                db()->commit();
                $aviso = 'ok|Acessório "' . $cat[$acess]['nome'] . '" desbloqueado!';
                $tem = true;
            }
        } else {
            db()->prepare('UPDATE usuarios SET acessorio = ? WHERE id = ?')->execute([$acess, $u['id']]);
        }
        if ($tem && !$aviso) $aviso = 'ok|Acessório equipado.';
    }

    // recarrega dados atualizados
    $st = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
    $st->execute([$u['id']]);
    $u = $st->fetch();
}

$st = db()->prepare('SELECT skin FROM skins_usuario WHERE usuario_id = ?');
$st->execute([$u['id']]);
$minhas = array_column($st->fetchAll(), 'skin');

$st = db()->prepare('SELECT acessorio FROM acessorios_usuario WHERE usuario_id = ?');
$st->execute([$u['id']]);
$meusAcess = array_column($st->fetchAll(), 'acessorio');

$st = db()->prepare('SELECT modo, pontos FROM recordes WHERE usuario_id = ?');
$st->execute([$u['id']]);
$recordes = array_column($st->fetchAll(), 'pontos', 'modo');

$st = db()->prepare('SELECT COUNT(*) t, COALESCE(SUM(pontos),0) p FROM partidas WHERE usuario_id = ?');
$st->execute([$u['id']]);
$tot = $st->fetch();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0b1220">
<title>Cobrinha — menu</title>
<link rel="stylesheet" href="estilo.css?v=<?= APP_VERSAO ?>">
</head>
<body>
<div class="container">

  <div class="topo" style="margin-bottom:14px">
    <div>
      <div style="font-size:20px;font-weight:700">Olá, <?= e($u['usuario']) ?> 👋</div>
      <div class="mudo">@<?= e($u['usuario']) ?></div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <span class="chip">🪙 <b><?= (int)$u['moedas'] ?></b></span>
      <?php if ((int)($u['admin'] ?? 0) === 1): ?><a class="chip" href="admin.php">⚙️</a><?php endif; ?>
      <a class="chip" href="sair.php">Sair</a>
    </div>
  </div>

  <?php if ($aviso):
      [$tipo, $msg] = explode('|', $aviso, 2); ?>
      <div class="<?= $tipo === 'ok' ? 'ok' : 'erro' ?>"><?= e($msg) ?></div>
  <?php endif; ?>

  <div class="card">
    <h2>Escolha o modo</h2>
    <div class="grade">
      <?php foreach (modos() as $id => $m): ?>
        <a class="item" href="<?= e($m['arquivo']) ?>?modo=<?= e($id) ?>" style="display:block;color:inherit">
          <div class="nome"><?= e($m['nome']) ?></div>
          <div class="info"><?= e($m['desc']) ?></div>
          <div class="info" style="margin-top:6px;color:var(--verde)">
            Recorde: <b><?= (int)($recordes[$id] ?? 0) ?></b>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h2>Skins <span class="mudo">— desbloqueie com moedas que você ganha jogando</span></h2>
    <form method="post" id="formSkin">
      <input type="hidden" name="csrf" value="<?= e(token()) ?>">
      <input type="hidden" name="skin" id="skinEscolhida">
      <div class="grade">
        <?php foreach (skins() as $id => $s):
            $tem   = in_array($id, $minhas, true);
            $ativa = $u['skin'] === $id; ?>
          <?php
            $c0 = e($s['cores'][0]); $c1 = e($s['cores'][1]);
            $fundo = match ($s['padrao'] ?? 'solido') {
                'listras'  => "repeating-linear-gradient(45deg,$c0 0 7px,$c1 7px 14px)",
                'anelado'  => "repeating-linear-gradient(90deg,$c0 0 12px,$c1 12px 16px)",
                'zebra'    => "repeating-linear-gradient(70deg,$c0 0 9px,$c1 9px 13px)",
                'bolinhas' => "radial-gradient($c1 26%,transparent 27%) 0 0/13px 13px, $c0",
                'escamas'  => "radial-gradient(circle at 50% 0,$c1 40%,transparent 41%) 0 0/11px 11px, $c0",
                'neon'     => "radial-gradient(circle,$c1 30%,$c0 70%)",
                default    => "linear-gradient(135deg,$c0,$c1)",
            };
          ?>
          <div class="item <?= $ativa ? 'on' : '' ?> <?= $tem ? '' : 'travado' ?>" onclick="escolher('<?= e($id) ?>')">
            <div class="bola" style="background:<?= $fundo ?>"></div>
            <div class="nome"><?= e($s['nome']) ?></div>
            <div class="info">
              <?= $ativa ? 'Equipada' : ($tem ? 'Equipar' : '🪙 ' . (int)$s['preco']) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Acessórios <span class="mudo">— aparecem na cabeça da sua cobrinha</span></h2>
    <form method="post" id="formAcess">
      <input type="hidden" name="csrf" value="<?= e(token()) ?>">
      <input type="hidden" name="acessorio" id="acessEscolhido">
      <div class="grade">
        <?php foreach (acessorios() as $id => $a):
            $tem   = in_array($id, $meusAcess, true);
            $ativo = (string)($u['acessorio'] ?? '') === $id;
            $emoji = $a["emoji"] ?? "✨"; ?>
          <div class="item <?= $ativo ? 'on' : '' ?> <?= $tem ? '' : 'travado' ?>" onclick="escolherAcess('<?= e($id) ?>')">
            <div style="font-size:30px;line-height:42px"><?= $emoji ?></div>
            <div class="nome"><?= e($a['nome']) ?></div>
            <div class="info"><?= $ativo ? 'Equipado' : ($tem ? 'Equipar' : '🪙 ' . (int)$a['preco']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Suas estatísticas</h2>
    <div class="grade">
      <div class="item"><div class="nome"><?= (int)$tot['t'] ?></div><div class="info">partidas</div></div>
      <div class="item"><div class="nome"><?= (int)$tot['p'] ?></div><div class="info">pontos no total</div></div>
      <div class="item"><div class="nome"><?= (int)$u['moedas'] ?></div><div class="info">moedas</div></div>
      <div class="item"><div class="nome"><?= count($minhas) ?>/<?= count(skins()) ?></div><div class="info">skins</div></div>
    </div>
    <a class="btn sec" href="ranking.php" style="text-align:center;margin-top:14px">🏆 Ver ranking</a>
  </div>

  <p class="rodape">Cobrinha v<?= APP_VERSAO ?></p>
</div>

<script>
function escolher(id){
  document.getElementById('skinEscolhida').value = id;
  document.getElementById('formSkin').submit();
}
function escolherAcess(id){
  document.getElementById('acessEscolhido').value = id;
  document.getElementById('formAcess').submit();
}
</script>
</body>
</html>
