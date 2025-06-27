<?php

use Src\services\QueueClientService;

require_once __DIR__ . '/vendor/autoload.php';

$client = new QueueClientService();

$client->sendMessage(
    ['message' => 'hello_world'],
    ['id' => '1234567890'],
    [
        'name' => 'test_topic',
        'type' => 'topic'
    ],
    ['name' => 'test_route']
);
