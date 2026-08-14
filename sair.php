<?php
require __DIR__ . '/config.php';
sessao();
$_SESSION = [];
session_destroy();
header('Location: index.php');
