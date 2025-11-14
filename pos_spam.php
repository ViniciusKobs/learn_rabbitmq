<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

// === Configurations ===
global $exchangeName;

function sendToExchange(
    \PhpAmqpLib\Channel\AMQPChannel $channel,
    string $queueName,
    string $exchange,
    string $route,
    array $payload,
    string $correlationId,
    string $type = '',
    array $headers = [],
    ?string $messageId = null,
): void {
    $message = new AMQPMessage(
        json_encode($payload),
        [
            'content_type' => 'application/json',
            'type' => $type,
            'delivery_mode' => 2,  // persistent
            'priority' => 0,
            'application_headers' => new AMQPTable($headers),
            'app_id' => $queueName,
            'message_id' => $messageId ?? ($payload['id'] ?? ''),
            'correlation_id' => $correlationId,
        ]
    );

    $channel->exchange_declare(
        $exchange,
        'topic',
        false,
        true,
        false
    );

    $channel->basic_publish(
        $message,
        $exchange,
        $route
    );
}

// === Configurations ===
$exchangeName = 'pos';

// === Connection ===
$config = new AMQPConnectionConfig();
$config->setHost('44.210.249.31');
$config->setPort(5712);
$config->setUser('imply');
$config->setPassword('Ux36J5tZe4J5mSeJmM9HUl9y');
$config->setConnectionName('vkobs_fake');
$connection = AMQPConnectionFactory::create($config) ;
$channel    = $connection->channel();

$start = microtime(true); // End the timer

$json = file_get_contents('sliced.json');
$array = json_decode($json, true);

$count = count($array);
foreach ($array as $item) {
    echo $count-- . " remaining" . PHP_EOL;
    try {
        sendToExchange(
            $channel,
            $item,
            'server',
            'default',
            [
                "result" => 2,
                "error" => null,
                "id" => ''
            ],
            '9f90304e-738b-4e86-a575-18f656c1fe22',
            'ack'
        );
    } catch (Throwable) {
        echo "Error sending response\n";
    }
}

$end = microtime(true); // End the timer
$duration = $end - $start;

echo "finished in " . round($duration, 3) . " seconds" . PHP_EOL;

