<?php

require_once __DIR__ . '/vendor/autoload.php';

$pref = '\\Src\\MessageHandlers\\';
$type = $argv[1];
$method = $argv[2];
$param = $argv[3] ?? '';

$class = $pref . $type;

([$class, $method])($param);

//\Src\MessageHandlers\Ack::handle('test');