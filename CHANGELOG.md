# Cobrinha — histórico de versões

Regra do projeto: **toda alteração de código sobe a versão** em `config.php` (`APP_VERSAO`)
e ganha uma linha aqui.

## 1.8.0 — desempenho no modo online
- Servidor: colisão por **grade espacial** (antes varria todo o corpo de todas as cobras).
- Servidor: estado enviado 10x/s em vez de 20x/s; corpo de cobras distantes com menos pontos;
  teto de orbes por pacote; placar e minimapa só a cada 10 quadros.
- Cliente: **interpolação** entre pacotes — desenha liso a 60fps mesmo recebendo 10 estados/s.
- Cliente: corpo desenhado como **um traço só** no lugar de um círculo por elo.
- Resultado medido: ~33 KB/s por jogador, CPU do servidor baixa com 10 jogadores simultâneos.

## 1.7.0
- Interpolação e otimizações iniciais de desenho.

## 1.6.0 — skins e acessórios de verdade
- 15 skins com **padrões desenhados por código**: listras, anelado, bolinhas, escamas, zebra e neon
  (Abelha, Onça, Zebra, Dragão, Naja, Gelo, Galáxia, Ouro...).
- Acessórios ganharam **asas**, **asas de fogo** e **montarias** (prancha e foguete, com chama no turbo).
- Prévia das skins no menu já mostra o padrão.

## 1.5.0 — arena online + identidade única
- Servidor Node autoritativo (`servidor/servidor.js`) com WebSocket: **todo mundo na mesma partida**.
- Proxy WebSocket no Apache nas duas URLs; serviço systemd `cobrinha-arena`.
- Bilhete assinado (HMAC) emitido pelo PHP autentica o jogador no servidor Node.
- Placar, moedas e recordes gravados pelo próprio servidor — impossível trapacear.
- Modo "Treino" (offline) separado do "Snake.io" (online).
- **O usuário virou o nome da cobrinha**: nomes duplicados deixaram de existir; bots nunca repetem
  nome de jogador; a mesma conta não entra duas vezes na arena.
- Acessórios de cabeça (chapéu, óculos, coroa, laço, cartola, chifres, aura).

## 1.4.0
- Modo **Snake.io** (`arena.php`): mundo aberto circular, 11 cobras rivais com IA, orbes de
  crescimento, turbo que consome massa, câmera com zoom dinâmico, placar ao vivo, minimapa,
  joystick e botão TURBO no celular. Cobra morta vira comida no mapa.
- `api.php`: pontuação própria do modo Arena (massa + 100 por eliminação) com checagem de
  plausibilidade.
- `config.php`: cada modo agora aponta para o seu arquivo (`arquivo`), e menu/ranking usam isso.

## 1.3.0
- Vhost Apache `cobrinha.alequizao.com` apontando para a mesma pasta.
- `instalar.php` bloqueado (403) pelo vhost do subdomínio.

## 1.2.0
- Painel do administrador (`admin.php`): busca de jogadores, ajuste de moedas, redefinição de
  senha, liberação de skins, zerar histórico e exclusão de conta.
- Coluna `admin` na tabela `usuarios`; usuário administrador criado no instalador.

## 1.1.0
- Ranking global por modo e tela de estatísticas do jogador.

## 1.0.0
- Versão inicial: login/cadastro, jogo em canvas, 4 modos, 8 skins com moedas, banco `cobrinha`.
