para instalar a biblioteca do amqp:
`composer install && composer dump-autoload`

sistema possui 3 partes:
um server RabbitMQ rodando na nuvem
um produtor de mensagens
e um consumidor de mensagens

para registrar um consumidor:
`php consumertest.php idconsumidor`

para produzir uma mensagem:
`php producertest.php mensagem idconsumidor`

se os ids forem iguais o consumidor deve receber a mensagem enviada pelo produtor
