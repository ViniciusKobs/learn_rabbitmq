link repositorio:<br>
https://github.com/ViniciusKobs/learn_rabbitmq

para instalar a biblioteca do amqp:<br>
`composer install && composer dump-autoload`

sistema possui 3 partes:
- um server RabbitMQ rodando na nuvem <br>
- um produtor de mensagens<br>
- e um consumidor de mensagens

para registrar um consumidor:<br>
`php consumertest.php idconsumidor`

para produzir uma mensagem:<br>
`php producertest.php mensagem idconsumidor`

se os ids forem iguais o consumidor deve receber a mensagem enviada pelo produtor
