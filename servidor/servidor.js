/**
 * Cobrinha — servidor da arena online (autoritativo)
 * Todo mundo que entra cai na MESMA partida. O servidor simula tudo;
 * o navegador só manda a direção e desenha o que recebe.
 */
'use strict';

const http    = require('http');
const crypto  = require('crypto');
const fs      = require('fs');
const path    = require('path');
const { WebSocketServer } = require('ws');
const mysql   = require('mysql2/promise');

// ---------- configuração ----------
const env = {};
fs.readFileSync(path.join(__dirname, '.env'), 'utf8').split('\n').forEach(l => {
  const m = l.match(/^\s*([A-Z_]+)\s*=\s*(.*)\s*$/);
  if (m) env[m[1]] = m[2];
});

const PORTA   = parseInt(env.PORTA || '3210', 10);
const SEGREDO = env.SEGREDO;
const TICK    = 50;                    // 20 quadros por segundo

const bd = mysql.createPool({
  host: env.DB_HOST || 'localhost',
  user: env.DB_USER, password: env.DB_PASS, database: env.DB_NAME,
  waitForConnections: true, connectionLimit: 5
});

const log = (...a) => console.log(new Date().toISOString(), ...a);

// ---------- regras do mundo ----------
const MUNDO   = 3400;
const N_ORBES = 1100;
const MASSA0  = 10;
const VEL     = 2.45;
const VEL_T   = 4.35;
const GIRO    = 0.085;
const CUSTO_T = 0.09;
const MIN_VIVAS = 8;                   // completa com rivais do computador
const MAX_JOGADORES = parseInt(env.MAX_JOGADORES || '60', 10);   // teto de gente humana na arena
const RAIO_VISAO = 1250;

const PALETA = [
  ['#f87171','#b91c1c'], ['#60a5fa','#1d4ed8'], ['#fbbf24','#b45309'],
  ['#c084fc','#6d28d9'], ['#34d399','#047857'], ['#f472b6','#be185d'],
  ['#22d3ee','#0e7490'], ['#a3e635','#4d7c0f'], ['#fb923c','#c2410c']
];
const NOMES_BOT = ['Kaio','Duda','Ravi','Nina','Téo','Bia','Zeca','Lulu','Vini','Malu','Caco','Pipa','Juju','Tato'];

const rnd   = (a, b) => a + Math.random() * (b - a);
const dist2 = (ax, ay, bx, by) => { const dx = ax-bx, dy = ay-by; return dx*dx + dy*dy; };

// ---------- estado ----------
let proxId = 1;
const cobras = new Map();              // id -> cobra
const orbes  = [];
const mortes = [];                     // avisos a entregar

function pontoLivre(){
  for (let tent = 0; tent < 30; tent++){
    const ang = rnd(0, Math.PI*2), r = Math.sqrt(Math.random()) * (MUNDO - 260);
    const p = { x: Math.cos(ang)*r, y: Math.sin(ang)*r };
    let ok = true;
    for (const c of cobras.values()){
      if (!c.viva) continue;
      if (dist2(p.x, p.y, c.x, c.y) < 320*320){ ok = false; break; }
    }
    if (ok) return p;
  }
  const ang = rnd(0, Math.PI*2), r = Math.sqrt(Math.random()) * (MUNDO - 260);
  return { x: Math.cos(ang)*r, y: Math.sin(ang)*r };
}

function novoOrbe(x, y, v){
  const p = (x === undefined) ? pontoLivre() : { x, y };
  return { x: p.x, y: p.y, v: v || 1, c: Math.floor(Math.random()*PALETA.length) };
}
for (let i = 0; i < N_ORBES; i++) orbes.push(novoOrbe());

function criarCobra(dados){
  const p = pontoLivre();
  const ang = rnd(0, Math.PI*2);
  const c = {
    id: proxId++, ...dados,
    x: p.x, y: p.y, ang, mira: ang, massa: MASSA0, viva: true,
    corpo: [], turbo: false, turboAtivo: false, kills: 0,
    entrouEm: Date.now(), maxMassa: MASSA0
  };
  for (let i = 0; i < 40; i++) c.corpo.push({ x: c.x - Math.cos(ang)*i*2, y: c.y - Math.sin(ang)*i*2 });
  cobras.set(c.id, c);
  return c;
}

const raio = c => 6 + Math.min(20, Math.sqrt(c.massa) * 0.75);
const elos = c => Math.floor(14 + c.massa * 1.05);

function nomeDeBotLivre(){
  const usados = new Set([...cobras.values()].map(c => c.nome.toLowerCase()));
  const livres = NOMES_BOT.filter(n => !usados.has(n.toLowerCase()));
  const base = livres.length ? livres : NOMES_BOT;
  let n = base[Math.floor(Math.random()*base.length)];
  // se ainda assim repetir, numera pra nunca haver dois iguais na arena
  let i = 2;
  while (usados.has(n.toLowerCase())) n = base[0] + i++;
  return n;
}

function criarBot(){
  const i = Math.floor(Math.random()*PALETA.length);
  const c = criarCobra({
    bot: true, nome: nomeDeBotLivre(),
    cores: PALETA[i], padrao: ['solido','listras','bolinhas','anelado','escamas'][i % 5],
    arcoiris: false, acessorio: '', ws: null, uid: 0
  });
  c.massa = rnd(12, 45);
  return c;
}

// ---------- IA ----------
function pensarBot(b){
  const r = raio(b);
  if (Math.hypot(b.x, b.y) > MUNDO - 240){ b.mira = Math.atan2(-b.y, -b.x); b.turbo = false; return; }

  let perigo = null, dp = 1e9;
  for (const o of cobras.values()){
    if (o === b || !o.viva) continue;
    if (dist2(b.x, b.y, o.x, o.y) > 420*420) continue;
    for (let j = 0; j < o.corpo.length; j += 4){
      const s = o.corpo[j];
      const dd = dist2(b.x, b.y, s.x, s.y);
      const lim = r + raio(o) + 62;
      if (dd < lim*lim && dd < dp){ dp = dd; perigo = s; }
    }
  }
  if (perigo){ b.mira = Math.atan2(b.y - perigo.y, b.x - perigo.x); b.turbo = b.massa > 25; return; }

  if (!b.alvo || b.alvo.morto || Math.random() < 0.02){
    let melhor = null, dm = 1e9;
    for (let i = 0; i < orbes.length; i += 4){
      const o = orbes[i], dd = dist2(b.x, b.y, o.x, o.y);
      if (dd < dm){ dm = dd; melhor = o; }
    }
    b.alvo = melhor;
  }
  if (b.alvo){
    b.mira = Math.atan2(b.alvo.y - b.y, b.alvo.x - b.x);
    b.turbo = b.massa > 40 && Math.random() < 0.02;
  }
}

// ---------- simulação ----------
function mover(c){
  if (!c.viva) return;
  if (c.bot) pensarBot(c);

  let dif = c.mira - c.ang;
  while (dif >  Math.PI) dif -= Math.PI*2;
  while (dif < -Math.PI) dif += Math.PI*2;
  const lim = GIRO * (c.bot ? 1 : 1.15);
  c.ang += Math.max(-lim, Math.min(lim, dif));

  const podeTurbo = c.turbo && c.massa > MASSA0 + 2;
  if (podeTurbo){
    c.massa -= CUSTO_T;
    if (Math.random() < 0.35){
      const t = c.corpo[c.corpo.length - 1];
      if (t && orbes.length < N_ORBES * 1.6) orbes.push(novoOrbe(t.x + rnd(-6,6), t.y + rnd(-6,6), 0.6));
    }
  }
  c.turboAtivo = podeTurbo;
  const v = podeTurbo ? VEL_T : VEL;
  c.x += Math.cos(c.ang) * v;
  c.y += Math.sin(c.ang) * v;

  if (Math.hypot(c.x, c.y) > MUNDO) return matar(c, null);

  c.corpo.unshift({ x: c.x, y: c.y });
  while (c.corpo.length > elos(c)) c.corpo.pop();
  if (c.massa > c.maxMassa) c.maxMassa = c.massa;
}

function comer(c){
  const r = raio(c) + 12, r2 = r*r;
  for (let i = orbes.length - 1; i >= 0; i--){
    const o = orbes[i];
    if (dist2(c.x, c.y, o.x, o.y) < r2){
      c.massa += o.v; o.morto = true;
      orbes.splice(i, 1);
      if (orbes.length < N_ORBES) orbes.push(novoOrbe());
    }
  }
}

// Grade espacial: em vez de varrer todo corpo de todo mundo, só olha
// os elos que estão na mesma célula da cabeça. Corta MUITO o processamento.
const CELULA = 120;
let grade = new Map();
const chave = (x, y) => ((x / CELULA) | 0) + ':' + ((y / CELULA) | 0);

function montarGrade(){
  grade = new Map();
  for (const o of cobras.values()){
    if (!o.viva) continue;
    for (let j = 2; j < o.corpo.length; j += 2){
      const s = o.corpo[j];
      const k = chave(s.x, s.y);
      let lista = grade.get(k);
      if (!lista) grade.set(k, lista = []);
      lista.push({ o, s });
    }
  }
}

function colidir(c){
  const r = raio(c);
  const cx = (c.x / CELULA) | 0, cy = (c.y / CELULA) | 0;
  for (let gx = cx - 1; gx <= cx + 1; gx++){
    for (let gy = cy - 1; gy <= cy + 1; gy++){
      const lista = grade.get(gx + ':' + gy);
      if (!lista) continue;
      for (let i = 0; i < lista.length; i++){
        const { o, s } = lista[i];
        if (o === c || !o.viva) continue;
        const ro = raio(o), lim = (r + ro*0.85) * (r + ro*0.85);
        if (dist2(c.x, c.y, s.x, s.y) < lim) return matar(c, o);
      }
    }
  }
}

function matar(c, porQuem){
  if (!c.viva) return;
  c.viva = false;

  for (let i = 0; i < c.corpo.length; i += 2){
    const s = c.corpo[i];
    if (orbes.length < N_ORBES * 2)
      orbes.push(novoOrbe(s.x + rnd(-5,5), s.y + rnd(-5,5), Math.max(1, c.massa / c.corpo.length * 1.6)));
  }
  if (porQuem && porQuem.viva){
    porQuem.kills++;
    if (!porQuem.bot) enviar(porQuem, { t: 'k', nome: c.nome });
  }

  if (c.bot){
    cobras.delete(c.id);
  } else {
    salvarPartida(c, porQuem).then(res => {
      enviar(c, { t: 'm', por: porQuem ? porQuem.nome : null, ...res });
    }).catch(e => {
      log('erro ao salvar', e.message);
      enviar(c, { t: 'm', por: porQuem ? porQuem.nome : null });
    });
  }
}

// ---------- placar persistido ----------
async function salvarPartida(c, porQuem){
  const tamanho = Math.floor(c.maxMassa);
  const duracao = Math.round((Date.now() - c.entrouEm) / 1000);
  const pontos  = Math.round(Math.max(0, tamanho - MASSA0) * 5 + c.kills * 100);
  const moedas  = Math.floor(pontos / 5);

  const cx = await bd.getConnection();
  try {
    await cx.beginTransaction();
    await cx.execute(
      'INSERT INTO partidas (usuario_id, modo, pontos, tamanho, duracao, moedas) VALUES (?,?,?,?,?,?)',
      [c.uid, 'online', pontos, tamanho, duracao, moedas]);
    if (moedas > 0)
      await cx.execute('UPDATE usuarios SET moedas = moedas + ? WHERE id = ?', [moedas, c.uid]);

    const [[rec]] = await cx.query('SELECT pontos FROM recordes WHERE usuario_id = ? AND modo = ?', [c.uid, 'online']);
    const atual = rec ? rec.pontos : 0;
    const novo  = pontos > atual;
    if (novo)
      await cx.execute(`INSERT INTO recordes (usuario_id, modo, pontos) VALUES (?,?,?)
                        ON DUPLICATE KEY UPDATE pontos = VALUES(pontos)`, [c.uid, 'online', pontos]);
    await cx.commit();

    const [[p]] = await cx.query('SELECT COUNT(*)+1 n FROM recordes WHERE modo = ? AND pontos > ?',
                                 ['online', Math.max(pontos, atual)]);
    return { pontos, moedas, kills: c.kills, tamanho, recorde: Math.max(pontos, atual), novo, posicao: p.n };
  } catch (e) {
    await cx.rollback(); throw e;
  } finally { cx.release(); }
}

// ---------- envio ----------
function enviar(c, obj){
  if (c.ws && c.ws.readyState === 1) {
    try { c.ws.send(JSON.stringify(obj)); } catch (_) {}
  }
}

function placarGeral(){
  return [...cobras.values()]
    .filter(c => c.viva)
    .sort((a, b) => b.massa - a.massa)
    .slice(0, 10)
    .map(c => ({ i: c.id, n: c.nome, m: Math.floor(c.massa), b: c.bot ? 1 : 0 }));
}

function estadoPara(c){
  const vis = [];
  for (const o of cobras.values()){
    if (!o.viva) continue;
    if (o !== c && dist2(c.x, c.y, o.x, o.y) > (RAIO_VISAO + 600) * (RAIO_VISAO + 600)) continue;
    // cobra longe manda menos pontos do corpo — economiza banda sem perder o visual
    const longe = (o !== c) && dist2(c.x, c.y, o.x, o.y) > 900*900;
    const salto = longe ? 6 : 3;
    const seg = [];
    for (let i = 0; i < o.corpo.length; i += salto){
      seg.push(Math.round(o.corpo[i].x), Math.round(o.corpo[i].y));
    }
    vis.push({
      i: o.id, n: o.nome, c: o.cores, p: o.padrao || 'solido', ai: o.arcoiris ? 1 : 0, ac: o.acessorio || '',
      x: Math.round(o.x), y: Math.round(o.y), a: +o.ang.toFixed(2),
      r: +raio(o).toFixed(1), t: o.turboAtivo ? 1 : 0, s: seg
    });
  }

  // só os orbes que cabem na tela do jogador, com teto de quantidade
  const or = [];
  const lim = RAIO_VISAO * RAIO_VISAO;
  const MAX_ORBES_PACOTE = 260;
  for (const o of orbes){
    if (dist2(c.x, c.y, o.x, o.y) > lim) continue;
    or.push(Math.round(o.x), Math.round(o.y), Math.round(o.v*10), o.c);
    if (or.length >= MAX_ORBES_PACOTE * 4) break;
  }

  const mapa = ((tick % 10) === 0)
    ? [...cobras.values()].filter(k => k.viva).map(k => [Math.round(k.x), Math.round(k.y), k.id === c.id ? 1 : 0])
    : null;

  // placar e minimapa vão só de vez em quando (mudam devagar)
  const leve = (tick % 10) !== 0;
  const pacote = { t: 'e', eu: c.id, k: c.kills, cobras: vis, orbes: or };
  if (!leve){ pacote.placar = placarGeral(); pacote.mapa = mapa; }
  return pacote;
}

// ---------- laço ----------
let tick = 0;
setInterval(() => {
  const jogadores = [...cobras.values()].filter(c => !c.bot && c.viva).length;
  const vivas     = [...cobras.values()].filter(c => c.viva).length;
  if (vivas < MIN_VIVAS && jogadores > 0) criarBot();
  // sem ninguém online, mantém só uns poucos bots pra economizar CPU
  if (jogadores === 0 && vivas > 3){
    for (const c of [...cobras.values()]) if (c.bot && vivas > 3) { cobras.delete(c.id); break; }
  }

  for (const c of cobras.values()) mover(c);
  montarGrade();
  for (const c of cobras.values()) if (c.viva){ comer(c); colidir(c); }

  tick++;
  // Estado vai 10x por segundo; o navegador interpola os quadros no meio.
  if (tick % 2 === 0){
    for (const c of cobras.values()){
      if (!c.bot && c.viva && c.ws && c.ws.readyState === 1) enviar(c, estadoPara(c));
    }
  }
}, TICK);

// ---------- autenticação (bilhete assinado pelo PHP) ----------
function abrirBilhete(tk){
  try {
    const [corpo, assin] = String(tk).split('.');
    const esperado = crypto.createHmac('sha256', SEGREDO).update(corpo).digest('hex');
    if (!crypto.timingSafeEqual(Buffer.from(assin), Buffer.from(esperado))) return null;
    const d = JSON.parse(Buffer.from(corpo, 'base64url').toString('utf8'));
    if (!d.exp || d.exp < Math.floor(Date.now()/1000)) return null;
    return d;
  } catch (_) { return null; }
}

// ---------- servidor ----------
const servidor = http.createServer((req, res) => {
  if (req.url === '/saude'){
    const j = [...cobras.values()].filter(c => !c.bot).length;
    res.writeHead(200, {'Content-Type':'application/json'});
    return res.end(JSON.stringify({ ok: true, jogadores: j, cobras: cobras.size, orbes: orbes.length }));
  }
  res.writeHead(404); res.end();
});

const wss = new WebSocketServer({ server: servidor, path: '/ws' });

wss.on('connection', ws => {
  let cobra = null;
  ws.isAlive = true;
  ws.on('pong', () => { ws.isAlive = true; });

  ws.on('message', buf => {
    let m; try { m = JSON.parse(buf); } catch (_) { return; }

    if (m.t === 'ent'){
      if (cobra) return;
      const d = abrirBilhete(m.tk);
      if (!d) { ws.send(JSON.stringify({ t: 'nao', msg: 'Sessão expirada. Recarregue a página.' })); return ws.close(); }

      const online = [...cobras.values()].filter(c => !c.bot).length;
      if (online >= MAX_JOGADORES){
        ws.send(JSON.stringify({ t: 'nao', msg: 'A arena está lotada (' + MAX_JOGADORES + ' jogadores). Tente em instantes.' }));
        return ws.close();
      }

      // um jogador por conta: derruba a sessão anterior
      for (const c of cobras.values()){
        if (!c.bot && c.uid === d.uid){ enviar(c, { t: 'nao', msg: 'Você entrou em outra aba.' }); if (c.ws) c.ws.close(); matar(c, null); }
      }
      cobra = criarCobra({
        bot: false, uid: d.uid, nome: String(d.nome).slice(0, 16),
        cores: d.cores, padrao: d.p || 'solido', arcoiris: !!d.ai, acessorio: d.ac || '', ws
      });
      ws.send(JSON.stringify({ t: 'ini', id: cobra.id, mundo: MUNDO, nome: cobra.nome }));
      log('entrou', cobra.nome, '#' + cobra.uid, '| online:', [...cobras.values()].filter(c => !c.bot).length);
      return;
    }

    if (!cobra || !cobra.viva) return;
    if (m.t === 'd'){
      if (typeof m.a === 'number' && isFinite(m.a)) cobra.mira = m.a;
      cobra.turbo = !!m.b;
    }
  });

  ws.on('close', () => {
    if (cobra){
      if (cobra.viva) matar(cobra, null);
      cobras.delete(cobra.id);
      log('saiu', cobra.nome, '| online:', [...cobras.values()].filter(c => !c.bot).length);
    }
  });
  ws.on('error', () => {});
});

setInterval(() => {
  wss.clients.forEach(ws => {
    if (!ws.isAlive) return ws.terminate();
    ws.isAlive = false;
    try { ws.ping(); } catch (_) {}
  });
}, 30000);

servidor.listen(PORTA, '127.0.0.1', () => log('Arena online na porta ' + PORTA));

process.on('uncaughtException', e => log('ERRO', e.stack || e.message));
