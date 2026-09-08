# ADR-001: plataforma do beta local

Status: aceita pelo Prompt Mestre; implementação inicial em 27/08/2026.

## Decisão

Usar Windows 11 como camada de interação, WSL 2 com Ubuntu 24.04 como ambiente Linux, Docker Desktop como mecanismo de contêineres e Laravel Sail como interface do projeto.

O código fica no filesystem Linux. Web, fila, scheduler, MySQL e Mailpit ficam em serviços separados. Dados mutáveis relevantes ficam fora do repositório.

## Consequências

- desenvolvimento mais próximo de Linux sem instalar PHP ou MySQL no Windows;
- locks e Compose definem a base reproduzível;
- Docker Desktop e integração WSL tornam-se pré-requisitos;
- a porta 8080 precisa estar livre;
- backup e restauração continuam sendo requisitos próprios, não efeitos automáticos do Docker.

O host usado na implementação inicial é provisório e não satisfaz esta decisão por completo.
