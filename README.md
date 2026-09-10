# SIGME

Sistema de Gestão de Manutenção Escolar para registrar ocorrências, realizar triagem, emitir e executar Ordens de Serviço, controlar aprovações, custos, materiais, evidências privadas, notificações e reaberturas.

O projeto é uma aplicação Laravel executada localmente com Docker. Aplicação, fila, agendador, MySQL e Mailpit funcionam em serviços separados. Existem duas opções: instalação pelo Windows com Docker Desktop e ambiente de desenvolvimento com Ubuntu/WSL e Laravel Sail.

## Windows com Docker Desktop — instalação sem terminal Ubuntu

Nesta opção você pode baixar o projeto em `C:\SIGME` e usar os atalhos do Windows. Não precisa instalar PHP, Composer, Node, MySQL ou uma distribuição Ubuntu manualmente. O Docker Desktop administra o ambiente Linux interno; mantenha **Linux containers** selecionado. Isso não é execução com Windows containers nem elimina o backend de virtualização do Docker.

Requisitos: Windows 11 x64 atualizado, virtualização habilitada, Docker Desktop com Docker Compose v2 (`--wait` disponível), PowerShell 5.1 ou superior, internet na instalação, 8 GB de RAM e pelo menos 20 GB livres. Para uso mais confortável, prefira 16 GB e SSD com 30 GB livres. Consulte os requisitos e a instalação atuais do [Docker Desktop para Windows](https://docs.docker.com/desktop/setup/install/windows-install/).

1. Instale e abra o Docker Desktop. Conclua a configuração solicitada por ele e aguarde o mecanismo ficar pronto.
2. Baixe e extraia o ZIP deste repositório privado em `C:\SIGME`, ou clone-o com Git para Windows. A pasta deve conter `compose.windows.yaml` e os atalhos `.cmd`.
3. Dê dois cliques em **Instalar SIGME Docker.cmd**. A primeira execução baixa dependências, constrói a imagem, cria o banco vazio e aplica as migrations. O console mostra o progresso e os erros, se houver.
4. Execute **Criar administrador SIGME Docker.cmd**. Informe seu nome e e-mail; guarde a senha gerada, exibida uma única vez. Usar um e-mail já cadastrado atualiza essa conta e sua senha.
5. Entre em [http://localhost:8080](http://localhost:8080). Os e-mails de teste aparecem em [http://localhost:8025](http://localhost:8025).

No uso diário, abra o Docker Desktop e use **Iniciar SIGME Docker.cmd**, **Status SIGME Docker.cmd** ou **Parar SIGME Docker.cmd**. Parar mantém os dados.

Também é possível usar PowerShell, dentro da pasta do projeto:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\docker.ps1 -Action Install
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\docker.ps1 -Action Start
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\docker.ps1 -Action Status
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\docker.ps1 -Action Admin
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\docker.ps1 -Action Logs
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\docker.ps1 -Action Stop
```

Para escolher outras portas na primeira instalação, acrescente `-Port 8082 -MailpitPort 8027`. Depois que `.env.windows` existir, altere `APP_PORT` e `MAILPIT_PORT` nele e reinicie. As opções de porta da linha de comando só preenchem o arquivo na primeira instalação.

### Dados e configuração nesta opção

- `.env.windows`: chaves e senhas geradas localmente; ignorado pelo Git e pelo build da imagem. Preserve esse arquivo em cópia segura. Não o substitua pelo exemplo depois de criar o banco.
- `sigme-windows_mysql`: banco MySQL persistente.
- `sigme-windows_uploads`: anexos privados persistentes.
- `sigme-windows_logs`: logs persistentes.
- A imagem contém somente código e dependências; não inclui o banco, o snapshot antigo, `.env`, uploads, testes ou checkpoints locais.
- As portas são limitadas a `127.0.0.1`. MySQL não tem porta publicada.

O projeto Docker `sigme-windows` é separado do ambiente antigo `sigme`. Seus dados antigos não são importados automaticamente. Se os dois estiverem ativos, escolha portas diferentes. Não altere `SIGME_WINDOWS_PROJECT` depois de começar a usar a instalação: ele identifica os volumes. Não exclua esses volumes nem execute `down -v` se quiser conservar seus dados. Copiar somente o código para outro PC não transfere banco e anexos; preserve os volumes e `.env.windows` em seu procedimento de backup.

Para atualizar o código desta opção, faça backup dos dados, baixe a nova versão preservando `.env.windows`, pare os serviços e execute novamente **Instalar SIGME Docker.cmd**. O instalador reutiliza chaves e volumes existentes e aplica migrations pendentes. O código é copiado para a imagem: alterações em arquivos só aparecem após reconstruí-la.

Os scripts `scripts/wsl/*` e o procedimento de restauração abaixo pertencem à opção Sail/WSL. Não use o restaurador antigo para restaurar o banco da opção Windows/Docker.

Verificação em 10/09/2026: imagem construída do zero, 32 migrations aplicadas em banco isolado, criação de administrador, login HTTP a partir do Windows, CSS/JS e saúde com HTTP 200, persistência do administrador e de um arquivo privado após parar e iniciar os serviços. O script foi validado sintaticamente no Windows PowerShell 5.1, incluindo a mensagem de erro quando Docker Desktop está ausente. Esses testes usaram o Docker Engine disponível no WSL; a instalação completa em um Windows novo com Docker Desktop ainda precisa ser ensaiada.

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
- 137 testes automatizados, com 1.021 asserções, aprovados em 10/09/2026;
- build de produção e revisão responsiva validados em 10/09/2026.

## Desenvolvimento com Sail/WSL — compatibilidade e requisitos

As seções seguintes descrevem o ambiente Sail/WSL original, usado no desenvolvimento. Para instalar pelo Windows sem usar o Ubuntu manualmente, utilize a opção acima. macOS, Linux nativo, Windows Server e processadores ARM não fazem parte da matriz de homologação desta V1.

### Mínimo obrigatório

- Windows 11 de 64 bits, atualizado;
- processador x86-64 com 4 núcleos, SLAT e virtualização habilitada no BIOS/UEFI;
- 8 GB de memória RAM;
- 20 GB livres na unidade do sistema para código, imagens e volumes;
- WSL 2 versão 2.1.5 ou superior;
- Ubuntu 24.04 LTS no WSL;
- Docker Desktop com backend WSL 2 e integração com o Ubuntu habilitada;
- Git instalado dentro do Ubuntu;
- acesso à internet na primeira instalação;
- portas locais 8080, 8025, 1025 e 5173 disponíveis.

### Recomendado

- processador com 6 núcleos ou mais;
- 16 GB de RAM ou mais;
- 30 GB livres em SSD;
- Docker Desktop atualizado e pelo menos 6 GB de memória disponíveis para WSL/Docker.

O verificador incluído no projeto exige Windows 11 e Ubuntu 24.04. Os requisitos básicos de WSL e Docker podem ser consultados na [documentação da Microsoft](https://learn.microsoft.com/windows/wsl/install) e na [documentação do Docker Desktop](https://docs.docker.com/desktop/setup/install/windows-install/).

No modo de desenvolvimento Sail, o código deve ficar no filesystem Linux, por exemplo `/home/seu-usuario/projetos/sigme`. Evite `/mnt/c` ou `/mnt/e` nesse modo. A instalação Windows/Docker acima pode manter sua cópia em `C:\SIGME`, pois executa o código dentro da imagem, sem montar a pasta do Windows nos containers.

## Instalação em um computador novo

### 1. Preparar o Windows

Abra o PowerShell como administrador e execute:

```powershell
wsl --install -d Ubuntu-24.04
```

Reinicie o computador se o Windows solicitar. Abra o Ubuntu uma vez e conclua a criação do usuário Linux. Depois, instale e abra o Docker Desktop, selecione o backend WSL 2 e habilite a integração para `Ubuntu-24.04` em **Settings > Resources > WSL Integration**.

### 2. Instalar o Git no Ubuntu

No terminal Ubuntu, execute:

```bash
sudo apt update
sudo apt install -y git
```

### 3. Baixar e instalar o SIGME

Abra o terminal do Ubuntu no WSL e execute:

```bash
mkdir -p ~/projetos
cd ~/projetos
git clone https://github.com/GabryelTenorio/SIGME.git sigme
cd sigme
chmod +x scripts/wsl/*.sh
./scripts/wsl/bootstrap.sh
```

Como o repositório é privado, o GitHub solicitará autenticação no primeiro clone. Use uma conta autorizada; em clone HTTPS, use um token pessoal no lugar da senha da conta. Não salve o token no projeto nem no `.env`.

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

### 4. Criar o primeiro administrador

Em uma instalação vazia, crie uma conta administrativa:

```bash
docker compose exec laravel.test php artisan sigme:create-admin \
  --name="Seu nome" \
  --email="seu-email@exemplo.com" \
  --generate
```

O comando mostra uma senha segura uma única vez. Guarde-a em um gerenciador de senhas e entre em http://localhost:8080.

## Restaurar o banco incluído neste repositório

O arquivo `database/snapshots/sigme.sql.gz` contém uma fotografia restaurável do banco local de 08/09/2026, após as migrations da V1 e antes da massa temporária usada na auditoria final. O snapshot possui 10 usuários, 4 escolas e 1 ocorrência original. Não havia arquivos de upload nesse estado.

Depois de executar o bootstrap, restaure essa fotografia com:

```bash
cd ~/projetos/sigme
./scripts/wsl/restore-repository-snapshot.sh
```

O script mostra o banco de destino, pede a confirmação exata `RESTAURAR`, verifica o SHA-256, pausa somente os serviços que usam o banco, recria o banco `sigme`, importa o snapshot e inicia novamente os serviços.

Essa operação substitui os dados existentes. Não a execute sobre um banco que contenha informações que você queira preservar.

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

## Atualizar uma instalação existente

Antes de atualizar, preserve seus dados e confirme que não há alterações locais que você queira manter. No Ubuntu:

```bash
cd ~/projetos/sigme
git status
git pull --ff-only
docker compose run --rm laravel.test composer install --no-interaction --prefer-dist --optimize-autoloader
docker compose run --rm laravel.test npm ci
docker compose run --rm laravel.test npm run build
docker compose run --rm laravel.test php artisan migrate --force --no-interaction
./scripts/wsl/start.sh
```

O comando de migration atualiza a estrutura do banco sem restaurar o snapshot. Nunca use o script de restauração para uma atualização comum.

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

## Solução rápida de problemas

- **Docker não responde no Ubuntu:** abra o Docker Desktop e confira a integração WSL com `Ubuntu-24.04`.
- **Porta ocupada:** libere 8080, 8025, 1025 ou 5173, ou altere as portas correspondentes no `.env`.
- **Página sem o visual atualizado:** execute `docker compose run --rm laravel.test npm run build` e atualize o navegador com `Ctrl+F5`.
- **Diagnóstico completo:** execute `powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/windows/check-prerequisites.ps1` a partir da pasta do projeto no Windows.
- **Ver logs:** execute `docker compose logs --tail=200 laravel.test queue scheduler mysql`.
