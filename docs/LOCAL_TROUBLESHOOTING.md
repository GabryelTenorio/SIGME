# Solução de problemas

## Porta 8080 ocupada

No PowerShell:

    Get-NetTCPConnection -LocalPort 8080 -State Listen
    Get-Process -Id <PID>

No WSL:

    docker ps --format "table {{.Names}}\t{{.Ports}}"

Identifique o dono antes de agir. Neste computador, a porta pertence ao projeto de impressoras e não foi encerrada pelo SIGME.

## Ubuntu incorreto

Os scripts oficiais exigem 24.04. Instale Ubuntu-24.04 e mantenha o projeto no diretório home de um usuário comum. A opção de host não suportado serve apenas para diagnóstico consciente.

## Docker não responde

Inicie o Docker Desktop, aguarde o estado operacional e confira a integração da distribuição em Settings > Resources > WSL Integration.

O Docker Engine instalado diretamente nesta distribuição de teste é encerrado quando o WSL fica sem sessão ativa. Isso reinicia os contêineres na próxima abertura e não substitui o Docker Desktop do ambiente oficial.

## Permissão negada

Confirme que o projeto pertence ao usuário Linux atual e não está em /mnt. Não resolva com chmod 777.

## /up retorna erro

    docker compose ps
    docker compose logs --tail=100 laravel.test mysql

O endpoint depende de conexão com o banco e escrita no armazenamento privado.

## Fila após reinício do banco

    docker compose restart queue
    docker compose logs --tail=100 queue

## Proteção de segredos

Evite publicar a saída completa de docker compose config, pois variáveis interpoladas podem conter credenciais. Use verificações direcionadas que reportem somente presença e estado.
