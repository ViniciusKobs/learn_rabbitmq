<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

// === Configurations ===
global $posID;
global $queueName;
global $exchangeName;
global $routingKeys;

// === Connection ===
$config = new AMQPConnectionConfig();
$config->setHost('44.210.249.31');
$config->setPort(5712);
$config->setUser('imply');
$config->setPassword('Ux36J5tZe4J5mSeJmM9HUl9y');
$config->setConnectionName('vkobs_fake.' . $queueName);
$config->setVhost('homolog');
$config->setHeartbeat(15);

$connection = AMQPConnectionFactory::create($config);
$channel    = $connection->channel();

$posId = ($argv[2] ?? die("morre praga\n"));
$queueName = 'fake_pos.' . $posId;
$exchangeName = 'pos.' . ($argv[1] ?? die("morre praga\n"));
$routingKeys  = [$posId, 'all'];

$outputMessage = function(AMQPMessage $message): void {
    global $posId;

    // message header
    $messageId = safeGet($message, 'message_id') ?? 'not set';
    $routingKey = $message->getRoutingKey() ?? 'not set';
    $contentType = safeGet($message, 'content_type') ?? 'not set';
    $type = safeGet($message, 'type') ?? 'not set';
    $deliveryMode = safeGet($message, 'delivery_mode');
    $priority = safeGet($message, 'priority') ?? 'not set';
    $appId = safeGet($message, 'app_id') ?? 'not set';
//    $appHeaders = safeGet($message, 'application_headers') ?? [];

    // message body
    $payload = json_decode($message->getBody(), true) ?? [];
    $params = json_encode($payload['params'], JSON_PRETTY_PRINT);

    echo "
=== Message Headers === 
Message ID: {$messageId}
Routing Key: {$routingKey}
Content Type: {$contentType}
Message Type: {$type}
Delivery Mode: {$deliveryMode}
Priority: {$priority}
App ID: {$appId}

=== Message Payload ===  
Method: {$payload['method']}
Params: {$params}
ID: {$payload['id']}
    ";

    $response = [
        'tipo' => 'ack',
        'headers' => [],
        'metodo' => 1,
        'payload' => null,
        'id' => '',
        'correlation_id' => $messageId,
        'app_id' => $posId
    ];

    $destinatario = [
        'topic' => 'server',
        'routes' => ['default']
    ];

    sendMessage($response, $destinatario);

    $message->ack();
};

echo "Listening...\n";
bindConsumer($queueName, $outputMessage, [
    'topic' => $exchangeName,
    'routes' => $routingKeys
]);
listen();

function safeGet(AMQPMessage $message, string $key, $default = null) {
    if ($message->has($key)) {
        return $message->get($key);
    }
    return $default;
}

function declareQueue(string $queue): void {
    global $channel;
    $channel->queue_declare(
        $queue,
        false, // passiva
        true,  // duravel
        false, // exclusiva
        false, // auto-delete
        false, // nowait
        new AMQPTable([
            'x-message-ttl'             => 86_400_000, // mensagens expiram depois de 1 dia
            'x-expires'                 => 86_400_000, // fila expira depois de 1 dia
            'x-dead-letter-exchange'    => 'dead',
            'x-max-priority'            => 10,
        ])
    );
}

function declareExchange(string $exchange, bool $useAlternate = true): void {
    $isAlternate = $exchange === 'dead';
    $useAlternate = $useAlternate && !$isAlternate;

    $args = [
        'x-expires' => 86_400_000,
    ];

    if ($useAlternate) {
        $args['alternate-exchange'] = 'dead';
    }

    global $channel;
    $channel->exchange_declare(
        $exchange,
        $isAlternate ? 'fanout' : 'topic',
        false, // passiva
        true,  // duravel
        false, // auto-delete
        false, // interna
        false, // nowait
        new AMQPTable($args)
    );

    if ($useAlternate) {
        declareExchange('dead');
    }
}

function bindQueue(string $queue, string $exchange, string $routing_key): void {
    global $channel;
    $channel->queue_bind(
        $queue,
        $exchange,
        $routing_key,
        false,
        [],
    );
}

function basicConsume(string $queue, callable $callback): void {
    global $channel;
    $channel->basic_consume(
        $queue,
        '',
        false, // no_local
        false, // no_ack
        false, // exclusiva
        false, // nowait
        $callback,
        null,
        [],
    );
}

function bindConsumer(
    string   $queue,
    callable $callback,
    ?array   $destinatario = null,
    bool     $useAlternate = true,
): void {
    declareQueue($queue);

    if ($destinatario) {
        $topic = $destinatario['topic'];

        declareExchange($topic, $useAlternate);

        foreach ($destinatario['routes'] as $route) {
            bindQueue($queue, $topic, $route);
        }
    }

    basicConsume($queue, $callback);
}

function createMessage(array $message, bool $isResponse = false): AMQPMessage {
    [   'tipo'           => $tipo,
        'headers'        => $headers,
        'metodo'         => $metodo,
        'payload'        => $payload,
        'id'             => $id,
        'correlation_id' => $correlationId,
        'app_id'         => $appId
    ] = $message;

    $body = $isResponse
        ? json_encode([
            'method' => $metodo,
            'params' => $payload,
            'id'     => $id,
        ])
        : json_encode([
            'result' => $metodo,
            'error'  => $payload,
            'id'     => $id,
        ]);

    return new AMQPMessage(
        $body,
        [
            'content_type'        => 'application/json',
            'type'                => $tipo,                   // tipo da mensagem customizavel
            'delivery_mode'       => 2,                       // persistencia: 1 = nao, 2 = sim
            'priority'            => 0,                       // prioridade da mensagem, 0 - menor > 10 - maior
            'application_headers' => new AMQPTable($headers),
            'app_id'              => $appId,                  // identificador do produtor da mensagem, neste caso o servidor
            'message_id'          => $id,                     // identificador da mensagem (desabilitado por enquanto)
            'correlation_id'      => $correlationId,          // identificador da mensagem para qual se trata a response
        ]
    );
}

function sendMessage(
    array $mensagem,
    array $destinatario,
    bool  $isResponse = false
): void {
    global $channel;
    $topic = $destinatario['topic'];
    $message = createMessage($mensagem, $isResponse);

    declareExchange($topic, false);

    foreach ($destinatario['routes'] as $route) {
        $channel->batch_basic_publish($message, $topic, $route);
    }

    $channel->publish_batch();
}

function listen(): void
{
    global $channel;
    while ($channel->is_consuming()) {
        $channel->wait();
    }
}
