<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

// === Configurations ===
$queueName    = $argv[1] ?? die("morre praga\n");
$exchangeName = 'pos';
$routingKeys  = [$queueName, 'all']; // Example routing keys
$callback     = function (AMQPMessage $msg) {
    try {
        echo "Received message from: {$msg->get('app_id')}\n";
    } catch (\Exception) {
        echo "Received message from: unknown\n";
    }
    echo "Content Type: {$msg->get('content_type')}\n";
    echo "Message Type: {$msg->get('type')}\n";
    echo "Body: {$msg->getBody()}\n";
    echo "Headers: \n";
    print_r($msg->get('application_headers')->getNativeData());
    echo "\n\n";
    $msg->ack();
};

// === Connection ===
$connection = new AMQPStreamConnection(
    'jackal-01.rmq.cloudamqp.com',
    5672,
    'iybmnxuk',
    'x5BrPkK0bCpRQYz3MBopGuFleTABQQAI',
    'iybmnxuk'
);
$channel    = $connection->channel();

// === Declare Exchange ===
$channel->exchange_declare(
    $exchangeName,
    'topic',
    false,
    true,
    false
);

// === Declare Queue with same options ===
$channel->queue_declare(
    $queueName,
    false,
    true,
    false,
    false,
    false,
    new AMQPTable([
        'x-message-ttl'             => 86000000,
        'x-expires'                 => 86000000,
        'x-dead-letter-exchange'    => 'dead',
        'x-dead-letter-routing-key' => 'all',
        'x-max-priority'            => 10,
    ])
);

// === Bind Queue to Exchange for each routing key ===
foreach ($routingKeys as $routingKey) {
    $channel->queue_bind($queueName, $exchangeName, $routingKey);
}

// === Start Consuming ===
$channel->basic_consume(
    $queueName,
    '',
    false,
    false,
    false,
    false,
    $callback
);

// === Keep Listening ===
while ($channel->is_consuming()) {
    $channel->wait();
}

// === Cleanup (optional, usually unreachable in infinite loop) ===
// $channel->close();
// $connection->close();
