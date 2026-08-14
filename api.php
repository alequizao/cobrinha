<?php
// API: fim de partida
require __DIR__ . '/config.php';
$u = usuario();
if (!$u) json(['erro' => 'Faça login novamente.'], 401);

$body = json_decode(file_get_contents('php://input'), true) ?: [];
if (!checaToken($body['csrf'] ?? null)) json(['erro' => 'Sessão inválida.'], 403);

$acao = (string)($body['acao'] ?? '');

if ($acao === 'fim') {
    $modos   = modos();
    $modo    = (string)($body['modo'] ?? 'classico');
    if (!isset($modos[$modo])) json(['erro' => 'Modo inválido.'], 400);

    $tamanho = max(3, min(20000, (int)($body['tamanho'] ?? 3)));
    $duracao = max(0, min(86400, (int)($body['duracao'] ?? 0)));

    // Pontuação é recalculada aqui — o cliente não decide o placar.
    if ($modo === 'arena') {
        $kills   = max(0, min(500, (int)($body['kills'] ?? 0)));
        $cresceu = max(0, $tamanho - 10);            // começa com massa 10
        $pontos  = (int)round($cresceu * 5 + $kills * 100);
        // Plausibilidade: crescer 1 de massa leva ~0,08s; cada eliminação, ~3s.
        if ($duracao > 0 && ($cresceu > $duracao * 12 || $kills > $duracao / 3 + 1)) {
            json(['erro' => 'Partida inválida.'], 400);
        }
    } else {
        $comidas = max(0, $tamanho - 3);
        $pontos  = (int)round($comidas * 10 * $modos[$modo]['mult']);
        // Plausibilidade simples: cada comida leva no mínimo ~0,2s de jogo.
        if ($duracao > 0 && $comidas > $duracao * 5) {
            json(['erro' => 'Partida inválida.'], 400);
        }
    }
    $moedas = (int)floor($pontos / 5);

    db()->beginTransaction();
    db()->prepare('INSERT INTO partidas (usuario_id, modo, pontos, tamanho, duracao, moedas) VALUES (?,?,?,?,?,?)')
        ->execute([$u['id'], $modo, $pontos, $tamanho, $duracao, $moedas]);

    if ($moedas > 0) {
        db()->prepare('UPDATE usuarios SET moedas = moedas + ? WHERE id = ?')->execute([$moedas, $u['id']]);
    }

    $st = db()->prepare('SELECT pontos FROM recordes WHERE usuario_id = ? AND modo = ?');
    $st->execute([$u['id'], $modo]);
    $atual  = (int)($st->fetchColumn() ?: 0);
    $novo   = $pontos > $atual;
    if ($novo) {
        db()->prepare('INSERT INTO recordes (usuario_id, modo, pontos) VALUES (?,?,?)
                       ON DUPLICATE KEY UPDATE pontos = VALUES(pontos)')
            ->execute([$u['id'], $modo, $pontos]);
    }
    db()->commit();

    $st = db()->prepare('SELECT COUNT(*)+1 FROM recordes WHERE modo = ? AND pontos > ?');
    $st->execute([$modo, max($pontos, $atual)]);

    json([
        'pontos'  => $pontos,
        'moedas'  => $moedas,
        'recorde' => max($pontos, $atual),
        'novo'    => $novo,
        'posicao' => (int)$st->fetchColumn(),
        'saldo'   => (int)$u['moedas'] + $moedas,
    ]);
}

json(['erro' => 'Ação desconhecida.'], 400);
