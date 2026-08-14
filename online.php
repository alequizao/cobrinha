<?php
// Snake.io — arena ONLINE: todo mundo na mesma partida, servidor autoritativo
require __DIR__ . '/config.php';
$u = exigirLogin();

$st = db()->prepare('SELECT pontos FROM recordes WHERE usuario_id = ? AND modo = ?');
$st->execute([$u['id'], 'online']);
$recorde = (int)($st->fetchColumn() ?: 0);

// O WebSocket fica em /ws no subdomínio e em /cobrinha/ws na subpasta
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,user-scalable=no">
<meta name="theme-color" content="#070d1a">
<title>Snake.io — arena online</title>
<link rel="stylesheet" href="estilo.css?v=<?= APP_VERSAO ?>">
<style>
  body{overflow:hidden}
  #arena{position:fixed;inset:0;display:block;background:#070d1a;touch-action:none}
  .hud{position:fixed;pointer-events:none;z-index:5;color:#e7eefc;text-shadow:0 2px 6px rgba(0,0,0,.7)}
  .hud-topo{top:calc(env(safe-area-inset-top) + 10px);left:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .hud-topo .chip{pointer-events:auto;background:rgba(12,20,38,.75);backdrop-filter:blur(6px)}
  .ping{font-size:11px}
  .ping.bom{color:#4ade80} .ping.medio{color:#fbbf24} .ping.ruim{color:#f87171}

  .placarLive{top:calc(env(safe-area-inset-top) + 10px);right:12px;width:180px;
    background:rgba(12,20,38,.75);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.09);
    border-radius:14px;padding:9px 11px;font-size:12.5px}
  .placarLive h4{font-size:10.5px;letter-spacing:.10em;text-transform:uppercase;color:#93a4c6;margin-bottom:6px;
    display:flex;justify-content:space-between}
  .placarLive .l{display:flex;justify-content:space-between;gap:8px;padding:2.5px 0;white-space:nowrap}
  .placarLive .l span:first-child{overflow:hidden;text-overflow:ellipsis}
  .placarLive .l.eu{color:#4ade80;font-weight:700}
  .placarLive .l.bot span:first-child{color:#93a4c6}
  .placarLive .l b{color:#fbbf24;font-weight:600}

  .barra{bottom:calc(env(safe-area-inset-bottom) + 12px);left:12px;display:flex;flex-direction:column;gap:6px}
  .barra .chip{background:rgba(12,20,38,.75);backdrop-filter:blur(6px)}
  #mini{position:fixed;right:12px;bottom:calc(env(safe-area-inset-bottom) + 12px);
    width:112px;height:112px;border-radius:14px;border:1px solid rgba(255,255,255,.09);
    background:rgba(12,20,38,.7);z-index:5;pointer-events:none}

  #toque{position:fixed;inset:0;z-index:4;display:none}
  .toques #toque{display:block}
  #base{position:absolute;width:118px;height:118px;border-radius:50%;
    border:2px solid rgba(255,255,255,.16);background:rgba(255,255,255,.05);display:none}
  #pino{position:absolute;width:52px;height:52px;border-radius:50%;
    background:rgba(74,222,128,.75);box-shadow:0 0 18px rgba(74,222,128,.5);display:none}
  #turbo{position:fixed;right:18px;bottom:calc(env(safe-area-inset-bottom) + 140px);
    width:84px;height:84px;border-radius:50%;z-index:6;display:none;
    border:2px solid rgba(251,191,36,.5);background:rgba(251,191,36,.18);color:#fde68a;
    font-size:12px;font-weight:800;letter-spacing:.06em;backdrop-filter:blur(4px)}
  .toques #turbo{display:block}
  #turbo:active,#turbo.on{background:rgba(251,191,36,.5);transform:scale(.96)}

  .msg{position:fixed;left:50%;top:24%;transform:translateX(-50%);z-index:6;pointer-events:none;
    font-size:19px;font-weight:800;color:#fde68a;text-shadow:0 3px 10px rgba(0,0,0,.8);opacity:0;transition:opacity .25s}
  .msg.ver{opacity:1}
  .status{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:7;text-align:center;
    color:#e7eefc;font-size:15px;background:rgba(12,20,38,.9);padding:16px 22px;border-radius:14px;
    border:1px solid rgba(255,255,255,.1);display:none}
</style>
</head>
<body>
<canvas id="arena"></canvas>
<canvas id="mini" width="224" height="224"></canvas>

<div class="hud hud-topo">
  <a class="chip" href="menu.php">←</a>
  <span class="chip">📏 <b id="hudTam">10</b></span>
  <span class="chip">💀 <b id="hudKills">0</b></span>
  <span class="chip ping" id="hudPing">conectando…</span>
</div>

<div class="hud placarLive">
  <h4><span>Placar ao vivo</span><span id="hudOnline"></span></h4>
  <div id="listaPlacar"></div>
</div>

<div class="hud barra"><span class="chip">🏅 Recorde <b><?= $recorde ?></b></span></div>

<div id="toque"><div id="base"></div><div id="pino"></div></div>
<button id="turbo">TURBO</button>
<div class="msg" id="msg"></div>
<div class="status" id="status"></div>

<div class="overlay" id="telaInicio">
  <div class="card">
    <h2>🐍 Snake.io — AO VIVO</h2>
    <p class="mudo" style="margin:10px 0">
      Uma arena só, para todo mundo. Quem estiver online agora joga junto com você, em tempo real.
      Coma os orbes, cresça e faça os outros baterem em você.
    </p>
    <p class="mudo" style="font-size:12px">
      Computador: mouse guia · clique ou Espaço = turbo<br>
      Celular: arraste com um dedo · <b>toque com o segundo dedo</b> ou use o botão TURBO
    </p>
    <button class="btn" onclick="Online.entrar()">Entrar na arena</button>
    <a class="btn sec" href="menu.php" style="text-align:center">Voltar</a>
  </div>
</div>

<div class="overlay esconde" id="telaFim">
  <div class="card">
    <h2 id="fimTitulo">Você foi eliminado</h2>
    <div class="pts" id="fimPontos">0</div>
    <p class="mudo" id="fimInfo">—</p>
    <button class="btn" onclick="location.reload()">Voltar pra arena</button>
    <a class="btn sec" href="menu.php" style="text-align:center">Menu</a>
    <a class="btn sec" href="ranking.php?modo=online" style="text-align:center">🏆 Ranking</a>
  </div>
</div>

<script>
const CONF = {
  bilhete: <?= json_encode(bilheteArena($u)) ?>,
  ws: (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + <?= json_encode($base . '/ws') ?>
};

const Online = (function(){
  'use strict';
  const tela = document.getElementById('arena'), ctx = tela.getContext('2d');
  const mini = document.getElementById('mini'),  mtx = mini.getContext('2d');

  let L = 0, A = 0, dpr = 1, MUNDO = 3400;
  let sock = null, meuId = 0, morto = false, entrou = false;
  let mira = 0, turbo = false, camX = 0, camY = 0, zoom = 1;
  let ultimoEstado = null, anterior = null, tEstado = 0, ping = 0, ultimoPing = 0;
  let mapaAtual = {}, mapaAnterior = {};
  const INTERVALO = 100;    // o servidor manda estado a cada 100ms

  // Mistura a posição antiga com a nova pra desenhar liso a 60fps
  function suavizar(c, f){
    const ant = mapaAnterior[c.i];
    if (!ant || ant.s.length !== c.s.length) return c;
    const s = new Array(c.s.length);
    for (let i = 0; i < c.s.length; i++) s[i] = ant.s[i] + (c.s[i] - ant.s[i]) * f;
    let da = c.a - ant.a;
    while (da >  Math.PI) da -= Math.PI*2;
    while (da < -Math.PI) da += Math.PI*2;
    return {
      i: c.i, n: c.n, c: c.c, p: c.p, ai: c.ai, ac: c.ac, t: c.t, r: c.r,
      x: ant.x + (c.x - ant.x) * f,
      y: ant.y + (c.y - ant.y) * f,
      a: ant.a + da * f,
      s: s
    };
  }

  const PALETA = [
    ['#f87171','#b91c1c'], ['#60a5fa','#1d4ed8'], ['#fbbf24','#b45309'],
    ['#c084fc','#6d28d9'], ['#34d399','#047857'], ['#f472b6','#be185d'],
    ['#22d3ee','#0e7490'], ['#a3e635','#4d7c0f'], ['#fb923c','#c2410c']
  ];

  // ---------- desenho barato: azulejo de fundo e orbes prontos ----------
  let azulejo = null, azulejoZoom = 0, padraoFundo = null;
  function desenharFundo(){
    const passo = Math.max(24, Math.round(60*zoom));
    if (azulejoZoom !== passo){
      azulejoZoom = passo;
      azulejo = document.createElement('canvas');
      azulejo.width = azulejo.height = passo;
      const a = azulejo.getContext('2d');
      a.fillStyle = 'rgba(255,255,255,.05)';
      a.beginPath(); a.arc(passo/2, passo/2, 1.7, 0, 6.2832); a.fill();
      padraoFundo = ctx.createPattern(azulejo, 'repeat');
    }
    const dx = ((-camX*zoom + L/2) % passo + passo) % passo;
    const dy = ((-camY*zoom + A/2) % passo + passo) % passo;
    ctx.save();
    ctx.translate(dx - passo/2, dy - passo/2);
    ctx.fillStyle = padraoFundo;
    ctx.fillRect(-passo, -passo, L + passo*2, A + passo*2);
    ctx.restore();
  }

  // orbes viram imagenzinhas prontas (com brilho já embutido) — nada de shadowBlur no laço
  const spriteOrbe = {};
  function orbeSprite(cor){
    if (spriteOrbe[cor]) return spriteOrbe[cor];
    const T = 34, cv = document.createElement('canvas');
    cv.width = cv.height = T;
    const g = cv.getContext('2d');
    const rad = g.createRadialGradient(T/2, T/2, 1, T/2, T/2, T/2);
    rad.addColorStop(0,   '#ffffff');
    rad.addColorStop(0.35, cor);
    rad.addColorStop(0.6,  cor);
    rad.addColorStop(1,   'rgba(0,0,0,0)');
    g.fillStyle = rad;
    g.beginPath(); g.arc(T/2, T/2, T/2, 0, 6.2832); g.fill();
    return spriteOrbe[cor] = cv;
  }

  // Qualidade adaptativa: 2 = tudo ligado, 1 = sem brilho/padrões, 0 = mínimo
  let QUALIDADE = 2, fpsQuadros = 0, fpsMarco = 0, fpsAtual = 60;
  function medirFPS(agora){
    fpsQuadros++;
    if (agora - fpsMarco >= 1000){
      fpsAtual = fpsQuadros * 1000 / (agora - fpsMarco);
      fpsQuadros = 0; fpsMarco = agora;
      if (fpsAtual < 34 && QUALIDADE > 0){
        QUALIDADE--;
        if (QUALIDADE === 0){ dpr = 1; ajustar(true); }
      } else if (fpsAtual > 55 && QUALIDADE < 2){
        QUALIDADE++;
      }
    }
  }

  function ajustar(manterDpr){
    if (!manterDpr) dpr = Math.min(window.devicePixelRatio || 1, 2);
    L = window.innerWidth; A = window.innerHeight;
    tela.width = Math.round(L*dpr); tela.height = Math.round(A*dpr);
    tela.style.width = L+'px'; tela.style.height = A+'px';
    ctx.setTransform(dpr,0,0,dpr,0,0);
  }
  window.addEventListener('resize', ajustar);

  function status(txt){
    const el = document.getElementById('status');
    if (!txt){ el.style.display = 'none'; return; }
    el.textContent = txt; el.style.display = 'block';
  }

  let avisoTimer = null;
  function aviso(txt){
    const el = document.getElementById('msg');
    el.textContent = txt; el.classList.add('ver');
    clearTimeout(avisoTimer);
    avisoTimer = setTimeout(function(){ el.classList.remove('ver'); }, 1600);
  }

  // ---------- conexão ----------
  function conectar(){
    status('Conectando na arena…');
    sock = new WebSocket(CONF.ws);

    sock.onopen = function(){
      sock.send(JSON.stringify({ t:'ent', tk: CONF.bilhete }));
      ultimoPing = Date.now();
    };

    sock.onmessage = function(ev){
      const m = JSON.parse(ev.data);

      if (m.t === 'ini'){
        meuId = m.id; MUNDO = m.mundo; entrou = true;
        status('');
        return;
      }
      if (m.t === 'e'){
        ping = Date.now() - ultimoPing; ultimoPing = Date.now();
        anterior = ultimoEstado; ultimoEstado = m; tEstado = performance.now();
        // guarda a posição de cada cobra pra suavizar o movimento entre pacotes
        mapaAnterior = mapaAtual;
        mapaAtual = {};
        for (const c of m.cobras) mapaAtual[c.i] = c;
        document.getElementById('hudKills').textContent = m.k;
        if (m.placar) atualizarPlacar(m);
        const eu = m.cobras.find(function(c){ return c.i === m.eu; });
        if (eu){
          if (!camX && !camY){ camX = eu.x; camY = eu.y; }
          document.getElementById('hudTam').textContent = Math.round(eu.r > 0 ? massaDoRaio(eu.r) : 10);
        }
        const p = document.getElementById('hudPing');
        p.textContent = ping + 'ms';
        p.className = 'chip ping ' + (ping < 120 ? 'bom' : ping < 260 ? 'medio' : 'ruim');
        return;
      }
      if (m.t === 'k'){ aviso('💀 Você eliminou ' + m.nome + '!'); return; }
      if (m.t === 'm'){ mostrarFim(m); return; }
      if (m.t === 'nao'){ status(m.msg); return; }
    };

    sock.onclose = function(){
      if (!morto) status('Conexão perdida. Recarregue a página.');
    };
    sock.onerror = function(){ status('Não deu para conectar na arena.'); };
  }

  const massaDoRaio = function(r){ const s = (r - 6) / 0.75; return s*s; };

  // manda a direção 15x por segundo
  setInterval(function(){
    if (sock && sock.readyState === 1 && entrou && !morto)
      sock.send(JSON.stringify({ t:'d', a:+mira.toFixed(3), b: turbo ? 1 : 0 }));
  }, 66);

  // ---------- desenho ----------
  // montaria: a cobrinha vai "pilotando" — fica embaixo da cabeça
  function montaria(tipo, hx, hy, rr, ang, turbo){
    ctx.save();
    ctx.translate(hx, hy); ctx.rotate(ang);
    if (tipo === 'prancha'){
      ctx.fillStyle = '#f59e0b';
      ctx.beginPath();
      ctx.ellipse(0, rr*0.9, rr*2.1, rr*0.62, 0, 0, 6.2832);
      ctx.fill();
      ctx.fillStyle = '#7c2d12';
      ctx.fillRect(-rr*1.4, rr*1.3, rr*0.5, rr*0.5);
      ctx.fillRect( rr*0.9, rr*1.3, rr*0.5, rr*0.5);
    } else if (tipo === 'foguete'){
      ctx.fillStyle = '#e2e8f0';
      ctx.beginPath();
      ctx.ellipse(0, rr*1.0, rr*2.2, rr*0.72, 0, 0, 6.2832);
      ctx.fill();
      ctx.fillStyle = '#dc2626';
      ctx.beginPath();
      ctx.moveTo(rr*2.0, rr*1.0); ctx.lineTo(rr*2.9, rr*1.0); ctx.lineTo(rr*2.0, rr*0.4);
      ctx.closePath(); ctx.fill();
      // chama do escapamento
      const f = (turbo ? 1.9 : 1) * (0.8 + Math.random()*0.4);
      const g = ctx.createLinearGradient(-rr*2.1, 0, -rr*2.1 - rr*2.4*f, 0);
      g.addColorStop(0, 'rgba(251,191,36,.95)');
      g.addColorStop(1, 'rgba(239,68,68,0)');
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.moveTo(-rr*2.0, rr*0.55); ctx.lineTo(-rr*2.0, rr*1.45);
      ctx.lineTo(-rr*2.0 - rr*2.4*f, rr*1.0);
      ctx.closePath(); ctx.fill();
    }
    ctx.restore();
  }

  function acessorioNaCabeca(ac, hx, hy, rr, ang){
    if (!ac) return;
    if (ac === 'asas' || ac === 'asas_fogo'){
      const bate = Math.sin(Date.now()/110) * 0.45;      // batida das asas
      const q = ac === 'asas_fogo' ? ['#fb923c','#dc2626'] : ['#f8fafc','#cbd5e1'];
      ctx.save();
      ctx.translate(hx, hy); ctx.rotate(ang);
      for (const s of [-1, 1]){
        ctx.save();
        ctx.scale(1, s);
        ctx.rotate(bate);
        const g = ctx.createLinearGradient(0, 0, -rr*0.6, -rr*2.6);
        g.addColorStop(0, q[0]); g.addColorStop(1, q[1]);
        ctx.fillStyle = g;
        if (ac === 'asas_fogo'){ ctx.shadowBlur = 18; ctx.shadowColor = '#f97316'; }
        ctx.beginPath();
        ctx.moveTo(0, -rr*0.5);
        ctx.quadraticCurveTo(-rr*0.4, -rr*2.6, -rr*2.3, -rr*2.0);
        ctx.quadraticCurveTo(-rr*1.4, -rr*0.9, 0, -rr*0.5);
        ctx.closePath(); ctx.fill();
        ctx.shadowBlur = 0;
        ctx.restore();
      }
      ctx.restore();
      return;
    }
    ctx.save();
    ctx.translate(hx, hy); ctx.rotate(ang + Math.PI/2);
    if (ac === 'chapeu'){
      ctx.fillStyle = '#dc2626';
      ctx.fillRect(-rr*1.1, -rr*1.5, rr*2.2, rr*0.35);
      ctx.fillRect(-rr*0.6, -rr*2.2, rr*1.2, rr*0.8);
    } else if (ac === 'cartola'){
      ctx.fillStyle = '#111827';
      ctx.fillRect(-rr*1.25, -rr*1.5, rr*2.5, rr*0.3);
      ctx.fillRect(-rr*0.65, -rr*2.7, rr*1.3, rr*1.25);
      ctx.fillStyle = '#f59e0b';
      ctx.fillRect(-rr*0.65, -rr*1.75, rr*1.3, rr*0.28);
    } else if (ac === 'coroa'){
      ctx.fillStyle = '#fbbf24';
      ctx.beginPath();
      ctx.moveTo(-rr, -rr*1.2); ctx.lineTo(-rr, -rr*2.1); ctx.lineTo(-rr*0.5, -rr*1.6);
      ctx.lineTo(0, -rr*2.3);   ctx.lineTo(rr*0.5, -rr*1.6); ctx.lineTo(rr, -rr*2.1);
      ctx.lineTo(rr, -rr*1.2);  ctx.closePath(); ctx.fill();
    } else if (ac === 'oculos'){
      ctx.strokeStyle = '#0f172a'; ctx.lineWidth = Math.max(1.5, rr*0.16);
      ctx.fillStyle = 'rgba(15,23,42,.82)';
      ctx.beginPath(); ctx.arc(-rr*0.5, -rr*0.15, rr*0.44, 0, 6.2832); ctx.fill();
      ctx.beginPath(); ctx.arc( rr*0.5, -rr*0.15, rr*0.44, 0, 6.2832); ctx.fill();
      ctx.beginPath(); ctx.moveTo(-rr*0.1, -rr*0.15); ctx.lineTo(rr*0.1, -rr*0.15); ctx.stroke();
    } else if (ac === 'laco'){
      ctx.fillStyle = '#ec4899';
      ctx.beginPath(); ctx.arc(-rr*0.55, -rr*1.5, rr*0.5, 0, 6.2832); ctx.fill();
      ctx.beginPath(); ctx.arc( rr*0.55, -rr*1.5, rr*0.5, 0, 6.2832); ctx.fill();
      ctx.fillStyle = '#be185d';
      ctx.beginPath(); ctx.arc(0, -rr*1.5, rr*0.26, 0, 6.2832); ctx.fill();
    } else if (ac === 'chifres'){
      ctx.fillStyle = '#f1f5f9';
      for (const s of [-1, 1]){
        ctx.beginPath();
        ctx.moveTo(s*rr*0.55, -rr*1.05);
        ctx.lineTo(s*rr*0.95, -rr*2.15);
        ctx.lineTo(s*rr*0.25, -rr*1.35);
        ctx.closePath(); ctx.fill();
      }
    } else if (ac === 'aura'){
      const g = ctx.createRadialGradient(0, 0, rr*0.8, 0, 0, rr*2.4);
      g.addColorStop(0, 'rgba(251,146,60,.55)');
      g.addColorStop(1, 'rgba(251,146,60,0)');
      ctx.fillStyle = g;
      ctx.beginPath(); ctx.arc(0, 0, rr*2.4, 0, 6.2832); ctx.fill();
    }
    ctx.restore();
  }

  // cor de cada elo conforme o padrão da skin
  function corElo(c, k, cores){
    if (c.ai) return 'hsl(' + ((k*11 + Date.now()/14) % 360) + ',85%,62%)';
    switch (c.p){
      case 'listras':  return (k % 6 < 3) ? cores[0] : cores[1];
      case 'anelado':  return (k % 10 < 2) ? cores[1] : cores[0];
      case 'zebra':    return (k % 7 < 2) ? cores[1] : cores[0];
      case 'escamas':  return cores[0];
      case 'bolinhas': return cores[0];
      case 'neon':     return cores[0];
      default:         return (k % 12 < 6) ? cores[0] : cores[1];
    }
  }

  function desenharCobra(c){
    const cores = c.c || ['#4ade80','#16a34a'];
    const rr = c.r * zoom;

    // O corpo vira UM traço só (rápido), em vez de um círculo por elo.
    const xs = [], ys = [];
    for (let i = 0; i < c.s.length; i += 2){
      xs.push((c.s[i] - camX) * zoom + L/2);
      ys.push((c.s[i+1] - camY) * zoom + A/2);
    }
    if (!xs.length) return;

    // brilho custa caro: só na SUA cobra e só quando a qualidade permite
    const brilho = QUALIDADE > 0 && c.i === meuId && (c.t || c.p === 'neon');
    if (brilho){ ctx.shadowBlur = c.t ? 20 : 11; ctx.shadowColor = cores[0]; }

    ctx.lineCap = 'round'; ctx.lineJoin = 'round';

    // traça o caminho uma vez e reaproveita (contorno + corpo + brilho)
    ctx.beginPath();
    ctx.moveTo(xs[0], ys[0]);
    for (let i = 1; i < xs.length; i++) ctx.lineTo(xs[i], ys[i]);

    // contorno escuro — é ele que dá a cara de desenho animado
    if (QUALIDADE > 0 && rr > 3){
      ctx.lineWidth = rr * 2 + Math.max(2, rr*0.42);
      ctx.strokeStyle = 'rgba(6,12,26,.85)';
      ctx.stroke();
    }

    ctx.lineWidth = rr * 2;
    ctx.strokeStyle = cores[0];
    ctx.stroke();
    if (brilho) ctx.shadowBlur = 0;

    // Padrão por cima. Se a cobra está pequena na tela, pula (não dá pra ver mesmo).
    if (rr > 3.2 && QUALIDADE > 0 && c.p && c.p !== 'solido'){
      if (c.p === 'listras' || c.p === 'zebra' || c.p === 'anelado'){
        const larg = (c.p === 'anelado') ? 2 : (c.p === 'zebra' ? 1 : 2);
        const salto = (c.p === 'anelado') ? 5 : (c.p === 'zebra' ? 4 : 4);
        ctx.strokeStyle = cores[1];
        ctx.lineWidth = rr * 2;
        for (let i = 0; i + larg < xs.length; i += salto){
          ctx.beginPath();
          ctx.moveTo(xs[i], ys[i]);
          for (let j = 1; j <= larg; j++) ctx.lineTo(xs[i+j], ys[i+j]);
          ctx.stroke();
        }
      } else if (c.p === 'bolinhas' || c.p === 'escamas'){
        ctx.fillStyle = cores[1];
        const raioD = c.p === 'bolinhas' ? rr*0.42 : rr*0.3;
        for (let i = 1; i < xs.length; i += 2){
          if (xs[i] < -rr || ys[i] < -rr || xs[i] > L+rr || ys[i] > A+rr) continue;
          ctx.beginPath(); ctx.arc(xs[i], ys[i], raioD, 0, 6.2832); ctx.fill();
        }
      } else if (c.p === 'neon'){
        ctx.strokeStyle = cores[1];
        ctx.lineWidth = rr;
        ctx.beginPath();
        ctx.moveTo(xs[0], ys[0]);
        for (let i = 1; i < xs.length; i++) ctx.lineTo(xs[i], ys[i]);
        ctx.stroke();
      }
    }

    if (c.ai){   // arco-íris continua colorindo elo a elo
      for (let i = 0; i < xs.length; i += 2){
        ctx.fillStyle = 'hsl(' + ((i*11 + Date.now()/14) % 360) + ',85%,62%)';
        ctx.beginPath(); ctx.arc(xs[i], ys[i], rr, 0, 6.2832); ctx.fill();
      }
    }

    // faixa de luz por cima: dá volume, como nos desenhos
    if (QUALIDADE > 1 && rr > 4){
      ctx.save();
      ctx.globalAlpha = 0.22;
      ctx.lineWidth = rr * 0.62;
      ctx.strokeStyle = '#ffffff';
      ctx.beginPath();
      ctx.moveTo(xs[0], ys[0] - rr*0.42);
      for (let i = 1; i < xs.length; i++) ctx.lineTo(xs[i], ys[i] - rr*0.42);
      ctx.stroke();
      ctx.restore();
    }

    // montaria embaixo da cabeça (desenha antes do corpo da cabeça)
    if (c.ac === 'prancha' || c.ac === 'foguete'){
      const mx = (c.x - camX)*zoom + L/2, my = (c.y - camY)*zoom + A/2;
      montaria(c.ac, mx, my, rr, c.a, c.t);
    }

    const hx = (c.x - camX) * zoom + L/2;
    const hy = (c.y - camY) * zoom + A/2;
    // asas e aura ficam ATRÁS da cabeça
    if (c.ac === 'aura' || c.ac === 'asas' || c.ac === 'asas_fogo')
      acessorioNaCabeca(c.ac, hx, hy, rr, c.a);

    // cabeça um pouco maior que o corpo, com contorno
    if (QUALIDADE > 0 && rr > 3){
      ctx.fillStyle = 'rgba(6,12,26,.85)';
      ctx.beginPath(); ctx.arc(hx, hy, rr*1.12 + Math.max(1.5, rr*0.2), 0, 6.2832); ctx.fill();
    }
    ctx.fillStyle = cores[0];
    ctx.beginPath(); ctx.arc(hx, hy, rr*1.12, 0, 6.2832); ctx.fill();

    const px = Math.cos(c.a), py = Math.sin(c.a);
    const ox = -py*rr*0.52, oy = px*rr*0.52;
    for (const s of [1, -1]){
      const ex = hx + px*rr*0.34 + ox*s, ey = hy + py*rr*0.34 + oy*s;
      // olhos grandes, brancos, com contorno escuro e pupila que olha pra frente
      if (QUALIDADE > 0 && rr > 3){
        ctx.fillStyle = 'rgba(6,12,26,.85)';
        ctx.beginPath(); ctx.arc(ex, ey, rr*0.50, 0, 6.2832); ctx.fill();
      }
      ctx.fillStyle = '#fff';
      ctx.beginPath(); ctx.arc(ex, ey, rr*0.42, 0, 6.2832); ctx.fill();
      ctx.fillStyle = '#0b1220';
      ctx.beginPath(); ctx.arc(ex + px*rr*0.17, ey + py*rr*0.17, rr*0.21, 0, 6.2832); ctx.fill();
      if (QUALIDADE > 1){
        ctx.fillStyle = 'rgba(255,255,255,.9)';
        ctx.beginPath(); ctx.arc(ex - px*rr*0.05 - py*rr*0.12, ey - py*rr*0.05 + px*rr*0.12, rr*0.09, 0, 6.2832); ctx.fill();
      }
    }
    if (c.ac && ['aura','asas','asas_fogo','prancha','foguete'].indexOf(c.ac) < 0)
      acessorioNaCabeca(c.ac, hx, hy, rr, c.a);

    ctx.font = '600 ' + Math.max(10, 13*zoom) + 'px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillStyle = (c.i === meuId) ? '#e7eefc' : 'rgba(231,238,252,.62)';
    ctx.fillText(c.n, hx, hy - rr*1.6 - 8);
  }

  function desenhar(agora){
    medirFPS(agora || performance.now());
    ctx.fillStyle = '#070d1a'; ctx.fillRect(0, 0, L, A);
    const est = ultimoEstado;
    if (!est){ requestAnimationFrame(desenhar); return; }

    // fator de interpolação: 0 = pacote anterior, 1 = pacote atual
    const f = Math.max(0, Math.min(1, (performance.now() - tEstado) / INTERVALO));
    const cobrasDesenho = est.cobras.map(function(c){ return suavizar(c, f); });

    const eu = cobrasDesenho.find(function(c){ return c.i === est.eu; });
    if (eu){
      camX += (eu.x - camX) * 0.25;
      camY += (eu.y - camY) * 0.25;
      const alvo = Math.max(0.52, Math.min(1.05, 26 / eu.r));
      zoom += (alvo - zoom) * 0.05;
    }

    // fundo: um azulejo pronto repetido de uma vez só (antes era ponto a ponto)
    desenharFundo();

    ctx.strokeStyle = 'rgba(239,68,68,.5)'; ctx.lineWidth = 6*zoom;
    ctx.beginPath(); ctx.arc((0-camX)*zoom + L/2, (0-camY)*zoom + A/2, MUNDO*zoom, 0, 6.2832); ctx.stroke();

    const t = Date.now()/400;
    for (let i = 0; i < est.orbes.length; i += 4){
      const sx = (est.orbes[i] - camX)*zoom + L/2;
      const sy = (est.orbes[i+1] - camY)*zoom + A/2;
      if (sx < -20 || sy < -20 || sx > L+20 || sy > A+20) continue;
      const v = est.orbes[i+2]/10;
      const cor = PALETA[est.orbes[i+3] % PALETA.length][0];
      const rr = (3.6 + Math.min(7, v*1.4)) * zoom * (1 + Math.sin(t + i)*0.10);
      const img = orbeSprite(cor);
      ctx.drawImage(img, sx - rr*1.6, sy - rr*1.6, rr*3.2, rr*3.2);
    }

    for (const c of cobrasDesenho) if (c.i !== est.eu) desenharCobra(c);
    if (eu) desenharCobra(eu);

    requestAnimationFrame(desenhar);
  }

  function desenharMini(est){
    const W = mini.width, esc = (W/2 - 6)/MUNDO;
    mtx.clearRect(0,0,W,W);
    mtx.strokeStyle = 'rgba(239,68,68,.45)'; mtx.lineWidth = 2;
    mtx.beginPath(); mtx.arc(W/2, W/2, MUNDO*esc, 0, 6.2832); mtx.stroke();
    for (const p of est.mapa){
      mtx.fillStyle = p[2] ? '#4ade80' : 'rgba(231,238,252,.45)';
      mtx.beginPath(); mtx.arc(W/2 + p[0]*esc, W/2 + p[1]*esc, p[2] ? 5 : 3, 0, 6.2832); mtx.fill();
    }
  }

  function atualizarPlacar(est){
    let html = '', humanos = 0;
    est.placar.forEach(function(p, i){
      if (!p.b) humanos++;
      html += '<div class="l ' + (p.i === est.eu ? 'eu' : (p.b ? 'bot' : '')) + '"><span>' +
              (i+1) + '. ' + p.n.replace(/[<>&]/g,'') + (p.b ? ' 🤖' : '') +
              '</span><b>' + p.m + '</b></div>';
    });
    document.getElementById('listaPlacar').innerHTML = html;
    document.getElementById('hudOnline').textContent = '🟢 ' + humanos;
    if (est.mapa) desenharMini(est);
  }

  function mostrarFim(d){
    morto = true;
    document.getElementById('telaFim').classList.remove('esconde');
    document.getElementById('fimTitulo').textContent =
      d.novo ? '🎉 Novo recorde!' : (d.por ? 'Você bateu em ' + d.por : 'Você foi eliminado');
    document.getElementById('fimPontos').textContent = d.pontos !== undefined ? d.pontos : '—';
    document.getElementById('fimInfo').innerHTML = (d.pontos === undefined) ? 'Partida encerrada.' :
      '📏 ' + d.tamanho + ' &nbsp;·&nbsp; 💀 ' + d.kills + ' &nbsp;·&nbsp; 🪙 +' + d.moedas +
      ' &nbsp;·&nbsp; #' + d.posicao + ' no ranking';
  }

  // ---------- controles ----------
  document.addEventListener('mousemove', function(ev){ mira = Math.atan2(ev.clientY - A/2, ev.clientX - L/2); });
  document.addEventListener('mousedown', function(){ turbo = true; });
  document.addEventListener('mouseup',   function(){ turbo = false; });
  document.addEventListener('keydown', function(ev){ if (ev.code === 'Space'){ ev.preventDefault(); turbo = true; } });
  document.addEventListener('keyup',   function(ev){ if (ev.code === 'Space') turbo = false; });

  const base = document.getElementById('base'), pino = document.getElementById('pino');
  const areaToque = document.getElementById('toque');
  let toqueId = null, bx = 0, by = 0;
  let ultimoToque = 0, turboTravado = false;

  areaToque.addEventListener('touchstart', function(ev){
    for (const t of ev.changedTouches){
      if (toqueId === null){
        // primeiro dedo: joystick de direção
        toqueId = t.identifier; bx = t.clientX; by = t.clientY;
        base.style.display = pino.style.display = 'block';
        base.style.left = (bx-59)+'px'; base.style.top = (by-59)+'px';
        pino.style.left = (bx-26)+'px'; pino.style.top = (by-26)+'px';
      } else {
        // segundo dedo: segura pra dar turbo
        turbo = true;
      }
    }
    // duplo toque rápido trava/destrava o turbo
    const agora = Date.now();
    if (agora - ultimoToque < 300){
      turboTravado = !turboTravado;
      turbo = turboTravado;
      document.getElementById('turbo').classList.toggle('on', turboTravado);
    }
    ultimoToque = agora;
  }, {passive:true});

  areaToque.addEventListener('touchmove', function(ev){
    for (const t of ev.changedTouches){
      if (t.identifier !== toqueId) continue;
      const dx = t.clientX - bx, dy = t.clientY - by, h = Math.hypot(dx, dy);
      if (h > 12) mira = Math.atan2(dy, dx);
      const lim = Math.min(46, h), a = Math.atan2(dy, dx);
      pino.style.left = (bx + Math.cos(a)*lim - 26)+'px';
      pino.style.top  = (by + Math.sin(a)*lim - 26)+'px';
    }
  }, {passive:true});

  function soltouDedo(ev){
    for (const t of ev.changedTouches){
      if (t.identifier === toqueId){
        toqueId = null;
        base.style.display = pino.style.display = 'none';
      } else if (!turboTravado){
        turbo = false;       // soltou o segundo dedo
      }
    }
    if (ev.touches.length === 0 && !turboTravado) turbo = false;
  }
  areaToque.addEventListener('touchend', soltouDedo, {passive:true});
  areaToque.addEventListener('touchcancel', soltouDedo, {passive:true});

  const bt = document.getElementById('turbo');
  bt.addEventListener('touchstart', function(ev){ ev.preventDefault(); turbo = true;  bt.classList.add('on'); });
  bt.addEventListener('touchend',   function(ev){ ev.preventDefault(); if (!turboTravado){ turbo = false; bt.classList.remove('on'); } });
  bt.addEventListener('click', function(){ turbo = !turbo; bt.classList.toggle('on', turbo); });

  if (window.matchMedia('(pointer: coarse)').matches) document.body.classList.add('toques');

  return {
    entrar: function(){
      document.getElementById('telaInicio').classList.add('esconde');
      ajustar(); conectar(); requestAnimationFrame(desenhar);
    }
  };
})();
</script>
</body>
</html>
