<?php

use Src\services\QueueClientService;

require_once __DIR__ . '/vendor/autoload.php';

$client = new QueueClientService();

$msg = $argv[1];
$key = $argv[2];

$client->sendMessage(
    ['message' => $msg],
    ['id' => '1234567890'],
    [
        'name' => 'pos',
        'type' => 'topic'
    ],
    ['name' => $key]
);
