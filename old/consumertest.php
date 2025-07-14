<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$id = $argv[1];
$exchange = 'pos';
$queueName = 'pos.'.$id;

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
    false, // passiva     - quando falso verifica se a exchange existe, se não existe cria ela
           //               quando verdadeiro verifica se a exchange existe, se não existe lança uma exception

    false, // durável     - quando falso cria uma exchange volátil (é mantida na ram, melhor performance, mas se o broker desligar é excluída),
           //               quando verdadeiro cria uma exchange persistente (é salva no disco, menos performance, mas sobrevive reboots do broker)

    true,  // auto delete - quando verdadeiro a exchange é excluída após a última binding ser desvinculada
           //               quando falso a exchange é mantida mesmo sem nenhuma binding vinculada

    false, // interna     - quando false qualquer cliente pode publicar para essa exchange
           //               quando verdadeiro a exchange é interna e somente o servidor pode usar para roteamento

    false, // nowait      - quando falso o cliente espera por uma confirmação da declaração, se ocorreu alguma falha lança um erro
           //               quando verdadeiro o cliente não espera por confirmação e não lança erro em caso de falha

    [      // argumentos opcionais

           // alternate-exchange - o nome de uma exchange alternativa para mensagens que não são roteáveis (nenhuma queue bindada)
           // x-expires - o tempo de inatividade para a exchange expirar e ser excluída
    ],
);

$channel->queue_declare(
    $queueName,
    false, // passiva     - quando falso verifica se a fila existe, se não existe cria ela
           //               quando verdadeiro verifica se a fila existe, se não existe lança uma exception

    false, // durável     - quando falso cria uma fila volátil (é mantida na ram, melhor performance, mas se o broker desligar é excluída),
           //               quando verdadeiro cria uma fila persistente (é salva no disco, menos performance, mas sobrevive reboots do broker)

    false, // exclusiva   - quando falso a fila pode ser acessada por qualquer cliente
           //               quando verdadeiro a fila só pode ser acessada pelo cliente que criou ela, e quando o cliente desconecta a fila é apagada

    true,  // auto-delete - quando verdadeiro a fila é excluída após o último cliente desconectar
           //               quando falso a fila é mantida mesmo sem nenhum cliente conectado

    false, // nowait      - quando falso o cliente espera por uma confirmação da declaração, se ocorreu alguma falha lança um erro
           //               quando verdadeiro o cliente não espera por confirmação e não lança erro em caso de falha

    [      // argumentos opcionais

           // x-message-ttl	            - tempo de vida de mensagens na fila, ao atingir esse tempo a mensagem é apagada
           // x-expires                 - a fila expira depois de um tempo de inatividade
           // x-max-length	            - número limite de mensagens na fila, mensagens mais antigas são apagadas primeiro.
           // x-max-length-bytes        - tamanho limite de mensagens na fila em bytes, mensagens mais antigas são apagadas primeiro
           // x-overflow                - define um comportamento quando é excedido número ou tamanho limite de mensagens:
               // • drop-head (default) (mensagens antigas são apagadas para criar espaço para mensagem nova)
               // • reject-publish      (mensagem nova é rejeitada)
               // • reject-publish-dlx  (mensagem nova é rejeitada e redirecionada para exchange dead letter)
           // x-dead-letter-exchange    - exchange para quando a mensagem é rejeitada pelo cliente ou fila (ttl e max-length)
           // x-dead-letter-routing-key	- routing key para mensagens enviadas para a exchange dead letter
           // x-max-priority            - habilita filas prioritárias e define o valor de prioridade
           // x-queue-mode              - método de armazenamento da fila:
               //   • default (mensagens vão para disco somente quando necessário)
               //   • lazy    (mensagens vão sempre para disco)
           // x-single-active-consumer  - quando verdadeiro somente um consumidor vai receber mensagem da fila por vez, os outros vão se manter idle
    ],
);

$channel->queue_bind(
    $queueName,
    $exchange,
    $id,
    false, // nowait - quando falso o cliente espera por uma confirmação da declaração, se ocorreu alguma falha lança um erro
           //          quando verdadeiro o cliente não espera por confirmação e não lança erro em caso de falha

    [] // argumentos opcionais (queue_bind não possuí nenhum por padrão, somente com plugins)
);

$channel->queue_bind(
    $queueName,
    $exchange,
    'all'
);

echo "Waiting for messages on queue '{$queueName}'\n";

$callback = function (AMQPMessage $msg) {
    $headers = $msg->get('application_headers');
    if ($headers && method_exists($headers, 'getNativeData')) {
        $nativeHeaders = $headers->getNativeData();
        echo " [x] Received headers:\n";
        print_r($nativeHeaders);
    } else {
        echo " [x] No headers found.\n";
    }

    echo "Received: {$msg->body}\n";

    $msg->ack();
};

$channel->basic_consume(
    $queueName,

    '',        // tag de consumidor - identificador único do consumidor

    false,     // no_local          - quando falso o servidor permite entregar mensagens enviadas da mesma conexão
               //                   - quando verdadeiro o servidor não permite entregar mensagens enviadas da mesma conexão

    false,     // no_ack            - quando falso o consumidor precisa dar a acknowledge manualmente
               //                   - quando verdadeiro o servidor assume acknowledge no momento que a mensagem é enviada

    false,     // exclusivo         - quando falso múltiplos consumidores podem acessar esta fila simultaneamente
               //                   - quando verdadeiro outros consumidores não podem acessar esta fila enquanto este consumidor está ativo

    false,     // nowait            - quando falso o cliente espera por uma confirmação da declaração, se ocorreu alguma falha lança um erro
               //                     quando verdadeiro o cliente não espera por confirmação e não lança erro em caso de falha

    $callback, //

    null,      // ticket (não é utilizado pelo rabbitmq)

    []         // argumentos opcionais (basic_consume não possuí nenhum por padrão, somente com plugins)
);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
