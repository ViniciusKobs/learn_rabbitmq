<?php

namespace Src\interfaces;

interface QueueClientInterface{
    public function sendMessage(
        array $payload,
        array $headers = [],
        array $exchangeConfig = [],
        array $queueConfig = [],
    );
    public function sendMessageInBulk(
        array $payloads,
        array $headers = [],
        array $exchangeConfig = [],
        array $queueConfig = [],
    );
    public function bindConsumer(
        callable $callback,
        array $queueConfig,
        array $consumerConfig = [],
        array $exchangeConfig = [],
        array $bindQueueConfig = [],
    );
    public function listen(): void;
}