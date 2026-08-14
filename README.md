# 🐍 Cobrinha — jogo da cobrinha multiplayer online

Jogo da cobrinha jogável no navegador, com **modo solo** e **arena multiplayer em tempo
real**, ranking, contas de jogador e painel administrativo. Front em PHP + Canvas, servidor
de tempo real em Node.js com WebSocket.

## ✨ Funcionalidades

- **Modo solo** com pontuação e dificuldade progressiva
- **Arena online**: várias cobras na mesma partida, em tempo real
- **Ranking** geral de jogadores
- **Contas de jogador**: cadastro, login e histórico
- **Painel administrativo** para gerenciar jogadores e partidas
- Colisão por **grade espacial** — o servidor não varre todos os corpos a cada quadro
- Estado sincronizado 10×/s, com simplificação do corpo de cobras distantes

## 📸 Tela

[![Cobrinha — jogo da cobrinha multiplayer online no navegador, desenvolvido por Alex Junior (alequizao)](https://image.thum.io/get/width/700/https://publishdev.com.br/cobrinha/)](https://publishdev.com.br/cobrinha/)

## 🧱 Stack

| Camada | Tecnologia |
|---|---|
| Front | PHP 7.4 + HTML5 Canvas + JavaScript |
| Tempo real | Node.js + WebSocket (`servidor/servidor.js`) |
| Banco | MySQL |

## 📦 Manual de instalação

### Requisitos

| Componente | Versão |
|---|---|
| PHP | 7.4+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Node.js | 18+ (apenas para o modo online) |

### 1. Arquivos

```bash
git clone https://github.com/alequizao/cobrinha.git
cd cobrinha
```

### 2. Configurar

```bash
cp config.example.php config.php
cp servidor/.env.example servidor/.env
```

Preencha os dois com os dados do banco. **Nenhum dos dois vai para o Git.**

### 3. Criar as tabelas

Acesse `https://seu-dominio/instalar.php` no navegador. Depois de instalar,
**apague o `instalar.php`**.

### 4. Subir o servidor do modo online

```bash
cd servidor
npm install
node servidor.js
```

Em produção, use systemd ou PM2 para manter o processo de pé.

### Atualizações

Toda alteração de código sobe o `APP_VERSAO` em `config.php` e ganha uma linha no
[`CHANGELOG.md`](CHANGELOG.md).

---

## 👨‍💻 Desenvolvedor

Sistema **desenvolvido sob encomenda** por **Alex Junior (alequizao)** — Analista e
Desenvolvedor de Sistemas em Maceió, Alagoas, Brasil. Programador na **Publish Digital**.

- **E-mail:** alequizao.dev@gmail.com
- **WhatsApp:** [(82) 98871-7072](https://wa.me/5582988717072)
- **Instagram:** [@alequizao](https://instagram.com/alequizao)
- **GitHub:** [@alequizao](https://github.com/alequizao) · [perfil completo](https://github.com/alequizao/alequizao)
- **Site:** [alequizao.com](https://alequizao.com)

---

© Código proprietário, desenvolvido sob encomenda.
