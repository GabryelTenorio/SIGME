# Iniciar, parar e consultar

## Windows

- Iniciar SIGME.cmd valida o host, inicia os serviços e abre o navegador somente depois da saúde aprovada.
- Parar SIGME.cmd interrompe serviços sem apagar volumes.
- Status do SIGME.cmd mostra serviços e consulta /up.

## WSL

    ./scripts/wsl/start.sh
    ./scripts/wsl/status.sh
    ./scripts/wsl/stop.sh

Endereços oficiais:

- aplicação: http://localhost:8080;
- Mailpit: http://localhost:8025.

Se a porta 8080 estiver ocupada, encerre apenas o processo conflitante que você reconhece. Não altere ou pare outro projeto automaticamente.

O comando docker compose down -v não faz parte de nenhum fluxo comum porque remove o volume do banco.

