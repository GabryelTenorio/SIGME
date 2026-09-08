# SIGME

Sistema de Gestão de Manutenção Escolar para registrar ocorrências, realizar triagem, emitir e executar Ordens de Serviço, controlar aprovações, custos, materiais, evidências privadas, notificações e reaberturas.

O projeto é uma aplicação Laravel executada localmente por Docker Compose/Laravel Sail. Aplicação, fila, agendador, MySQL e Mailpit funcionam em serviços separados.

## Estado desta versão

- Laravel 13.29.0 e PHP 8.5.9;
- Node.js 24, npm e Vite 8;
- MySQL 8.4.11;
- interface Blade responsiva em português;
- isolamento por organização e escola;
- perfis e permissões de plataforma, rede, direção, técnico, solicitante e representação estudantil;
- fluxo de ocorrência, triagem, OS interna ou externa, aprovação comum ou emergencial, execução e reabertura;
- anexos privados em imagem ou PDF;
- notificações internas e preferência individual de envio por e-mail;
- 114 testes automatizados, com 782 asserções, aprovados em 08/09/2026;
- build de produção validado em 08/09/2026.

## Pré-requisitos no Windows

1. Windows 11 com virtualização habilitada.
2. WSL 2 atualizado.
3. Ubuntu 24.04 LTS instalado no WSL.
4. Docker Desktop aberto, usando o backend WSL 2 e com integração para o Ubuntu habilitada.
5. Git instalado dentro do Ubuntu.
6. Pelo menos 20 GB livres.
7. Portas locais 8080, 8025, 1025 e 5173 disponíveis.

O código deve ficar no filesystem Linux, por exemplo `/home/seu-usuario/projetos/sigme`. Não execute o projeto em `/mnt/c` ou `/mnt/e`, pois o desempenho e as permissões do Docker ficam menos previsíveis.

## Instalação rápida

Abra o terminal do Ubuntu no WSL e execute:

```bash
mkdir -p ~/projetos
cd ~/projetos
git clone https://github.com/GabryelTenorio/SIGME.git sigme
cd sigme
chmod +x scripts/wsl/*.sh
./scripts/wsl/bootstrap.sh
```

O `bootstrap.sh`:

- cria o `.env` local sem versionar seus segredos;
- gera chaves e senhas ausentes;
- cria os diretórios persistentes em `~/sigme-data`;
- instala as dependências dos arquivos de lock;
- constrói as imagens e os assets;
- inicia MySQL e Mailpit;
- aplica as migrations;
- testa o endpoint de saúde;
- inicia aplicação, fila e agendador.

Ao terminar, acesse:

- SIGME: http://localhost:8080
- Mailpit, para visualizar e-mails locais: http://localhost:8025

## Restaurar o banco incluído neste repositório

O arquivo `database/snapshots/sigme.sql.gz` contém uma fotografia restaurável do banco local de 08/09/2026, após as migrations da V1 e antes da massa temporária usada na auditoria final. O snapshot possui 10 usuários, 4 escolas e 1 ocorrência original. Não havia arquivos de upload nesse estado.

Depois de executar o bootstrap, restaure essa fotografia com:

```bash
cd ~/projetos/sigme
./scripts/wsl/restore-repository-snapshot.sh
```

O script mostra o banco de destino, pede a confirmação exata `RESTAURAR`, verifica o SHA-256, pausa somente os serviços que usam o banco, recria o banco `sigme`, importa o snapshot e inicia novamente os serviços.

Essa operação substitui os dados existentes. Não a execute sobre um banco que contenha informações que você queira preservar.

## Criar seu administrador

Para gerar uma conta administrativa própria e uma senha segura exibida uma única vez:

```bash
cd ~/projetos/sigme
docker compose exec laravel.test php artisan sigme:create-admin \
  --name="Seu nome" \
  --email="seu-email@exemplo.com" \
  --generate
```

Guarde a senha em um gerenciador de senhas. O `.env` real e credenciais locais não são enviados ao GitHub.

## Uso diário

Pelo Windows, abra a pasta do projeto usando um caminho semelhante a:

```text
\\wsl.localhost\Ubuntu-24.04\home\seu-usuario\projetos\sigme
```

Depois use os atalhos:

- `Iniciar SIGME.cmd` — inicia os serviços, verifica a saúde e abre o navegador;
- `Status do SIGME.cmd` — mostra o estado dos serviços e do endpoint `/up`;
- `Parar SIGME.cmd` — interrompe os serviços sem apagar o banco.

Ou use o Ubuntu:

```bash
./scripts/wsl/start.sh
./scripts/wsl/status.sh
./scripts/wsl/stop.sh
```

Nunca use `docker compose down -v` no uso normal: a opção `-v` remove o volume do MySQL.

## Testes e build

Com os serviços iniciados:

```bash
docker compose exec -T laravel.test php artisan test --compact
docker compose exec -T laravel.test npm run build
```

Para consultar migrations:

```bash
docker compose exec -T laravel.test php artisan migrate:status
```

## Dados persistentes e segredos

- banco MySQL: volume Docker `sigme_mysql_data`;
- anexos privados: `~/sigme-data/uploads`;
- logs operacionais: `~/sigme-data/logs-operacionais`;
- `.env`: somente na máquina local e ignorado pelo Git;
- exemplos de configuração: `.env.example`, `.env.local.example`, `.env.demo.example` e `.env.testing.example`.

O snapshot versionado contém dados locais de demonstração e hashes de senha. O repositório deve permanecer privado enquanto ele estiver presente. Antes de tornar o código público, remova o snapshot do histórico Git e gere uma base fictícia nova.

## Estrutura principal

- `app/` — regras de negócio, modelos, políticas e serviços;
- `database/migrations/` — evolução do esquema;
- `database/snapshots/` — fotografia restaurável entregue com esta cópia privada;
- `resources/views/` — interface Blade;
- `routes/web.php` — rotas da aplicação;
- `tests/` — testes unitários e de integração;
- `docs/` — arquitetura, estados, permissões e decisões do projeto;
- `scripts/wsl/` e `scripts/windows/` — instalação e operação local.

## Documentação complementar

- `docs/PROJECT_STATE.md`
- `docs/ARCHITECTURE.md`
- `docs/12_STATE_MACHINE.md`
- `docs/13_PERMISSION_MATRIX.md`
- `docs/14_TRACEABILITY.md`
- `docs/LOCAL_WINDOWS_WSL_SETUP.md`
- `docs/LOCAL_START_STOP.md`
- `docs/LOCAL_TROUBLESHOOTING.md`

## Limite desta publicação

Esta entrega é adequada para desenvolvimento e homologação local. Antes de produção ainda devem ser definidos infraestrutura, domínio, HTTPS, e-mail real, monitoramento, política de backup criptografado, retenção, recuperação de desastre e gestão de segredos.
