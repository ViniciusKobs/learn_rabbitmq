<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

// === Configurations ===
global $posID;
global $queueName;
global $exchangeName;
global $routingKeys;

function sendToExchange(
    \PhpAmqpLib\Channel\AMQPChannel $channel,
    string $exchange,
    string $route,
    array $payload,
    string $correlationId,
    string $type = '',
    array $headers = [],
    ?string $messageId = null,
): void {
    global $posID;
    $message = new AMQPMessage(
        json_encode($payload),
        [
            'content_type' => 'application/json',
            'type' => $type,
            'delivery_mode' => 2,  // persistent
            'priority' => 0,
            'application_headers' => new AMQPTable($headers),
            'app_id' => $posID,
            'message_id' => $messageId ?? ($payload['id'] ?? ''),
            'correlation_id' => $correlationId,
        ]
    );

    $channel->exchange_declare(
        $exchange,
        'topic',
        false, // passiva
        true,  // duravel
        false, // auto-delete
        false, // interna
        false, // nowait
        new AMQPTable([
            'x-expires' => 86_400_000,
        ])
    );

    $channel->basic_publish(
        $message,
        $exchange,
        $route
    );
}

function listen(\PhpAmqpLib\Channel\AMQPChannel $channel, AMQPMessage $msg) {
    try {
        $messageId = $msg->get('message_id') ?? 'not set';
//    $correlationId = $msg->get('correlation_id') ?? 'not set';

        echo "=== Message Headers ===\n";
        // Routing Key
        echo "Routing Key: " . ($msg->getRoutingKey() ?? 'not set') . "\n";
        // Content Type
        echo "Content Type: " . ($msg->get('content_type') ?? 'not set') . "\n";
        // Message Type
        echo "Type: " . ($msg->get('type') ?? 'not set') . "\n";
        // Delivery Mode
        $deliveryMode = $msg->get('delivery_mode');
        echo "Delivery Mode: " . ($deliveryMode ? ($deliveryMode == 1 ? "1 (non-persistent)" : "2 (persistent)") : 'not set') . "\n";
        // Priority
        echo "Priority: " . ($msg->get('priority') ?? 'not set') . "\n";
        // App ID
        echo "App ID: " . ($msg->get('app_id') ?? 'not set') . "\n";
        // Message ID
        echo "Message ID: " . $messageId . "\n";
        // Correlation ID
        // echo "Correlation ID: " . $correlationId . "\n";
        // Custom Application Headers
        echo "Application Headers:\n";
    } catch (\Throwable) {
        echo "Error reading message headers\n";
    }

    try {
        $appHeaders = $msg->get('application_headers');
        if ($appHeaders instanceof AMQPTable) {
            print_r($appHeaders->getNativeData());
        } else {
            echo "No custom headers\n";
        }
    } catch (\Throwable) {
        echo "No custom headers\n";
    }

    echo "\n=== Message Payload ===\n";
    try {
        $payload = json_decode($msg->getBody(), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "Method: " . ($payload['method'] ?? 'not set') . "\n";
            echo "Params: ";
            if (isset($payload['params'])) {
                print_r($payload['params']);
            } else {
                echo "not set\n";
            }
            echo "ID: " . ($payload['id'] ?? 'not set') . "\n";
        } else {
            echo "Raw Body (not JSON):\n{$msg->getBody()}\n";
        }
    } catch (\Throwable) {
        echo "Error parsing message body\n";
    }
    echo "\n===========================\n\n";

    try {
        sendToExchange(
            $channel,
            'server',
            'default',
            [
                "result" => 1,
                "error" => null,
                "id" => ''
            ],
            $messageId,
            'ack'
        );
    } catch (Throwable) {
        echo "Error sending response\n";
    }

    // Acknowledge the message
    $msg->ack();
};


// === Configurations ===
$posID = ($argv[1] ?? die("morre praga\n"));
$queueName    = 'fake_pos.' . $posID;
$exchangeName = 'pos.' . ($argv[2] ?? die("morre praga\n"));
$routingKeys  = [$posID, 'all']; // Example routing keys

// === Connection ===
$config = new AMQPConnectionConfig();
$config->setHost('44.210.249.31');
$config->setPort(5712);
$config->setUser('imply');
$config->setPassword('Ux36J5tZe4J5mSeJmM9HUl9y');
$config->setConnectionName('vkobs_fake.' . $queueName);
$config->setVhost('batata');
$config->setHeartbeat(15);
$connection = AMQPConnectionFactory::create($config) ;
$channel    = $connection->channel();

// === Declare Exchange ===
$channel->exchange_declare(
    $exchangeName,
    'topic',
    false, // passiva
    true,  // duravel
    false, // auto-delete
    false, // interna
    false, // nowait
//    new AMQPTable([
//        'x-expires' => 86_400_000,
//        'alternate-exchange' => 'dead'
//    ])
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
//        'x-message-ttl'             => 86400000,
        'x-message-ttl'             => 10_000,
        'x-expires'                 => 86400000,
//        'x-dead-letter-exchange'    => 'dead',
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
    fn(AMQPMessage $msg) => listen($channel, $msg)
);

// === Keep Listening ===
while ($channel->is_consuming()) {
    $channel->wait();
}

// === Cleanup (optional, usually unreachable in infinite loop) ===
// $channel->close();
// $connection->close();
