<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

// === Configurations ===
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

$connection = AMQPConnectionFactory::create($config) ;
$channel    = $connection->channel();

//$message = [
//    'tipo'    => 'type',
//    'headers' => [],
//    'metodo' => 'method',
//    'payload' => [],
//    'id' => 'id',
//];
//
//$destinatario = [
//    'topic' => 'exchange',
//    'routes' => ['route']
//];

$message = [
    'tipo'    => 'blocked_card',
    'headers' => [],
    'metodo' => 'blockCard',
    'payload' => [
        'idevento' => '9c6d2a62-401c-409e-8a52-7cb8239307f4',
        'documento' => '4465651',
        'codigo' => '8EFB57D8',
        'idmotivo' => '8'
    ],
    'id' => '1234567890',
];

$destinatario = [
    'topic' => 'server',
    'routes' => ['default']
];

sendMessage($message, $destinatario);

$channel->close();
$connection->close();

function createMessage(array $message): AMQPMessage {
    [   'tipo'    => $tipo,
        'headers' => $headers,
        'metodo' => $metodo,
        'payload' => $payload,
        'id' => $id,
    ] = $message;

    return new AMQPMessage(
        json_encode([
            'method' => $metodo,
            'params' => $payload,
            'id' => $id,
        ]),
        [
            'content_type'        => 'application/json',
            'type'                => $tipo,                   // tipo da mensagem customizavel
            'delivery_mode'       => 2,                       // persistencia: 1 = nao, 2 = sim
            'priority'            => 0,                       // prioridade da mensagem, 0 - menor > 10 - maior
            'application_headers' => new AMQPTable($headers),
            'app_id'              => 'server',                // identificador do produtor da mensagem, neste caso o servidor
            'message_id'          => $id,                     // identificador da mensagem (desabilitado por enquanto)
            'correlation_id'      => '',                      // identificador da mensagem para qual se trata a response (para mensagens do tipo response, desabilidato por enquanto)
        ]
    );
}

/**
 * @throws Exception
 */
// TODO: pro cliente que for bindar sua queue na exchange alternativa não precisar usar uma routing_key
//       com wildcard usar o tipo de exchange 'fanout' quando for declarado uma exchange alternativa
function declareExchange(string $exchange, bool $useAlternate = true): void {
    global $channel;
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
}

function sendMessage(
    array $mensagem,
    array $destinatario,
): void {
    global $channel;
    $topic = $destinatario['topic'];

    // TODO: declarar exchange alternativa depois que resolver como vou implementar ela

    declareExchange($topic);

    $message = createMessage($mensagem);

    foreach ($destinatario['routes'] as $route) {
        $channel->batch_basic_publish($message, $topic, $route);
    }

    $channel->publish_batch();
}
