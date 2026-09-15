<?php
/*
 * Cobrinha · Desenvolvido por Alequizao <alequizao.dev@gmail.com>
 * https://github.com/alequizao · © 2026 Alequizao. Todos os direitos reservados.
 */
require __DIR__ . '/config.php';
sessao();
$_SESSION = [];
session_destroy();
header('Location: index.php');
