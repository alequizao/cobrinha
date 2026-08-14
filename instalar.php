<?php
// Cria as tabelas do banco. Rode uma vez e depois apague/renomeie.
require __DIR__ . '/config.php';

$sql = [
"CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(32) NOT NULL UNIQUE,
  nome VARCHAR(60) NOT NULL,
  senha VARCHAR(255) NOT NULL,
  moedas INT NOT NULL DEFAULT 0,
  skin VARCHAR(20) NOT NULL DEFAULT 'verde',
  admin TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acesso DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS partidas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  modo VARCHAR(20) NOT NULL,
  pontos INT NOT NULL,
  tamanho INT NOT NULL,
  duracao INT NOT NULL,
  moedas INT NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (usuario_id), INDEX (modo, pontos),
  CONSTRAINT fk_partidas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS recordes (
  usuario_id INT NOT NULL,
  modo VARCHAR(20) NOT NULL,
  pontos INT NOT NULL,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, modo),
  CONSTRAINT fk_recordes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS skins_usuario (
  usuario_id INT NOT NULL,
  skin VARCHAR(20) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, skin),
  CONSTRAINT fk_skins_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

header('Content-Type: text/plain; charset=utf-8');
foreach ($sql as $q) {
    db()->exec($q);
    echo "OK: " . substr(trim(explode("\n", $q)[0]), 0, 60) . "\n";
}
// Coluna admin em bancos criados antes desta versão
try { db()->exec("ALTER TABLE usuarios ADD COLUMN admin TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $e) {}

// Usuário administrador padrão
$st = db()->prepare('SELECT id FROM usuarios WHERE usuario = ?');
$st->execute(['alequizao']);
if ($id = $st->fetchColumn()) {
    db()->prepare('UPDATE usuarios SET admin = 1 WHERE id = ?')->execute([$id]);
    echo "OK: admin 'alequizao' já existia — permissão garantida.\n";
} else {
    db()->prepare('INSERT INTO usuarios (usuario, nome, senha, moedas, admin) VALUES (?,?,?,?,1)')
        ->execute(['alequizao', 'Alequizão', password_hash('alequizao', PASSWORD_DEFAULT), 9999]);
    $id = (int)db()->lastInsertId();
    echo "OK: admin criado — usuario 'alequizao' / senha 'alequizao'\n";
}
$ins = db()->prepare('INSERT IGNORE INTO skins_usuario (usuario_id, skin) VALUES (?,?)');
foreach (array_keys(skins()) as $s) $ins->execute([$id, $s]);

echo "\nBanco pronto. Acesse index.php e apague este arquivo.\n";

// --- v1.5: acessórios de cabeça ---
try { db()->exec("ALTER TABLE usuarios ADD COLUMN acessorio VARCHAR(20) NOT NULL DEFAULT ''"); } catch (PDOException $e) {}
db()->exec("CREATE TABLE IF NOT EXISTS acessorios_usuario (
  usuario_id INT NOT NULL,
  acessorio VARCHAR(20) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, acessorio),
  CONSTRAINT fk_acess_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// todo mundo já nasce com o "nenhum"
db()->exec("INSERT IGNORE INTO acessorios_usuario (usuario_id, acessorio) SELECT id, '' FROM usuarios");
$adm = db()->query("SELECT id FROM usuarios WHERE admin = 1")->fetchAll(PDO::FETCH_COLUMN);
$ins = db()->prepare('INSERT IGNORE INTO acessorios_usuario (usuario_id, acessorio) VALUES (?,?)');
foreach ($adm as $aid) foreach (array_keys(acessorios()) as $a) $ins->execute([$aid, $a]);
echo "OK: acessórios instalados.\n";
