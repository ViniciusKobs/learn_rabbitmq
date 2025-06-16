<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Check for routing key argument
if ($argc < 2) {
    $argv[1] = '';
}

$id = $argv[1];
$routingKey = $id;
$exchange = 'pos.notification';
$queueName = 'pos.queue.'.$id; // <- Named queue

$connection = new AMQPStreamConnection(
    'jackal-01.rmq.cloudamqp.com',      // host
    5672,                               // port
    'iybmnxuk',                         // user
    'x5BrPkK0bCpRQYz3MBopGuFleTABQQAI', // password
    'iybmnxuk'                          // vhost
);
$channel = $connection->channel();

$channel->exchange_declare(
    $exchange,
    'topic',
    false,
    false,
    true,
    false,
    false,
    [],
);

$channel->queue_declare(
    $queueName,
    false,
    false,
    false,
    true,
    false,
    [],
);

$channel->queue_bind(
    $queueName,
    $exchange,
    $routingKey
);

$channel->queue_bind(
    $queueName,
    $exchange,
    'all'
);

echo " [*] Waiting for messages on queue '{$queueName}' with routing key '{$routingKey}'. To exit press CTRL+C\n";

// Message handler
$callback = function (AMQPMessage $msg) {
    echo " [x] Received ({$msg->delivery_info['routing_key']}): {$msg->body}\n";
    $msg->ack();
};

// Start consuming from the named queue
$channel->basic_consume(
    $queueName,
    '',
    false,
    false,
    false,
    false,
    $callback,
    null,
    [],
);

// Enter wait loop
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
