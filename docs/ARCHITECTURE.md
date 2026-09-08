# Arquitetura local

O Windows fornece os atalhos e valida os pré-requisitos. O código e os comandos de desenvolvimento ficam no filesystem Linux do WSL. O Docker Compose, por meio do Laravel Sail, coordena os serviços.

## Serviços

- laravel.test: servidor HTTP Laravel, publicado somente em 127.0.0.1:8080 no ambiente oficial;
- queue: worker do Laravel com tentativas, timeout e reciclagem por tempo;
- scheduler: processo permanente do schedule:work;
- mysql: MySQL 8.4.11, acessível apenas pela rede interna do Compose;
- mailpit: SMTP local e interface web em loopback.

Web, fila e agendador usam a mesma imagem PHP 8.5, mas executam em contêineres independentes.

## Persistência

- banco: volume Docker sigme_mysql_data;
- uploads privados: ~/sigme-data/uploads;
- logs operacionais: ~/sigme-data/logs-operacionais;
- diretório reservado a backups: ~/sigme-data/backups;
- chave reservada ao backup: ~/.config/sigme/backup.key.

Parar os serviços não remove esses dados.

## Segurança local

- MySQL não publica porta no host;
- portas HTTP, Vite e Mailpit usam 127.0.0.1;
- .env e a chave de backup não entram no Git;
- /up devolve apenas healthy ou unhealthy, sem detalhes internos;
- testes usam sigme_testing, separado do banco sigme.

