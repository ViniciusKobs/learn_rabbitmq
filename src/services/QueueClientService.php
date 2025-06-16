<?php

namespace Src\services;

use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;
use Src\interfaces\QueueClientInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class QueueClientService implements QueueClientInterface
{
    private AMQPStreamConnection $connection;
    private AMQPChannel $channel;

    /**
     * @throws Exception
     */
    function __construct() {
        // SUBSTITUIR PARA ENV
        $this->connection = new AMQPStreamConnection(
            'localhost', // env('RABBITMQ_HOST', 'localhost'),
            5672,        // env('RABBITMQ_PORT', 5672       ),
            'server',    // env('RABBITMQ_USER', 'guest'    ),
            'imply1234', // env('RABBITMQ_PASS', 'guest'    ),
            '/'          // env('RABBITMQ_VHOST', '/'       ),
        );
        $this->channel = $this->connection->channel();
    }

    /**
     * @throws Exception
     */
    function __destruct() {
        $this->channel->close();
        $this->connection->close();
    }

    /**
     * @throws Exception
     */
    public function sendMessage(
        array $payload,
        array $headers = [],
        array $exchangeConfig = [],
        array $queueConfig = [],
    ): void {
        if (!empty($exchangeConfig)) {
            $this->declareExchange($exchangeConfig);
        }

        if (!empty($queueConfig)) {
            $this->declareQueue($queueConfig);
        }

        $message = $this->createMessage($payload, $headers);

        $this->channel->basic_publish(
            $message,
            $exchangeConfig['name'] ?? '',
            $queueConfig['name'] ?? ''
        );
    }

    /**
     * @throws Exception
     */
    public function sendMessageInBulk(
        array $payloads,
        array $headers = [],
        array $exchangeConfig = [],
        array $queueConfig = [],
    ): void {
        if (!empty($exchangeConfig)) {
            $this->declareExchange($exchangeConfig);
        }

        if (!empty($queueConfig)) {
            $this->declareQueue($queueConfig);
        }

        for ($i = 0; $i < max(count($payloads), count($headers)); $i++) {
            $payload = $payloads[$i % count($payloads)];
            $header  = $headers[$i % count($headers)];

            $message = $this->createMessage($payload, $header);

            $this->channel->batch_basic_publish(
                $message,
                $exchangeConfig['name'] ?? '',
                $queueConfig['name'] ?? ''
            );
        }

        $this->channel->publish_batch();
    }

    /**
     * @throws Exception
     */
    public function bindConsumer(
        callable $callback,
        array $queueConfig,
        array $consumerConfig = [],
        array $exchangeConfig = [],
        array $bindQueueConfig = [],
    ): void {
        $bindsQueue = !empty($exchangeConfig) && !empty($bindQueueConfig);

        if ($bindsQueue) {
            $this->declareExchange($exchangeConfig);
        }

        $this->declareQueue($queueConfig);

        if ($bindsQueue) {
            $this->bindQueue($bindQueueConfig);
        }

        $this->basicConsume(
            $queueConfig['name'],
            $callback,
            $consumerConfig
        );
    }

    public function listen(): void
    {
        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    private function createMessage(array $payload, array $headers = []): AMQPMessage {
        return new AMQPMessage(
            json_encode($payload),
            [
                'content_type' => 'application/json',
                'application_headers' => new AMQPTable($headers)
            ]
        );
    }

    /**
     * @throws Exception
     */
    private function declareQueue(array $queueConfig): void {
        $this->channel->queue_declare(
            $queueConfig['name'], // queue
            false,                // passive
            false,                // durable
            false,                // exclusive
            true,                 // auto_delete
            false,                // nowait
            [],                   // arguments
        );
    }

    /**
     * @throws Exception
     */
    private function declareExchange(array $exchangeConfig): void {
        $this->channel->exchange_declare(
            $exchangeConfig['name'], // exchange
            $exchangeConfig['type'], // exchange
            false,                   // passive
            false,                   // durable
            true,                    // auto_delete
            false,                   // internal
            false,                   // nowait
            [],                      // arguments
        );
    }

    private function bindQueue(array $bindQueueConfig): void {
        $this->channel->queue_bind(
            $bindQueueConfig['name'], // queue
            '',                       // exchange
            '',                       // routing_key
            false,                    // nowait
            [],                       // arguments
        );
    }

    private function basicConsume(string $queueName, callable $callback, array $consumeConfig): void {
        $this->channel->basic_consume(
            $queueName, // queue
            '',         // consumer_tag
            false,      // no_local
            false,      // no_ack
            false,      // exclusive
            false,      // nowait
            $callback,  // callback
            null,       // ticket (RabbitMQ não suporta tickets)
            [],         // arguments
        );
    }
}