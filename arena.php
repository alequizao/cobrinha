<?php
// Modo Arena — cobra contra cobras rivais em mundo aberto (estilo .io)
require __DIR__ . '/config.php';
$u = exigirLogin();

$skins = skins();
$skin  = $skins[$u['skin']] ?? $skins['verde'];

$st = db()->prepare('SELECT pontos FROM recordes WHERE usuario_id = ? AND modo = ?');
$st->execute([$u['id'], 'arena']);
$recorde = (int)($st->fetchColumn() ?: 0);

// Nomes dos rivais controlados pelo computador
$rivais = ['Kaio','Duda','Ravi','Nina','Téo','Bia','Zeca','Lulu','Vini','Malu','Caco','Pipa','Juju','Tato','Nick'];
shuffle($rivais);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,user-scalable=no">
<meta name="theme-color" content="#070d1a">
<title>Cobrinha — Arena</title>
<link rel="stylesheet" href="estilo.css?v=<?= APP_VERSAO ?>">
<style>
  body{overflow:hidden}
  #arena{position:fixed;inset:0;display:block;background:#070d1a;touch-action:none}

  .hud{position:fixed;pointer-events:none;z-index:5;color:#e7eefc;text-shadow:0 2px 6px rgba(0,0,0,.7)}
  .hud-topo{top:calc(env(safe-area-inset-top) + 10px);left:12px;display:flex;gap:8px;align-items:center}
  .hud-topo .chip{pointer-events:auto;background:rgba(12,20,38,.75);backdrop-filter:blur(6px)}

  .placarLive{top:calc(env(safe-area-inset-top) + 10px);right:12px;width:172px;
    background:rgba(12,20,38,.75);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.09);
    border-radius:14px;padding:9px 11px;font-size:12.5px}
  .placarLive h4{font-size:10.5px;letter-spacing:.10em;text-transform:uppercase;color:#93a4c6;margin-bottom:6px}
  .placarLive .l{display:flex;justify-content:space-between;gap:8px;padding:2.5px 0;white-space:nowrap}
  .placarLive .l span:first-child{overflow:hidden;text-overflow:ellipsis}
  .placarLive .l.eu{color:#4ade80;font-weight:700}
  .placarLive .l b{color:#fbbf24;font-weight:600}

  .barra{bottom:calc(env(safe-area-inset-bottom) + 12px);left:12px;display:flex;flex-direction:column;gap:6px}
  .barra .chip{background:rgba(12,20,38,.75);backdrop-filter:blur(6px)}

  #mini{position:fixed;right:12px;bottom:calc(env(safe-area-inset-bottom) + 12px);
    width:112px;height:112px;border-radius:14px;border:1px solid rgba(255,255,255,.09);
    background:rgba(12,20,38,.7);z-index:5;pointer-events:none}

  /* joystick e boost (toque) */
  #toque{position:fixed;inset:0;z-index:4;display:none}
  .toques #toque{display:block}
  #base{position:absolute;width:118px;height:118px;border-radius:50%;
    border:2px solid rgba(255,255,255,.16);background:rgba(255,255,255,.05);display:none}
  #pino{position:absolute;width:52px;height:52px;border-radius:50%;
    background:rgba(74,222,128,.75);box-shadow:0 0 18px rgba(74,222,128,.5);display:none}
  #turbo{position:fixed;right:18px;bottom:calc(env(safe-area-inset-bottom) + 140px);
    width:82px;height:82px;border-radius:50%;z-index:6;display:none;
    border:2px solid rgba(251,191,36,.5);background:rgba(251,191,36,.18);color:#fde68a;
    font-size:12px;font-weight:800;letter-spacing:.06em;backdrop-filter:blur(4px)}
  .toques #turbo{display:block}
  #turbo:active,#turbo.on{background:rgba(251,191,36,.45)}

  .msg{position:fixed;left:50%;top:26%;transform:translateX(-50%);z-index:6;pointer-events:none;
    font-size:19px;font-weight:800;color:#fde68a;text-shadow:0 3px 10px rgba(0,0,0,.8);opacity:0;transition:opacity .25s}
  .msg.ver{opacity:1}
</style>
</head>
<body>
<canvas id="arena"></canvas>
<canvas id="mini" width="224" height="224"></canvas>

<div class="hud hud-topo">
  <a class="chip" href="menu.php">←</a>
  <span class="chip">📏 <b id="hudTam">10</b></span>
  <span class="chip">💀 <b id="hudKills">0</b></span>
</div>

<div class="hud placarLive">
  <h4>Placar ao vivo</h4>
  <div id="listaPlacar"></div>
</div>

<div class="hud barra">
  <span class="chip">🏅 Recorde <b><?= $recorde ?></b></span>
</div>

<div id="toque"><div id="base"></div><div id="pino"></div></div>
<button id="turbo">TURBO</button>
<div class="msg" id="msg"></div>

<!-- Início -->
<div class="overlay" id="telaInicio">
  <div class="card">
    <h2>🐍 Arena</h2>
    <p class="mudo" style="margin:10px 0">
      Coma os orbes pra crescer. Faça as rivais baterem em você — quando morrem, viram comida.
      Encostar a <b>sua cabeça</b> no corpo delas mata você.
    </p>
    <p class="mudo" style="font-size:12px">
      Computador: mouse guia · segure o clique ou Espaço pra turbo<br>
      Celular: arraste pra guiar · botão TURBO
    </p>
    <button class="btn" onclick="Arena.iniciar()">Entrar na arena</button>
    <a class="btn sec" href="menu.php" style="text-align:center">Voltar</a>
  </div>
</div>

<!-- Fim -->
<div class="overlay esconde" id="telaFim">
  <div class="card">
    <h2 id="fimTitulo">Você foi eliminado</h2>
    <div class="pts" id="fimPontos">0</div>
    <p class="mudo" id="fimInfo">—</p>
    <button class="btn" onclick="location.reload()">Jogar de novo</button>
    <a class="btn sec" href="menu.php" style="text-align:center">Menu</a>
    <a class="btn sec" href="ranking.php?modo=arena" style="text-align:center">🏆 Ranking</a>
  </div>
</div>

<script>
const CONF = {
  csrf:     <?= json_encode(token()) ?>,
  nome:     <?= json_encode($u['nome']) ?>,
  cores:    <?= json_encode($skin['cores']) ?>,
  arcoiris: <?= json_encode(!empty($skin['arcoiris'])) ?>,
  rivais:   <?= json_encode(array_slice($rivais, 0, 12)) ?>
};

const Arena = (function(){
  'use strict';

  // ---------- mundo ----------
  const MUNDO   = 3200;          // raio da arena circular
  const N_BOTS  = 11;
  const N_ORBES = 850;
  const MASSA0  = 10;
  const VEL     = 2.45;
  const VEL_T   = 4.35;          // turbo
  const GIRO    = 0.085;         // rad por quadro
  const CUSTO_T = 0.09;          // massa por quadro no turbo

  const PALETA = [
    ['#f87171','#b91c1c'], ['#60a5fa','#1d4ed8'], ['#fbbf24','#b45309'],
    ['#c084fc','#6d28d9'], ['#34d399','#047857'], ['#f472b6','#be185d'],
    ['#22d3ee','#0e7490'], ['#a3e635','#4d7c0f'], ['#fb923c','#c2410c'],
    ['#e879f9','#a21caf'], ['#93c5fd','#2563eb'], ['#fca5a5','#dc2626']
  ];

  const tela = document.getElementById('arena');
  const ctx  = tela.getContext('2d');
  const mini = document.getElementById('mini');
  const mtx  = mini.getContext('2d');

  let L = 0, A = 0, dpr = 1;
  let cobras, orbes, jogador, rodando, inicioEm, kills, enviado;
  let camX = 0, camY = 0, zoom = 1;
  let mira = 0, turbo = false;

  function ajustar(){
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    L = window.innerWidth; A = window.innerHeight;
    tela.width = Math.round(L * dpr); tela.height = Math.round(A * dpr);
    tela.style.width = L + 'px'; tela.style.height = A + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }
  window.addEventListener('resize', ajustar);

  const rnd  = function(a, b){ return a + Math.random() * (b - a); };
  const dist2 = function(ax, ay, bx, by){ const dx = ax-bx, dy = ay-by; return dx*dx + dy*dy; };

  function pontoNoMundo(){
    const ang = rnd(0, Math.PI*2), r = Math.sqrt(Math.random()) * (MUNDO - 90);
    return { x: Math.cos(ang)*r, y: Math.sin(ang)*r };
  }

  // ---------- entidades ----------
  function novoOrbe(x, y, valor){
    const p = (x === undefined) ? pontoNoMundo() : {x:x, y:y};
    const c = PALETA[Math.floor(Math.random()*PALETA.length)][0];
    return { x:p.x, y:p.y, v: valor || 1, cor: c, fase: rnd(0, 6.28) };
  }

  function novaCobra(nome, cores, ehJogador, arcoiris){
    const p = pontoNoMundo();
    const ang = rnd(0, Math.PI*2);
    const c = {
      nome: nome, cores: cores, arcoiris: !!arcoiris, jogador: !!ehJogador,
      x: p.x, y: p.y, ang: ang, massa: MASSA0, viva: true,
      corpo: [], turbo: false, alvo: null, humor: 0, kills: 0
    };
    for (let i = 0; i < 60; i++) c.corpo.push({x: c.x - Math.cos(ang)*i*2, y: c.y - Math.sin(ang)*i*2});
    return c;
  }

  const raio     = function(c){ return 6 + Math.min(20, Math.sqrt(c.massa) * 0.75); };
  const tamanho  = function(c){ return Math.floor(c.massa); };
  const elos     = function(c){ return Math.floor(14 + c.massa * 1.05); };

  function reiniciar(){
    orbes = [];
    for (let i = 0; i < N_ORBES; i++) orbes.push(novoOrbe());

    cobras = [];
    jogador = novaCobra(CONF.nome, CONF.cores, true, CONF.arcoiris);
    cobras.push(jogador);
    for (let i = 0; i < N_BOTS; i++){
      const b = novaCobra(CONF.rivais[i % CONF.rivais.length], PALETA[i % PALETA.length], false);
      b.massa = rnd(12, 45);
      cobras.push(b);
    }
    kills = 0; enviado = false; rodando = true;
    inicioEm = Date.now();
    camX = jogador.x; camY = jogador.y;
    mira = jogador.ang;
  }

  // ---------- IA dos rivais ----------
  function pensarBot(b){
    const r = raio(b);

    // 1) fugir da borda
    const d = Math.hypot(b.x, b.y);
    if (d > MUNDO - 220){
      b.alvoAng = Math.atan2(-b.y, -b.x);
      b.turbo = false;
      return;
    }

    // 2) desviar de cabeça/corpo alheio bem perto
    let perigo = null, dPerigo = 1e9;
    for (let i = 0; i < cobras.length; i++){
      const o = cobras[i];
      if (o === b || !o.viva) continue;
      const passo = 4;
      for (let j = 0; j < o.corpo.length; j += passo){
        const s = o.corpo[j];
        const dd = dist2(b.x, b.y, s.x, s.y);
        if (dd < (r + raio(o) + 62) * (r + raio(o) + 62) && dd < dPerigo){
          dPerigo = dd; perigo = s;
        }
      }
    }
    if (perigo){
      b.alvoAng = Math.atan2(b.y - perigo.y, b.x - perigo.x);
      b.turbo = b.massa > 25;
      return;
    }

    // 3) caçar orbe mais próximo (com um pouco de teimosia)
    if (!b.alvo || b.alvo.morto || Math.random() < 0.02){
      let melhor = null, dm = 1e9;
      for (let i = 0; i < orbes.length; i += 3){
        const o = orbes[i];
        const dd = dist2(b.x, b.y, o.x, o.y);
        if (dd < dm){ dm = dd; melhor = o; }
      }
      b.alvo = melhor;
    }
    if (b.alvo){
      b.alvoAng = Math.atan2(b.alvo.y - b.y, b.alvo.x - b.x);
      b.turbo = b.massa > 40 && Math.random() < 0.02;
    }
  }

  function moverCobra(c){
    if (!c.viva) return;

    if (!c.jogador) pensarBot(c);
    const desejado = c.jogador ? mira : (c.alvoAng !== undefined ? c.alvoAng : c.ang);

    let dif = desejado - c.ang;
    while (dif >  Math.PI) dif -= Math.PI*2;
    while (dif < -Math.PI) dif += Math.PI*2;
    const limite = GIRO * (c.jogador ? 1.15 : 1);
    c.ang += Math.max(-limite, Math.min(limite, dif));

    const querTurbo = c.jogador ? turbo : c.turbo;
    const podeTurbo = querTurbo && c.massa > MASSA0 + 2;
    if (podeTurbo){
      c.massa -= CUSTO_T;
      // o turbo cospe massa pra trás
      if (Math.random() < 0.35){
        const t = c.corpo[c.corpo.length - 1];
        if (t) orbes.push(novoOrbe(t.x + rnd(-6,6), t.y + rnd(-6,6), 0.6));
      }
    }
    const v = podeTurbo ? VEL_T : VEL;
    c.x += Math.cos(c.ang) * v;
    c.y += Math.sin(c.ang) * v;
    c.turboAtivo = podeTurbo;

    // borda da arena mata
    if (Math.hypot(c.x, c.y) > MUNDO) return matar(c, null);

    c.corpo.unshift({x: c.x, y: c.y});
    const max = elos(c);
    while (c.corpo.length > max) c.corpo.pop();
  }

  function comer(c){
    const r = raio(c) + 12;
    for (let i = orbes.length - 1; i >= 0; i--){
      const o = orbes[i];
      if (dist2(c.x, c.y, o.x, o.y) < r*r){
        c.massa += o.v;
        o.morto = true;
        orbes.splice(i, 1);
        if (orbes.length < N_ORBES) orbes.push(novoOrbe());
      }
    }
  }

  function colidir(c){
    const r = raio(c);
    for (let i = 0; i < cobras.length; i++){
      const o = cobras[i];
      if (o === c || !o.viva) continue;
      const ro = raio(o);
      for (let j = 2; j < o.corpo.length; j += 2){
        const s = o.corpo[j];
        if (dist2(c.x, c.y, s.x, s.y) < (r + ro*0.85) * (r + ro*0.85)){
          return matar(c, o);
        }
      }
    }
  }

  function matar(c, porQuem){
    if (!c.viva) return;
    c.viva = false;

    // o corpo vira comida
    for (let i = 0; i < c.corpo.length; i += 2){
      const s = c.corpo[i];
      orbes.push(novoOrbe(s.x + rnd(-5,5), s.y + rnd(-5,5), Math.max(1, c.massa / c.corpo.length * 1.6)));
    }
    if (porQuem && porQuem.viva){
      porQuem.kills++;
      if (porQuem === jogador){
        kills++;
        document.getElementById('hudKills').textContent = kills;
        aviso('💀 Você eliminou ' + c.nome + '!');
      }
    }

    if (c === jogador){
      rodando = false;
      setTimeout(enviarResultado, 350);
    } else {
      // entra outra rival no lugar, mantendo a arena cheia
      setTimeout(function(){
        if (!rodando) return;
        const i = cobras.indexOf(c);
        const nova = novaCobra(
          CONF.rivais[Math.floor(Math.random()*CONF.rivais.length)],
          PALETA[Math.floor(Math.random()*PALETA.length)], false
        );
        nova.massa = rnd(12, 30);
        if (i >= 0) cobras[i] = nova;
      }, 2500);
    }
  }

  let avisoTimer = null;
  function aviso(txt){
    const el = document.getElementById('msg');
    el.textContent = txt;
    el.classList.add('ver');
    clearTimeout(avisoTimer);
    avisoTimer = setTimeout(function(){ el.classList.remove('ver'); }, 1600);
  }

  // ---------- desenho ----------
  function corDoElo(c, i, total){
    if (c.arcoiris) return 'hsl(' + ((i*7 + Date.now()/14) % 360) + ',85%,62%)';
    return (i % 12 < 6) ? c.cores[0] : c.cores[1];
  }

  function desenharCobra(c){
    if (!c.viva) return;
    const r = raio(c);

    if (c.turboAtivo){
      ctx.shadowBlur = 26; ctx.shadowColor = c.cores[0];
    }
    for (let i = c.corpo.length - 1; i >= 0; i--){
      const s = c.corpo[i];
      const sx = (s.x - camX) * zoom + L/2;
      const sy = (s.y - camY) * zoom + A/2;
      const rr = r * zoom;
      if (sx < -rr*2 || sy < -rr*2 || sx > L + rr*2 || sy > A + rr*2) continue;
      ctx.fillStyle = corDoElo(c, i, c.corpo.length);
      ctx.beginPath(); ctx.arc(sx, sy, rr, 0, 6.2832); ctx.fill();
    }
    ctx.shadowBlur = 0;

    // cabeça + olhos
    const hx = (c.x - camX) * zoom + L/2;
    const hy = (c.y - camY) * zoom + A/2;
    const rr = r * zoom;
    ctx.fillStyle = c.cores[0];
    ctx.beginPath(); ctx.arc(hx, hy, rr*1.06, 0, 6.2832); ctx.fill();

    const px = Math.cos(c.ang), py = Math.sin(c.ang);
    const ox = -py * rr*0.5, oy = px * rr*0.5;
    for (const sinal of [1, -1]){
      const ex = hx + px*rr*0.42 + ox*sinal;
      const ey = hy + py*rr*0.42 + oy*sinal;
      ctx.fillStyle = '#fff';
      ctx.beginPath(); ctx.arc(ex, ey, rr*0.34, 0, 6.2832); ctx.fill();
      ctx.fillStyle = '#0b1220';
      ctx.beginPath(); ctx.arc(ex + px*rr*0.13, ey + py*rr*0.13, rr*0.17, 0, 6.2832); ctx.fill();
    }

    // nome
    ctx.font = '600 ' + Math.max(10, 13*zoom) + 'px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillStyle = c.jogador ? '#e7eefc' : 'rgba(231,238,252,.62)';
    ctx.fillText(c.nome, hx, hy - rr - 8);
  }

  function desenhar(){
    ctx.fillStyle = '#070d1a';
    ctx.fillRect(0, 0, L, A);

    // pontilhado do chão
    const passo = 60 * zoom;
    const ix = ((-camX * zoom + L/2) % passo + passo) % passo;
    const iy = ((-camY * zoom + A/2) % passo + passo) % passo;
    ctx.fillStyle = 'rgba(255,255,255,.045)';
    for (let x = ix; x < L; x += passo)
      for (let y = iy; y < A; y += passo){
        ctx.beginPath(); ctx.arc(x, y, 1.6, 0, 6.2832); ctx.fill();
      }

    // limite da arena
    ctx.strokeStyle = 'rgba(239,68,68,.5)';
    ctx.lineWidth = 6 * zoom;
    ctx.beginPath();
    ctx.arc((0 - camX)*zoom + L/2, (0 - camY)*zoom + A/2, MUNDO*zoom, 0, 6.2832);
    ctx.stroke();

    // orbes
    const t = Date.now()/400;
    for (let i = 0; i < orbes.length; i++){
      const o = orbes[i];
      const sx = (o.x - camX)*zoom + L/2;
      const sy = (o.y - camY)*zoom + A/2;
      if (sx < -20 || sy < -20 || sx > L+20 || sy > A+20) continue;
      const rr = (3.2 + Math.min(7, o.v*1.4)) * zoom * (1 + Math.sin(t + o.fase)*0.12);
      ctx.fillStyle = o.cor;
      ctx.shadowBlur = 12; ctx.shadowColor = o.cor;
      ctx.beginPath(); ctx.arc(sx, sy, rr, 0, 6.2832); ctx.fill();
    }
    ctx.shadowBlur = 0;

    // cobras: rivais primeiro, jogador por cima
    for (let i = 0; i < cobras.length; i++) if (cobras[i] !== jogador) desenharCobra(cobras[i]);
    desenharCobra(jogador);
  }

  function desenharMini(){
    const W = mini.width, esc = (W/2 - 6) / MUNDO;
    mtx.clearRect(0, 0, W, W);
    mtx.strokeStyle = 'rgba(239,68,68,.45)'; mtx.lineWidth = 2;
    mtx.beginPath(); mtx.arc(W/2, W/2, MUNDO*esc, 0, 6.2832); mtx.stroke();

    for (let i = 0; i < cobras.length; i++){
      const c = cobras[i];
      if (!c.viva) continue;
      mtx.fillStyle = c.jogador ? '#4ade80' : 'rgba(231,238,252,.45)';
      mtx.beginPath();
      mtx.arc(W/2 + c.x*esc, W/2 + c.y*esc, c.jogador ? 5 : 3, 0, 6.2832);
      mtx.fill();
    }
  }

  function atualizarPlacar(){
    const vivas = cobras.filter(function(c){ return c.viva; })
                        .sort(function(a,b){ return b.massa - a.massa; });
    let html = '';
    for (let i = 0; i < Math.min(6, vivas.length); i++){
      const c = vivas[i];
      html += '<div class="l ' + (c.jogador ? 'eu' : '') + '"><span>' + (i+1) + '. ' +
              c.nome.replace(/[<>&]/g, '') + '</span><b>' + tamanho(c) + '</b></div>';
    }
    const pos = vivas.indexOf(jogador);
    if (pos >= 6) html += '<div class="l eu"><span>' + (pos+1) + '. você</span><b>' + tamanho(jogador) + '</b></div>';
    document.getElementById('listaPlacar').innerHTML = html;
    document.getElementById('hudTam').textContent = tamanho(jogador);
  }

  // ---------- laço ----------
  let quadro = 0;
  function laco(){
    if (rodando){
      for (let i = 0; i < cobras.length; i++) moverCobra(cobras[i]);
      for (let i = 0; i < cobras.length; i++) if (cobras[i].viva){ comer(cobras[i]); colidir(cobras[i]); }

      // câmera suave e zoom conforme o tamanho
      camX += (jogador.x - camX) * 0.12;
      camY += (jogador.y - camY) * 0.12;
      const alvoZoom = Math.max(0.52, Math.min(1.05, 26 / raio(jogador)));
      zoom += (alvoZoom - zoom) * 0.04;

      if (++quadro % 12 === 0){ atualizarPlacar(); desenharMini(); }
    }
    desenhar();
    requestAnimationFrame(laco);
  }

  // ---------- fim ----------
  function enviarResultado(){
    if (enviado) return;
    enviado = true;
    fetch('api.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        acao: 'fim', csrf: CONF.csrf, modo: 'arena',
        tamanho: tamanho(jogador),
        kills: kills,
        duracao: Math.round((Date.now() - inicioEm)/1000)
      })
    })
    .then(function(r){ return r.json(); })
    .then(mostrarFim)
    .catch(function(){ mostrarFim(null); });
  }

  function mostrarFim(d){
    document.getElementById('telaFim').classList.remove('esconde');
    if (!d || d.erro){
      document.getElementById('fimPontos').textContent = tamanho(jogador);
      document.getElementById('fimInfo').textContent = (d && d.erro) ? d.erro : 'Não deu para salvar a partida.';
      return;
    }
    document.getElementById('fimTitulo').textContent = d.novo ? '🎉 Novo recorde!' : 'Você foi eliminado';
    document.getElementById('fimPontos').textContent = d.pontos;
    document.getElementById('fimInfo').innerHTML =
      '📏 ' + tamanho(jogador) + ' &nbsp;·&nbsp; 💀 ' + kills +
      ' &nbsp;·&nbsp; 🪙 +' + d.moedas + ' &nbsp;·&nbsp; #' + d.posicao + ' no ranking';
  }

  // ---------- controles ----------
  document.addEventListener('mousemove', function(ev){
    mira = Math.atan2(ev.clientY - A/2, ev.clientX - L/2);
  });
  document.addEventListener('mousedown', function(){ turbo = true; });
  document.addEventListener('mouseup',   function(){ turbo = false; });
  document.addEventListener('keydown', function(ev){
    if (ev.code === 'Space'){ ev.preventDefault(); turbo = true; }
  });
  document.addEventListener('keyup', function(ev){
    if (ev.code === 'Space') turbo = false;
  });

  const base = document.getElementById('base'), pino = document.getElementById('pino');
  let toqueId = null, bx = 0, by = 0;

  function posBase(x, y){
    bx = x; by = y;
    base.style.display = pino.style.display = 'block';
    base.style.left = (x - 59) + 'px'; base.style.top = (y - 59) + 'px';
    pino.style.left = (x - 26) + 'px'; pino.style.top = (y - 26) + 'px';
  }
  document.getElementById('toque').addEventListener('touchstart', function(ev){
    const t = ev.changedTouches[0];
    toqueId = t.identifier;
    posBase(t.clientX, t.clientY);
  }, {passive:true});
  document.getElementById('toque').addEventListener('touchmove', function(ev){
    for (const t of ev.changedTouches){
      if (t.identifier !== toqueId) continue;
      const dx = t.clientX - bx, dy = t.clientY - by;
      if (Math.hypot(dx, dy) > 12) mira = Math.atan2(dy, dx);
      const lim = Math.min(46, Math.hypot(dx, dy)), a = Math.atan2(dy, dx);
      pino.style.left = (bx + Math.cos(a)*lim - 26) + 'px';
      pino.style.top  = (by + Math.sin(a)*lim - 26) + 'px';
    }
  }, {passive:true});
  document.getElementById('toque').addEventListener('touchend', function(){
    toqueId = null;
    base.style.display = pino.style.display = 'none';
  }, {passive:true});

  const btTurbo = document.getElementById('turbo');
  btTurbo.addEventListener('touchstart', function(ev){ ev.preventDefault(); turbo = true;  btTurbo.classList.add('on'); });
  btTurbo.addEventListener('touchend',   function(ev){ ev.preventDefault(); turbo = false; btTurbo.classList.remove('on'); });

  if (window.matchMedia('(pointer: coarse)').matches) document.body.classList.add('toques');

  return {
    iniciar: function(){
      document.getElementById('telaInicio').classList.add('esconde');
      ajustar();
      reiniciar();
      atualizarPlacar();
      requestAnimationFrame(laco);
    }
  };
})();
</script>
</body>
</html>
