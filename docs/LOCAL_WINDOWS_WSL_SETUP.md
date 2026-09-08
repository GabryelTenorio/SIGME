# Preparação local no Windows e WSL

## Pré-requisitos oficiais

1. Windows 11 com virtualização habilitada.
2. WSL 2 atualizado.
3. Distribuição Ubuntu 24.04 LTS.
4. Docker Desktop em execução, com backend WSL 2 e integração habilitada para Ubuntu 24.04.
5. Pelo menos 20 GB livres na unidade C:.
6. Portas 8080, 8025, 1025 e 5173 livres.

Use um usuário Linux comum. Guarde o projeto em:

    /home/<usuario>/projetos/sigme

Não trabalhe em /mnt/c ou /mnt/e.

## Preparação inicial

No Ubuntu:

    cd ~/projetos/sigme
    ./scripts/wsl/bootstrap.sh

O bootstrap:

- preserva um .env existente;
- gera somente os segredos ausentes;
- cria os diretórios persistentes;
- instala dependências pelos locks;
- constrói a imagem;
- inicia MySQL e Mailpit;
- executa migrations, build e o teste mínimo;
- abre disponibilidade somente depois de /up responder.

O script falha com mensagem clara se os pré-requisitos oficiais não forem atendidos. SIGME_ALLOW_UNSUPPORTED_HOST=1 existe apenas para desenvolvimento consciente e não certifica o host.

## Acesso pelo Windows

Abra a pasta pelo caminho WSL e execute os atalhos .cmd. O caminho de uma instalação normal se parece com:

    \\wsl.localhost\Ubuntu-24.04\home\<usuario>\projetos\sigme

