# Prompt Mestre do SIGME

> **Status:** diretriz obrigatória para o beta local.
>
> Este documento é a fonte normativa atual para o ambiente do beta do SIGME. Em qualquer conflito, os requisitos abaixo substituem orientações anteriores que tornem VPS, hospedagem externa, Nginx, PHP-FPM ou um servidor Linux separado obrigatórios para desenvolver, testar, demonstrar ou avaliar o beta.
>
> A presença de um requisito neste documento não constitui evidência de que ele já foi implementado ou validado. A conformidade deverá ser demonstrada por código, testes e evidências de execução.

---
# Ajuste obrigatório do Prompt Mestre do SIGME — beta local no Windows com WSL 2

## 1. Prioridade deste ajuste

Esta seção substitui todas as instruções anteriores que tratem uma VPS, hospedagem externa, Nginx, PHP-FPM ou servidor Linux separado como requisitos obrigatórios para o beta.

O beta do SIGME deverá ser totalmente executável localmente em um computador com Windows.

A arquitetura de produção futura continuará documentada, mas não deverá ser necessária para desenvolver, testar, apresentar ou avaliar o beta.

O ambiente oficial será:

```text
Windows 11
└── WSL 2
    └── Ubuntu 24.04 LTS
        └── Docker Desktop com integração WSL
            └── Laravel Sail
                ├── Aplicação Laravel
                ├── MySQL
                ├── Worker de filas
                ├── Scheduler
                ├── Mailpit
                └── Serviços opcionais de testes
```

---

# 2. Objetivo do ambiente local

O ambiente deverá permitir que qualquer integrante autorizado da equipe:

1. clone o repositório;
2. execute um script de configuração;
3. inicie o SIGME;
4. abra o navegador;
5. utilize o sistema;
6. encerre os serviços;
7. reinicie o computador;
8. inicie novamente;
9. encontre os dados preservados.

O integrante não deverá precisar instalar separadamente:

* PHP;
* Composer;
* MySQL;
* Apache;
* Nginx;
* Node.js;
* npm;
* extensões PHP;
* servidor de e-mail;
* Redis.

Essas dependências deverão ser fornecidas pelo ambiente de containers.

---

# 3. Tecnologias obrigatórias do ambiente local

Utilize:

* Windows 11 como sistema hospedeiro;
* WSL 2;
* Ubuntu 24.04 LTS;
* Docker Desktop com backend WSL 2;
* integração do Docker Desktop com a distribuição Ubuntu;
* Laravel Sail;
* Docker Compose por meio do arquivo `compose.yaml`;
* PHP 8.5;
* Laravel 13;
* MySQL 8.4;
* Node.js na versão definida pelo projeto;
* npm;
* Mailpit;
* fila baseada no banco de dados;
* Laravel Scheduler;
* PHPUnit;
* Playwright ou Laravel Dusk para testes de navegador.

As versões deverão ser fixadas sempre que possível.

Não utilizar imagens Docker com a tag genérica `latest` nos serviços principais.

Manter:

* `composer.lock`;
* `package-lock.json`;
* versões de imagens;
* versão mínima do WSL;
* versão esperada do PHP;
* versão esperada do MySQL.

---

# 4. Tecnologias que não serão utilizadas como ambiente oficial

Não utilizar como ambiente principal:

* XAMPP;
* WampServer;
* Laragon;
* PHP instalado diretamente no Windows;
* MySQL instalado diretamente no Windows;
* IIS;
* máquina virtual completa pelo VirtualBox;
* Laravel Homestead;
* servidor externo obrigatório;
* banco de dados em nuvem obrigatório;
* armazenamento em nuvem obrigatório.

Essas tecnologias não deverão ser necessárias para executar o beta.

O XAMPP não será utilizado como ambiente oficial porque criaria diferenças entre os computadores da equipe em relação a:

* versões;
* extensões PHP;
* permissões;
* caminhos;
* agendamentos;
* filas;
* configuração do banco;
* comportamento entre Windows e Linux.

---

# 5. Localização obrigatória do projeto

O código-fonte deverá ficar dentro do sistema de arquivos do Ubuntu no WSL.

Exemplo:

```text
/home/victor/projetos/sigme
```

Ou, de forma genérica:

```text
~/projetos/sigme
```

Não utilizar como diretório principal de desenvolvimento:

```text
C:\Users\Usuario\Documents\sigme
```

Nem:

```text
/mnt/c/Users/Usuario/Documents/sigme
```

O código deverá permanecer dentro do Linux para reduzir problemas relacionados a:

* desempenho;
* permissões;
* diferenças de maiúsculas e minúsculas;
* execução de scripts;
* eventos do Vite;
* observação de arquivos;
* instalação de dependências;
* volumes do Docker.

O projeto poderá ser aberto pelo Visual Studio Code do Windows utilizando a extensão WSL.

Fluxo esperado:

```bash
cd ~/projetos/sigme
code .
```

O editor será exibido no Windows, mas os comandos, extensões e arquivos do projeto serão executados dentro do Ubuntu.

---

# 6. Separação entre código e dados

O código e os dados persistentes não deverão ficar misturados.

Estrutura recomendada:

```text
~/projetos/sigme
├── código-fonte
├── documentação
├── migrations
├── testes
└── scripts

~/sigme-data
├── uploads
├── backups
├── exports
├── restore-tests
└── logs-operacionais
```

O diretório `~/sigme-data` não deverá fazer parte do repositório Git.

O arquivo `.env` poderá possuir:

```env
SIGME_DATA_DIR=/home/usuario/sigme-data
SIGME_BACKUP_DIR=/home/usuario/sigme-data/backups
SIGME_UPLOAD_DIR=/home/usuario/sigme-data/uploads
```

O script de configuração deverá descobrir automaticamente o diretório pessoal do usuário, sem deixar o nome `victor` fixo no código.

---

# 7. Serviços do Docker Compose

O arquivo `compose.yaml` deverá definir, no mínimo, os seguintes serviços.

## 7.1 Aplicação

Nome sugerido:

```text
laravel.test
```

Responsabilidades:

* executar PHP;
* atender a aplicação Laravel;
* executar comandos Artisan;
* executar Composer;
* executar npm;
* processar requisições HTTP.

Porta local:

```text
127.0.0.1:8080
```

A aplicação deverá ser acessada por:

```text
http://localhost:8080
```

Não expor a aplicação para toda a rede local por padrão.

## 7.2 MySQL

Nome:

```text
mysql
```

Configuração:

* imagem fixada no MySQL 8.4;
* banco `sigme`;
* banco separado para testes;
* usuário específico da aplicação;
* senha carregada por variável de ambiente;
* charset `utf8mb4`;
* engine InnoDB;
* health check;
* volume nomeado persistente.

Volume sugerido:

```text
sigme_mysql_data
```

Não expor a porta `3306` no Windows por padrão.

A aplicação deverá acessar o banco pela rede interna do Docker:

```env
DB_HOST=mysql
DB_PORT=3306
```

Ferramentas administrativas só poderão acessar o banco por meio de perfil de desenvolvimento explícito.

## 7.3 Worker de filas

Nome:

```text
queue
```

Executar:

```bash
php artisan queue:work
```

Configuração inicial:

```env
QUEUE_CONNECTION=database
```

Responsabilidades:

* notificações;
* processamento de anexos;
* geração de relatórios;
* rotinas demoradas;
* tarefas de manutenção;
* operações assíncronas.

Configurar:

* timeout;
* número máximo de tentativas;
* tratamento de falhas;
* tabela `failed_jobs`;
* reinício seguro;
* execução após commit quando necessário.

Redis não será obrigatório no beta.

A migração para Redis deverá ser possível futuramente sem reescrever as regras de negócio.

## 7.4 Scheduler

Nome:

```text
scheduler
```

Executar continuamente:

```bash
php artisan schedule:work
```

Responsabilidades:

* verificar backups;
* iniciar backups;
* aplicar retenção;
* limpar tokens expirados;
* limpar arquivos temporários;
* verificar ordens atrasadas;
* gerar alertas;
* verificar notificações pendentes;
* registrar a saúde operacional.

Não depender do usuário abrir uma página do sistema para executar tarefas.

## 7.5 Mailpit

Nome:

```text
mailpit
```

Responsabilidades:

* receber e-mails locais;
* testar convites;
* testar recuperação de senha;
* testar notificações;
* evitar envio de e-mails reais durante o desenvolvimento.

Interface:

```text
http://localhost:8025
```

A porta deverá ficar vinculada somente a:

```text
127.0.0.1
```

## 7.6 Serviço de testes de navegador

Disponibilizar Selenium ou serviço equivalente somente no perfil de testes.

Esse serviço não deverá iniciar durante o uso comum do beta.

## 7.7 Serviço administrativo opcional

Adminer ou ferramenta semelhante poderá ser disponibilizada apenas no perfil `dev-tools`.

Regras:

* desativado por padrão;
* vinculado somente a `127.0.0.1`;
* não iniciar no modo de demonstração;
* não utilizar credenciais fixas no código;
* não considerar a ferramenta parte da interface do SIGME.

---

# 8. Perfis de execução

Criar três modos distintos.

## 8.1 Desenvolvimento

Arquivo sugerido:

```text
.env.local
```

Características:

* `APP_DEBUG=true`;
* Vite em modo de desenvolvimento;
* Mailpit ativo;
* ferramentas de desenvolvimento permitidas;
* dados falsos;
* logs detalhados;
* nunca utilizar dados pessoais reais.

## 8.2 Demonstração

Arquivo sugerido:

```text
.env.demo
```

Características:

* `APP_DEBUG=false`;
* assets compilados;
* dados fictícios controlados;
* nenhuma ferramenta administrativa exposta;
* mensagens de erro amigáveis;
* contas de demonstração;
* banner discreto indicando ambiente de demonstração;
* somente portas necessárias;
* sem stack trace no navegador.

Esse será o modo usado na apresentação do TCC.

## 8.3 Testes

Arquivo sugerido:

```text
.env.testing
```

Características:

* banco separado;
* dados descartáveis;
* e-mails falsos;
* arquivos temporários;
* execução automatizada;
* nenhuma interferência nos dados locais do beta.

Nunca executar testes automatizados no banco `sigme`.

---

# 9. Persistência dos dados

Os dados não poderão desaparecer quando os containers forem parados ou recriados.

## 9.1 Banco de dados

Utilizar volume Docker nomeado:

```text
sigme_mysql_data
```

O nome do projeto Docker deverá ser fixado:

```env
COMPOSE_PROJECT_NAME=sigme
```

Isso evita que cada pasta ou execução crie volumes com nomes diferentes.

## 9.2 Arquivos enviados

As fotos e PDFs deverão ser armazenados em diretório privado fora do repositório:

```text
~/sigme-data/uploads
```

Esse diretório deverá ser montado no container da aplicação.

Exemplo conceitual:

```text
~/sigme-data/uploads
→
/var/www/html/storage/app/private
```

Não guardar arquivos enviados em:

```text
public/
```

Não guardar uploads no Git.

Não utilizar BLOB no MySQL.

## 9.3 Código-fonte

O Git deverá guardar:

* código;
* migrations;
* testes;
* documentação;
* scripts;
* configurações de exemplo;
* arquivo Compose;
* locks de dependências.

O Git não deverá guardar:

* banco;
* uploads;
* backups;
* `.env`;
* chaves;
* senhas;
* tokens;
* dados pessoais;
* arquivos temporários.

---

# 10. Scripts obrigatórios

Criar a seguinte estrutura:

```text
scripts/
├── windows/
│   ├── check-prerequisites.ps1
│   ├── start-sigme.ps1
│   ├── stop-sigme.ps1
│   ├── status-sigme.ps1
│   ├── register-backup-task.ps1
│   ├── unregister-backup-task.ps1
│   └── open-sigme.ps1
│
└── wsl/
    ├── bootstrap.sh
    ├── start.sh
    ├── stop.sh
    ├── status.sh
    ├── backup.sh
    ├── restore.sh
    ├── verify-backup.sh
    └── reset-demo.sh
```

Também criar atalhos simples:

```text
Iniciar SIGME.cmd
Parar SIGME.cmd
Status do SIGME.cmd
Backup do SIGME.cmd
```

O comando de restauração não deverá ser executado automaticamente por duplo clique.

---

# 11. Verificação dos pré-requisitos

O script:

```text
check-prerequisites.ps1
```

deverá verificar:

* versão do Windows;
* existência do WSL;
* versão do WSL;
* distribuição Ubuntu;
* uso do WSL 2;
* Docker Desktop instalado;
* Docker Desktop em execução;
* integração Docker/Ubuntu;
* virtualização disponível;
* espaço livre;
* portas necessárias;
* acesso ao diretório do projeto.

O script não deverá afirmar que o ambiente está correto sem executar verificações reais.

Quando encontrar um problema, deverá informar:

1. o que está ausente;
2. por que é necessário;
3. como corrigir;
4. como verificar novamente.

Não alterar configurações críticas do Windows silenciosamente.

---

# 12. Configuração inicial

O script:

```text
scripts/wsl/bootstrap.sh
```

deverá:

1. verificar se está sendo executado dentro do WSL 2;
2. verificar se o Docker responde;
3. criar `~/sigme-data`;
4. criar diretórios de uploads e backups;
5. copiar `.env.example` para `.env`, se necessário;
6. gerar `APP_KEY`;
7. gerar segredo de backup;
8. configurar permissões;
9. instalar dependências do Composer;
10. instalar dependências npm;
11. construir os containers;
12. iniciar MySQL;
13. esperar o health check;
14. executar migrations;
15. criar banco de testes;
16. compilar assets;
17. criar usuário administrador local;
18. executar testes mínimos;
19. criar primeiro backup;
20. apresentar o endereço local.

O script deverá ser idempotente.

Executá-lo novamente não poderá:

* apagar dados;
* duplicar usuários;
* recriar configurações desnecessariamente;
* gerar outro volume;
* alterar chaves sem autorização.

---

# 13. Inicialização do SIGME

O script de inicialização deverá:

1. verificar WSL;
2. verificar Docker;
3. iniciar Docker Desktop quando possível;
4. aguardar o Docker responder;
5. entrar no diretório do projeto;
6. iniciar os containers;
7. aguardar o MySQL;
8. aguardar a aplicação;
9. verificar migrations pendentes;
10. fazer backup antes de migration relevante;
11. executar migrations autorizadas;
12. verificar filas;
13. verificar scheduler;
14. consultar `/up` ou endpoint de saúde;
15. abrir `http://localhost:8080`.

O sistema só deverá abrir o navegador depois que o endpoint de saúde retornar sucesso.

Não afirmar que o sistema iniciou apenas porque o comando Docker terminou.

---

# 14. Encerramento

O script de encerramento deverá utilizar:

```bash
docker compose stop
```

Ou comando equivalente do Sail.

Não executar automaticamente:

```bash
docker compose down -v
```

O parâmetro `-v` apaga volumes e poderá eliminar o banco.

O encerramento comum deverá preservar:

* banco;
* uploads;
* backups;
* configurações;
* logs necessários.

---

# 15. Proteção contra apagamento acidental

Nunca disponibilizar um botão comum que execute:

```bash
docker compose down -v
```

O script de reset deverá:

1. informar exatamente o que será apagado;
2. realizar backup;
3. verificar o backup;
4. exigir a frase:

```text
APAGAR DADOS LOCAIS DO SIGME
```

5. registrar a operação;
6. apagar somente após confirmação.

O modo de demonstração deverá possuir um reset próprio que apague somente os dados fictícios.

---

# 16. Backup automático local

O backup deverá ocorrer sem depender de ações dos usuários comuns.

Os usuários finais não deverão:

* clicar em “Fazer backup”;
* escolher arquivos;
* informar senhas;
* selecionar diretórios;
* interromper seu trabalho.

Os responsáveis técnicos deverão conhecer a existência, o destino e a política dos backups.

Não implementar envio escondido para serviços de terceiros.

## 16.1 Conteúdo do backup

Cada conjunto deverá conter:

* dump consistente do MySQL;
* arquivos privados enviados;
* manifesto;
* versão da aplicação;
* versão das migrations;
* data e hora;
* tamanho;
* hashes SHA-256;
* status da verificação.

Não incluir:

* cache;
* `node_modules`;
* `vendor`;
* logs temporários;
* imagens Docker;
* arquivos que possam ser reconstruídos;
* senha em texto puro.

## 16.2 Banco

Utilizar dump com opções apropriadas para consistência, incluindo:

* transação única;
* triggers;
* rotinas quando existentes;
* eventos quando existentes;
* dados binários corretamente tratados.

O backup deverá ser comprimido.

## 16.3 Arquivos

Criar arquivo compactado do diretório:

```text
~/sigme-data/uploads
```

Preservar estrutura e metadados necessários.

## 16.4 Criptografia

Os arquivos de backup deverão ser criptografados.

A chave deverá ficar fora:

* do Git;
* do diretório de backup;
* do código;
* do arquivo compartilhado da documentação.

Local sugerido:

```text
~/.config/sigme/backup.key
```

Permissão:

```text
600
```

A cópia enviada ao Windows deverá permanecer criptografada.

## 16.5 Destinos

Destino principal:

```text
~/sigme-data/backups
```

Segunda cópia:

```text
C:\SIGME\backups
```

Vista pelo WSL:

```text
/mnt/c/SIGME/backups
```

A pasta do Windows não deverá guardar a chave de descriptografia.

A segunda cópia deverá ocorrer depois da criação, criptografia e verificação do backup principal.

## 16.6 Agendamento dentro da aplicação

O Laravel Scheduler deverá:

* executar backup diariamente;
* impedir sobreposição;
* registrar início e conclusão;
* registrar falha;
* aplicar retenção;
* verificar hashes;
* criar alerta técnico.

O horário deverá ser configurável:

```env
BACKUP_DAILY_AT=20:00
```

## 16.7 Backup atrasado

Ao iniciar o SIGME, verificar a data do último backup válido.

Quando o último backup tiver mais de 24 horas:

1. iniciar um backup;
2. verificar;
3. copiar para o segundo destino;
4. somente então registrar sucesso.

Isso evita perder o backup quando o computador estava desligado no horário programado.

## 16.8 Agendamento pelo Windows

Criar:

```text
register-backup-task.ps1
```

Esse script deverá registrar no Agendador de Tarefas do Windows:

```text
SIGME-Backup-Diario
```

A tarefa deverá:

1. executar em horário configurado;
2. chamar o WSL;
3. localizar o projeto;
4. iniciar somente os serviços necessários;
5. aguardar o banco;
6. realizar o backup;
7. verificar o resultado;
8. registrar logs;
9. não abrir janelas desnecessárias;
10. não apagar containers;
11. não desligar serviços que já estavam sendo utilizados.

A tarefa deverá ser instalada conscientemente pelo responsável técnico durante a configuração.

Não criar tarefa agendada escondida sem informar o responsável pela instalação.

## 16.9 Retenção inicial

Política local inicial:

* 14 cópias diárias;
* 8 cópias semanais;
* 6 cópias mensais.

Antes de apagar um backup antigo, verificar se existem cópias mais recentes válidas.

## 16.10 Registro

Registrar em `backup_runs`:

* início;
* fim;
* tipo;
* status;
* tamanho;
* hash;
* destino;
* erro sanitizado;
* versão da aplicação;
* resultado da verificação.

O painel de backup não deverá aparecer para professores, técnicos ou usuários comuns.

Somente administradores técnicos autorizados poderão consultar a saúde dos backups.

---

# 17. Restauração

Criar comando seguro:

```bash
php artisan sigme:restore
```

Ou script equivalente.

A restauração deverá:

1. verificar autorização local;
2. listar backups válidos;
3. verificar assinatura e hash;
4. solicitar confirmação;
5. criar backup do estado atual;
6. parar filas;
7. bloquear alterações;
8. restaurar o banco;
9. restaurar uploads;
10. executar verificações;
11. liberar a aplicação;
12. registrar o resultado.

Nunca restaurar automaticamente por decisão da aplicação.

Nunca sobrescrever o banco atual sem criar um backup pré-restauração.

---

# 18. Teste automático de restauração

Uma vez por mês, ou sob comando explícito, o sistema deverá:

1. criar banco temporário;
2. restaurar o último backup;
3. validar migrations;
4. verificar tabelas;
5. verificar organizações;
6. verificar escolas;
7. verificar ocorrências;
8. verificar ordens;
9. verificar anexos de amostra;
10. executar testes de integridade;
11. registrar o resultado;
12. apagar o banco temporário.

Diretório temporário:

```text
~/sigme-data/restore-tests
```

Um backup não deverá ser considerado saudável apenas porque o arquivo existe.

---

# 19. Armazenamento de anexos no beta

No beta local, utilizar o driver privado local do Laravel.

Configuração conceitual:

```env
FILESYSTEM_DISK=private-local
```

Os arquivos deverão ficar em:

```text
~/sigme-data/uploads
```

A aplicação deverá fornecer o arquivo somente depois de:

1. autenticar;
2. identificar o usuário;
3. verificar organização;
4. verificar escola;
5. verificar permissão;
6. gerar resposta controlada.

Não disponibilizar URL pública direta.

A arquitetura deverá utilizar a abstração de arquivos do Laravel para permitir futura substituição por armazenamento compatível com S3.

A troca futura de `private-local` para `s3` não poderá exigir alteração nas regras de negócio.

---

# 20. Segurança local

Mesmo sendo local, o beta deverá respeitar segurança básica.

## 20.1 Portas

Vincular ao loopback:

```text
127.0.0.1
```

Serviços que não precisam ser acessados pelo Windows não deverão publicar portas.

MySQL não deverá ficar disponível para outros computadores da rede.

## 20.2 Dados

Utilizar apenas dados falsos ou autorizados durante o desenvolvimento.

Não inserir no beta:

* CPF real;
* RG real;
* senhas reais;
* prontuários;
* informações médicas;
* dados pessoais desnecessários;
* nomes de alunos sem autorização.

## 20.3 Segredos

Não versionar:

* `.env`;
* chave da aplicação;
* chave de backup;
* senhas;
* tokens;
* credenciais do banco;
* segredos MFA.

## 20.4 Debug

No modo de demonstração:

```env
APP_DEBUG=false
```

Nenhum stack trace deverá aparecer durante a apresentação.

## 20.5 Rede local

Não expor o sistema para a rede da escola automaticamente.

Um modo futuro de teste em rede deverá exigir:

* confirmação explícita;
* regra de firewall;
* credenciais fortes;
* dados fictícios ou autorizados;
* definição de hosts confiáveis;
* documentação;
* encerramento da exposição depois do teste.

---

# 21. E-mails locais

Durante o beta:

```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

Os e-mails deverão aparecer em:

```text
http://localhost:8025
```

Nenhum convite ou recuperação deverá ser enviado para pessoas reais por padrão.

O Mailpit permitirá testar:

* convite;
* ativação;
* recuperação;
* conclusão de ocorrência;
* atualização de ordem;
* alertas.

---

# 22. Dados de demonstração

Criar seeders separados.

## 22.1 Escola única

Criar cenário:

```text
Organização: Escola Independente de Demonstração
Escolas: 1
```

Nesse modo, o seletor de escolas deverá ficar oculto.

## 22.2 Rede de escolas

Criar cenário:

```text
Organização: Rede Escolar de Demonstração
Escolas:
- Escola Unidade Centro
- Escola Unidade Norte
- Escola Unidade Sul
```

Criar usuários fictícios:

* administrador da rede;
* administrador escolar;
* direção;
* professor;
* técnico de manutenção;
* representante de aluno opcional.

Criar:

* ambientes;
* ativos;
* ocorrências;
* ordens;
* inspeções;
* compras;
* indicadores.

Todos os dados deverão ser claramente fictícios.

## 22.3 Credenciais

Não deixar uma senha administrativa fixa no repositório principal.

O bootstrap deverá:

* solicitar senha;
* ou gerar senha aleatória;
* apresentar uma única vez;
* permitir alteração no primeiro acesso.

Contas de demonstração conhecidas poderão existir somente no modo `DEMO_MODE=true`.

---

# 23. Compatibilidade entre os computadores da equipe

Todos os integrantes deverão utilizar:

* WSL 2;
* Docker Desktop;
* Laravel Sail;
* versões fixadas;
* mesmo `compose.yaml`;
* mesmo `composer.lock`;
* mesmo `package-lock.json`.

O README deverá registrar:

* versão do WSL validada;
* distribuição validada;
* versão do Docker Desktop validada;
* versão do PHP;
* versão do Laravel;
* versão do MySQL;
* portas;
* comandos;
* problemas conhecidos.

Não aceitar como justificativa:

> “Na minha máquina funciona.”

A validação deverá ser feita em pelo menos dois computadores diferentes da equipe.

---

# 24. Final de linha e permissões

Criar `.gitattributes` adequado.

Exemplo conceitual:

```gitattributes
* text=auto

*.sh text eol=lf
*.php text eol=lf
*.js text eol=lf
*.css text eol=lf
*.json text eol=lf
*.yaml text eol=lf
*.yml text eol=lf

*.ps1 text eol=crlf
*.cmd text eol=crlf
```

Scripts `.sh` deverão possuir permissão de execução.

Não salvar shell scripts com final de linha incompatível com Linux.

---

# 25. Comandos padronizados

Documentar comandos curtos.

Exemplos:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail stop
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan test
./vendor/bin/sail npm run dev
./vendor/bin/sail npm run build
./vendor/bin/sail artisan queue:work
./vendor/bin/sail artisan schedule:list
```

Também criar aliases ou scripts:

```bash
./scripts/wsl/start.sh
./scripts/wsl/stop.sh
./scripts/wsl/status.sh
./scripts/wsl/backup.sh
```

A documentação deverá explicar o que cada comando faz e se existe risco de perda de dados.

---

# 26. Endpoint de saúde

Criar endpoint local:

```text
/up
```

Ou:

```text
/health
```

Ele deverá verificar, sem expor informações sensíveis:

* aplicação;
* banco;
* fila;
* scheduler;
* armazenamento;
* último backup.

A resposta pública comum não deverá revelar:

* versão completa do servidor;
* senha;
* caminhos internos;
* stack trace;
* credenciais;
* nomes de containers.

Um diagnóstico mais detalhado deverá exigir permissão técnica.

---

# 27. Arquivos de documentação adicionais

Criar:

```text
docs/
├── LOCAL_WINDOWS_WSL_SETUP.md
├── LOCAL_START_STOP.md
├── LOCAL_BACKUP_RESTORE.md
├── LOCAL_TROUBLESHOOTING.md
├── LOCAL_DATA_STORAGE.md
└── FUTURE_PRODUCTION_MIGRATION.md
```

## `LOCAL_WINDOWS_WSL_SETUP.md`

Explicar:

* pré-requisitos;
* instalação do WSL;
* Ubuntu;
* Docker Desktop;
* integração WSL;
* clone;
* bootstrap;
* primeiro acesso.

## `LOCAL_START_STOP.md`

Explicar:

* iniciar;
* encerrar;
* verificar status;
* abrir navegador;
* resolver porta ocupada;
* reiniciar containers.

## `LOCAL_BACKUP_RESTORE.md`

Explicar:

* frequência;
* destinos;
* criptografia;
* retenção;
* backup manual;
* restauração;
* verificação;
* riscos.

## `LOCAL_TROUBLESHOOTING.md`

Cobrir:

* Docker não iniciou;
* WSL está na versão 1;
* porta 8080 ocupada;
* MySQL não fica saudável;
* permissão negada;
* scripts com CRLF;
* Vite não atualiza;
* volume ausente;
* pouco espaço;
* backup falhou;
* navegador não abre.

## `FUTURE_PRODUCTION_MIGRATION.md`

Documentar futuramente:

* Linux;
* Nginx;
* PHP-FPM;
* HTTPS;
* MySQL gerenciado;
* object storage;
* workers;
* scheduler;
* monitoramento;
* backup externo.

Não implementar essa produção como requisito do beta.

---

# 28. Mudanças nas fases do projeto

## Fase 2 — Fundação técnica local

Substituir a fase anterior por:

1. configurar WSL 2;
2. definir Ubuntu 24.04;
3. criar ambiente Sail;
4. criar `compose.yaml`;
5. fixar versões;
6. criar volumes;
7. configurar MySQL;
8. configurar Mailpit;
9. configurar fila;
10. configurar scheduler;
11. configurar diretórios persistentes;
12. criar scripts Windows;
13. criar scripts WSL;
14. criar endpoint de saúde;
15. criar documentação local;
16. validar em dois computadores.

## Fase de backup local

Antes da conclusão do beta:

1. implementar backup;
2. implementar criptografia;
3. implementar hashes;
4. implementar retenção;
5. implementar cópia para Windows;
6. registrar tarefa agendada;
7. restaurar banco temporário;
8. verificar anexos;
9. registrar evidências.

---

# 29. Critérios de aceitação do ambiente local

* `AC-LOCAL-001`: o SIGME inicia em Windows 11 com WSL 2.
* `AC-LOCAL-002`: não exige PHP instalado diretamente no Windows.
* `AC-LOCAL-003`: não exige MySQL instalado diretamente no Windows.
* `AC-LOCAL-004`: não exige XAMPP.
* `AC-LOCAL-005`: abre em `http://localhost:8080`.
* `AC-LOCAL-006`: banco permanece depois de parar containers.
* `AC-LOCAL-007`: uploads permanecem depois de parar containers.
* `AC-LOCAL-008`: reiniciar o Windows não apaga dados.
* `AC-LOCAL-009`: `start-sigme.ps1` inicia os serviços.
* `AC-LOCAL-010`: `stop-sigme.ps1` encerra sem apagar volumes.
* `AC-LOCAL-011`: o modo demonstração não mostra stack trace.
* `AC-LOCAL-012`: MySQL não fica exposto à rede.
* `AC-LOCAL-013`: Mailpit recebe e-mails de teste.
* `AC-LOCAL-014`: worker processa jobs.
* `AC-LOCAL-015`: scheduler executa tarefas.
* `AC-LOCAL-016`: backup é criado automaticamente.
* `AC-LOCAL-017`: backup possui hash válido.
* `AC-LOCAL-018`: segunda cópia é criada no Windows.
* `AC-LOCAL-019`: restauração funciona em banco temporário.
* `AC-LOCAL-020`: nenhum segredo está no Git.
* `AC-LOCAL-021`: o projeto funciona em pelo menos dois computadores.
* `AC-LOCAL-022`: escola única funciona sem seletor desnecessário.
* `AC-LOCAL-023`: rede de escolas mantém isolamento.
* `AC-LOCAL-024`: testes não utilizam o banco principal.
* `AC-LOCAL-025`: reset exige confirmação e backup prévio.

---

# 30. Proibições específicas do ambiente local

Não:

* desenvolver oficialmente dentro de `/mnt/c`;
* utilizar XAMPP como ambiente principal;
* exigir instalação manual de PHP em cada computador;
* exigir instalação manual de MySQL em cada computador;
* expor MySQL para a rede;
* executar `down -v` nos scripts comuns;
* apagar volume durante atualização;
* guardar uploads dentro do Git;
* guardar backup junto da chave;
* usar backup sem teste de restauração;
* utilizar banco principal nos testes;
* enviar e-mails reais por padrão;
* abrir Adminer no modo demonstração;
* deixar `APP_DEBUG=true` durante apresentação;
* depender do navegador para executar backup;
* afirmar que o backup passou sem validar hash;
* guardar somente uma cópia do backup;
* enviar dados para nuvem sem autorização;
* misturar dados de demonstração com dados reais;
* iniciar serviços desnecessários;
* tornar a instalação local mais complexa do que a produção futura exige.

---

# 31. Resultado esperado

Ao final, o avaliador ou integrante da equipe deverá conseguir:

1. ligar o computador;
2. clicar em “Iniciar SIGME”;
3. aguardar a verificação automática;
4. acessar o sistema no navegador;
5. utilizar todas as funções do beta;
6. encerrar o sistema;
7. iniciar novamente;
8. manter os dados;
9. possuir backup automático;
10. restaurar os dados quando necessário.

O beta deverá demonstrar:

* organização;
* segurança;
* durabilidade;
* isolamento entre escolas;
* facilidade de instalação;
* facilidade de manutenção;
* preparação para produção futura;
* preparação para aplicativo mobile futuro.

A infraestrutura local não deverá impedir que o sistema seja migrado futuramente para um servidor Linux. A mudança futura deverá envolver principalmente configuração e implantação, não reescrita das regras de negócio.

