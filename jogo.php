<?php
// Tela do jogo
require __DIR__ . '/config.php';
$u = exigirLogin();

$modos = modos();
$modo  = (string)($_GET['modo'] ?? 'classico');
if (!isset($modos[$modo])) $modo = 'classico';

$skins = skins();
$skin  = $skins[$u['skin']] ?? $skins['verde'];

$st = db()->prepare('SELECT pontos FROM recordes WHERE usuario_id = ? AND modo = ?');
$st->execute([$u['id'], $modo]);
$recorde = (int)($st->fetchColumn() ?: 0);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,user-scalable=no">
<meta name="theme-color" content="#0b1220">
<title>Cobrinha — <?= e($modos[$modo]['nome']) ?></title>
<link rel="stylesheet" href="estilo.css?v=<?= APP_VERSAO ?>">
</head>
<body>
<div class="container arena">

  <div class="topo" style="width:100%;max-width:520px">
    <a class="chip" href="menu.php">← Menu</a>
    <span class="chip"><?= e($modos[$modo]['nome']) ?></span>
  </div>

  <div class="placar">
    <div class="box"><small>Pontos</small><b id="pts">0</b></div>
    <div class="box"><small>Recorde</small><b id="rec"><?= $recorde ?></b></div>
    <div class="box"><small>Tamanho</small><b id="tam">3</b></div>
  </div>

  <canvas id="tela" width="600" height="600"></canvas>

  <div class="dpad">
    <div class="vazio"></div>
    <button data-dir="cima">▲</button>
    <div class="vazio"></div>
    <button data-dir="esq">◀</button>
    <button data-dir="baixo">▼</button>
    <button data-dir="dir">▶</button>
  </div>
  <p class="mudo">Setas/WASD no teclado, arraste na tela ou use os botões. Espaço pausa.</p>
</div>

<!-- Início -->
<div class="overlay" id="telaInicio">
  <div class="card">
    <h2><?= e($modos[$modo]['nome']) ?></h2>
    <p class="mudo" style="margin:8px 0 4px"><?= e($modos[$modo]['desc']) ?></p>
    <p class="mudo">Multiplicador de pontos: <?= e((string)$modos[$modo]['mult']) ?>x</p>
    <button class="btn" onclick="Jogo.iniciar()">Jogar</button>
    <a class="btn sec" href="menu.php" style="text-align:center">Voltar</a>
  </div>
</div>

<!-- Fim -->
<div class="overlay esconde" id="telaFim">
  <div class="card">
    <h2 id="fimTitulo">Fim de jogo</h2>
    <div class="pts" id="fimPontos">0</div>
    <p class="mudo" id="fimInfo">—</p>
    <button class="btn" onclick="location.reload()">Jogar de novo</button>
    <a class="btn sec" href="menu.php" style="text-align:center">Menu</a>
    <a class="btn sec" href="ranking.php" style="text-align:center">🏆 Ranking</a>
  </div>
</div>

<script>
const CONF = {
  modo:     <?= json_encode($modo) ?>,
  mult:     <?= json_encode((float)$modos[$modo]['mult']) ?>,
  csrf:     <?= json_encode(token()) ?>,
  cores:    <?= json_encode($skin['cores']) ?>,
  arcoiris: <?= json_encode(!empty($skin['arcoiris'])) ?>,
  recorde:  <?= $recorde ?>
};

const Jogo = (function(){
  const CEL = 24;                      // grade 24x24
  const tela = document.getElementById('tela');
  const ctx  = tela.getContext('2d');
  const px   = tela.width / CEL;

  let cobra, dir, proxDir, comida, obstaculos, vivo, pausado, iniciada;
  let ritmo, ultimo, acumulado, inicioEm, enviando;

  const ritmoBase = CONF.modo === 'turbo' ? 70 : 130;

  function iguais(a, b){ return a.x === b.x && a.y === b.y; }
  function ocupado(p){
    return cobra.some(function(c){ return iguais(c, p); }) ||
           obstaculos.some(function(o){ return iguais(o, p); });
  }

  function novaComida(){
    let p, n = 0;
    do {
      p = { x: Math.floor(Math.random()*CEL), y: Math.floor(Math.random()*CEL) };
      n++;
    } while (ocupado(p) && n < 500);
    comida = p;
  }

  function montarObstaculos(){
    obstaculos = [];
    if (CONF.modo !== 'obstaculo') return;
    const blocos = [[6,6],[6,7],[7,6],[17,17],[17,16],[16,17],[6,17],[7,17],[6,16],[17,6],[16,6],[17,7]];
    for (let i = 9; i <= 14; i++) { obstaculos.push({x:i, y:11}); obstaculos.push({x:11, y:i}); }
    blocos.forEach(function(b){ obstaculos.push({x:b[0], y:b[1]}); });
  }

  function reiniciar(){
    cobra   = [{x:12,y:12},{x:11,y:12},{x:10,y:12}];
    dir     = {x:1,y:0};
    proxDir = {x:1,y:0};
    vivo = true; pausado = false; enviando = false;
    ritmo = ritmoBase; acumulado = 0; ultimo = 0;
    inicioEm = Date.now();
    montarObstaculos();
    novaComida();
    atualizaPlacar();
  }

  function atualizaPlacar(){
    const comidas = cobra.length - 3;
    document.getElementById('pts').textContent = Math.round(comidas * 10 * CONF.mult);
    document.getElementById('tam').textContent = cobra.length;
  }

  function virar(nx, ny){
    if (!vivo || !iniciada) return;
    if (dir.x === -nx && dir.y === -ny) return;   // não deixa voltar em si mesma
    proxDir = {x:nx, y:ny};
  }

  function passo(){
    dir = proxDir;
    const cab = { x: cobra[0].x + dir.x, y: cobra[0].y + dir.y };

    if (CONF.modo === 'infinito') {
      cab.x = (cab.x + CEL) % CEL;
      cab.y = (cab.y + CEL) % CEL;
    } else if (cab.x < 0 || cab.y < 0 || cab.x >= CEL || cab.y >= CEL) {
      return morrer();
    }

    if (cobra.some(function(c){ return iguais(c, cab); })) return morrer();
    if (obstaculos.some(function(o){ return iguais(o, cab); })) return morrer();

    cobra.unshift(cab);

    if (iguais(cab, comida)) {
      novaComida();
      // acelera de leve conforme cresce, com um piso
      ritmo = Math.max(ritmoBase * 0.45, ritmo - 1.5);
      atualizaPlacar();
    } else {
      cobra.pop();
    }
  }

  function morrer(){
    vivo = false;
    enviarResultado();
  }

  function corDoElo(i){
    if (CONF.arcoiris) return 'hsl(' + ((i * 12 + Date.now()/12) % 360) + ',85%,60%)';
    const t = cobra.length > 1 ? i / (cobra.length - 1) : 0;
    return t < 0.5 ? CONF.cores[0] : CONF.cores[1];
  }

  function desenhar(){
    ctx.fillStyle = '#0a1226';
    ctx.fillRect(0, 0, tela.width, tela.height);

    // grade
    ctx.strokeStyle = 'rgba(255,255,255,.035)';
    ctx.lineWidth = 1;
    for (let i = 1; i < CEL; i++){
      ctx.beginPath(); ctx.moveTo(i*px, 0); ctx.lineTo(i*px, tela.height); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(0, i*px); ctx.lineTo(tela.width, i*px); ctx.stroke();
    }

    // obstáculos
    ctx.fillStyle = '#334160';
    obstaculos.forEach(function(o){
      ctx.fillRect(o.x*px+1, o.y*px+1, px-2, px-2);
    });

    // comida
    const pulso = 1 + Math.sin(Date.now()/180) * 0.08;
    ctx.fillStyle = '#ef4444';
    ctx.beginPath();
    ctx.arc(comida.x*px + px/2, comida.y*px + px/2, (px/2 - 2) * pulso, 0, Math.PI*2);
    ctx.fill();

    // cobra
    for (let i = cobra.length - 1; i >= 0; i--){
      const c = cobra[i];
      ctx.fillStyle = corDoElo(i);
      const r = i === 0 ? 6 : 4;
      redondo(c.x*px+1, c.y*px+1, px-2, px-2, r);
      ctx.fill();
    }

    // olhos
    const cab = cobra[0];
    ctx.fillStyle = '#08111f';
    const cx = cab.x*px + px/2, cy = cab.y*px + px/2, d = px/5;
    const ox = dir.y !== 0 ? d : d*0.6, oy = dir.x !== 0 ? d : d*0.6;
    ctx.beginPath(); ctx.arc(cx - ox*0.9 + dir.x*d*0.5, cy - oy*0.9 + dir.y*d*0.5, px/9, 0, Math.PI*2); ctx.fill();
    ctx.beginPath(); ctx.arc(cx + ox*0.9 + dir.x*d*0.5, cy + oy*0.9 + dir.y*d*0.5, px/9, 0, Math.PI*2); ctx.fill();

    if (pausado){
      ctx.fillStyle = 'rgba(6,11,22,.7)';
      ctx.fillRect(0,0,tela.width,tela.height);
      ctx.fillStyle = '#e7eefc';
      ctx.font = 'bold 34px system-ui, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('PAUSADO', tela.width/2, tela.height/2);
    }
  }

  function redondo(x, y, w, h, r){
    ctx.beginPath();
    ctx.moveTo(x+r, y);
    ctx.arcTo(x+w, y,   x+w, y+h, r);
    ctx.arcTo(x+w, y+h, x,   y+h, r);
    ctx.arcTo(x,   y+h, x,   y,   r);
    ctx.arcTo(x,   y,   x+w, y,   r);
    ctx.closePath();
  }

  function laco(agora){
    if (!vivo) { desenhar(); return; }
    if (!ultimo) ultimo = agora;
    const dt = agora - ultimo;
    ultimo = agora;
    if (!pausado){
      acumulado += dt;
      while (acumulado >= ritmo && vivo){ acumulado -= ritmo; passo(); }
    }
    desenhar();
    requestAnimationFrame(laco);
  }

  function enviarResultado(){
    if (enviando) return;
    enviando = true;
    fetch('api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        acao: 'fim', csrf: CONF.csrf, modo: CONF.modo,
        tamanho: cobra.length,
        duracao: Math.round((Date.now() - inicioEm) / 1000)
      })
    })
    .then(function(r){ return r.json(); })
    .then(function(d){ mostrarFim(d); })
    .catch(function(){ mostrarFim(null); });
  }

  function mostrarFim(d){
    document.getElementById('telaFim').classList.remove('esconde');
    if (!d || d.erro){
      document.getElementById('fimPontos').textContent = Math.round((cobra.length-3)*10*CONF.mult);
      document.getElementById('fimInfo').textContent = d && d.erro ? d.erro : 'Não deu para salvar a partida.';
      return;
    }
    document.getElementById('fimTitulo').textContent = d.novo ? '🎉 Novo recorde!' : 'Fim de jogo';
    document.getElementById('fimPontos').textContent = d.pontos;
    document.getElementById('fimInfo').innerHTML =
      '🪙 +' + d.moedas + ' moedas &nbsp;·&nbsp; recorde ' + d.recorde + ' &nbsp;·&nbsp; #' + d.posicao + ' no ranking';
    document.getElementById('rec').textContent = d.recorde;
  }

  // ---- controles ----
  document.addEventListener('keydown', function(ev){
    const k = ev.key.toLowerCase();
    if (['arrowup','arrowdown','arrowleft','arrowright',' '].indexOf(ev.key.toLowerCase()) >= 0) ev.preventDefault();
    if (k === 'arrowup'    || k === 'w') virar(0,-1);
    if (k === 'arrowdown'  || k === 's') virar(0, 1);
    if (k === 'arrowleft'  || k === 'a') virar(-1,0);
    if (k === 'arrowright' || k === 'd') virar(1, 0);
    if (k === ' ' && iniciada && vivo) pausado = !pausado;
  });

  document.querySelectorAll('.dpad button').forEach(function(b){
    b.addEventListener('click', function(){
      const m = {cima:[0,-1], baixo:[0,1], esq:[-1,0], dir:[1,0]}[b.dataset.dir];
      virar(m[0], m[1]);
    });
  });

  let tx = 0, ty = 0;
  tela.addEventListener('touchstart', function(ev){
    tx = ev.touches[0].clientX; ty = ev.touches[0].clientY;
  }, {passive:true});
  tela.addEventListener('touchend', function(ev){
    const dx = ev.changedTouches[0].clientX - tx;
    const dy = ev.changedTouches[0].clientY - ty;
    if (Math.abs(dx) < 18 && Math.abs(dy) < 18) return;
    if (Math.abs(dx) > Math.abs(dy)) virar(dx > 0 ? 1 : -1, 0);
    else virar(0, dy > 0 ? 1 : -1);
  }, {passive:true});

  return {
    iniciar: function(){
      document.getElementById('telaInicio').classList.add('esconde');
      iniciada = true;
      reiniciar();
      requestAnimationFrame(laco);
    }
  };
})();
</script>
</body>
</html>
