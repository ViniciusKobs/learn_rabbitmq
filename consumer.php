<?php

require_once __DIR__ . '/vendor/autoload.php';

use Src\services\QueueClientService;

function callback($msg): void
{
    echo "Received: " . $msg->body . "\n";
}

$service = new QueueClientService();

$service->bindConsumer(
    'callback',
    ['name' => 'test_queue_afdkj']
);

$service->listen();
