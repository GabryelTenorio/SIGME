# Regras permanentes do SIGME

- O beta oficial executa localmente no Windows 11 com WSL 2, Ubuntu 24.04, Docker Desktop e Laravel Sail.
- O código de desenvolvimento deve permanecer no filesystem Linux do WSL, nunca em `/mnt/c` ou `/mnt/e`.
- Use PHP 8.5, Laravel 13, Node 24 e MySQL 8.4 conforme os locks e o `compose.yaml`.
- Preserve `.env`, chaves, bancos, uploads e backups. Nunca execute `docker compose down -v` em fluxos comuns.
- Não publique MySQL. Vincule interfaces locais somente a `127.0.0.1`.
- Testes automatizados usam exclusivamente `sigme_testing`, nunca o banco `sigme`.
- Requisitos documentados não são evidência de implementação; declare apenas verificações realmente executadas.
- Funcionalidades de negócio exigem requisitos aprovados. Não transforme exemplos do Prompt Mestre em regras inventadas.
