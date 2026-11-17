<?php
require __DIR__ . '/inc/bootstrap.php';
$lang = $_SESSION['lang'] ?? null;
$_SESSION = [];
session_regenerate_id(true);
if ($lang) $_SESSION['lang'] = $lang;
redirect('index.php');
