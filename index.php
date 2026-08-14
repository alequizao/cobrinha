<?php
// Login e cadastro
require __DIR__ . '/config.php';
sessao();

if (usuario()) { header('Location: menu.php'); exit; }

$erro = '';
$aba  = ($_POST['acao'] ?? $_GET['aba'] ?? 'entrar') === 'cadastrar' ? 'cadastrar' : 'entrar';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioNome = trim((string)($_POST['usuario'] ?? ''));
    $senha       = (string)($_POST['senha'] ?? '');

    if (!checaToken($_POST['csrf'] ?? null)) {
        $erro = 'Sessão expirada, tente de novo.';
    } elseif ($aba === 'cadastrar') {
        // O usuário é o próprio nome da cobrinha — por isso é único.
        $nome = $usuarioNome;
        if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $usuarioNome)) {
            $erro = 'Nome da cobrinha: 3 a 16 caracteres, apenas letras, números e _';
        } elseif (strlen($senha) < 4) {
            $erro = 'A senha precisa de pelo menos 4 caracteres.';
        } else {
            $st = db()->prepare('SELECT id FROM usuarios WHERE usuario = ? OR nome = ?');
            $st->execute([$usuarioNome, $nome]);
            if ($st->fetch()) {
                $erro = 'Já existe uma cobrinha com esse nome. Escolha outro.';
            } else {
                $ins = db()->prepare('INSERT INTO usuarios (usuario, nome, senha, moedas) VALUES (?,?,?,0)');
                $ins->execute([$usuarioNome, $nome, password_hash($senha, PASSWORD_DEFAULT)]);
                $id = (int)db()->lastInsertId();
                db()->prepare('INSERT INTO skins_usuario (usuario_id, skin) VALUES (?, ?)')->execute([$id, 'verde']);
                $_SESSION['uid'] = $id;
                header('Location: menu.php'); exit;
            }
        }
    } else {
        $st = db()->prepare('SELECT * FROM usuarios WHERE usuario = ?');
        $st->execute([$usuarioNome]);
        $u = $st->fetch();
        if ($u && password_verify($senha, $u['senha'])) {
            $_SESSION['uid'] = (int)$u['id'];
            db()->prepare('UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?')->execute([$u['id']]);
            header('Location: menu.php'); exit;
        }
        $erro = 'Usuário ou senha incorretos.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0b1220">
<title>Cobrinha — entrar</title>
<link rel="stylesheet" href="estilo.css?v=<?= APP_VERSAO ?>">
</head>
<body>
<div class="centro">
  <div style="width:100%;max-width:400px">
    <div class="logo">
      <h1>🐍 Cobri<span>nha</span></h1>
      <p>Tudo liberado. Sem anúncio, sem pagar nada.</p>
    </div>

    <div class="card">
      <div class="abas">
        <button type="button" class="<?= $aba==='entrar'?'on':'' ?>" onclick="troca('entrar')">Entrar</button>
        <button type="button" class="<?= $aba==='cadastrar'?'on':'' ?>" onclick="troca('cadastrar')">Criar conta</button>
      </div>

      <?php if ($erro): ?><div class="erro" style="margin-top:12px"><?= e($erro) ?></div><?php endif; ?>

      <form method="post" id="form">
        <input type="hidden" name="csrf" value="<?= e(token()) ?>">
        <input type="hidden" name="acao" id="acao" value="<?= e($aba) ?>">

        <label for="usuario">Nome da cobrinha <span id="dicaNome" class="<?= $aba==='cadastrar'?'':'esconde' ?>">— é ele que aparece no jogo</span></label>
        <input id="usuario" name="usuario" maxlength="16" required value="<?= e($_POST['usuario'] ?? '') ?>" autocomplete="username" placeholder="ex: Victor">

        <label for="senha">Senha</label>
        <input id="senha" name="senha" type="password" required autocomplete="current-password">

        <button class="btn" id="enviar"><?= $aba==='cadastrar' ? 'Criar conta e jogar' : 'Entrar' ?></button>
      </form>
    </div>
    <p class="rodape">v<?= APP_VERSAO ?></p>
  </div>
</div>

<script>
function troca(qual){
  document.getElementById('acao').value = qual;
  document.getElementById('dicaNome').classList.toggle('esconde', qual !== 'cadastrar');
  document.getElementById('enviar').textContent = qual === 'cadastrar' ? 'Criar conta e jogar' : 'Entrar';
  document.querySelectorAll('.abas button').forEach(function(b,i){
    b.classList.toggle('on', (i === 0) === (qual === 'entrar'));
  });
}
</script>
</body>
</html>
