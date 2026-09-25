# Backup e restauração

## Escopo e estado

O projeto possui scripts para criar, verificar e ensaiar backups no ambiente
oficial WSL/Sail. Cada execução preserva:

- dump lógico consistente do MySQL, com transação única;
- uploads privados;
- `.env`, necessário para conservar `APP_KEY` e demais parâmetros de recuperação;
- metadados e manifesto SHA-256 interno.

O `.env` existe apenas dentro do pacote criptografado. O artefato final usa
AES-256-CBC com PBKDF2/SHA-256 e 600.000 iterações. Um SHA-256 externo permite
detectar corrupção antes da descriptografia.

Os scripts sempre passam explicitamente o nome do projeto, o arquivo Compose e
o `.env` ao Docker Compose. Isso evita operar acidentalmente sobre outro projeto
Compose disponível no mesmo computador.

Esta rotina não substitui uma segunda cópia em outro disco ou local. Um backup
que existe apenas no mesmo servidor não cobre perda do equipamento.

## Chave criptográfica

A chave fica fora do repositório e do diretório de backups:

```text
~/.config/sigme/backup.key
```

O arquivo deve pertencer ao usuário operacional, usar permissão `600` ou `400`
e nunca ser versionado. O bootstrap atual cria essa chave quando ela não existe.

Guarde uma cópia offline protegida da chave, separada dos backups. Sem a chave,
os artefatos são irrecuperáveis. Não coloque a chave dentro do mesmo diretório
que será copiado como backup.

## Criar um backup

Com MySQL em execução:

```bash
cd ~/projetos/sigme
./scripts/wsl/backup.sh
```

Para identificar execuções operacionais:

```bash
./scripts/wsl/backup.sh --label antes-atualizacao
```

O rótulo aceita letras minúsculas, números e hífen. O destino é o valor de
`SIGME_BACKUP_DIR`; na instalação padrão, `~/sigme-data/backups`.

Durante a captura, a aplicação entra brevemente em manutenção e fila/agendador
são pausados quando estavam ativos. O estado anterior é restaurado ao final,
inclusive quando a criação falha. MySQL nunca é publicado na rede do host.

Um backup concluído produz somente:

```text
sigme-AAAAMMDDTHHMMSSZ-rotulo.backup.enc
sigme-AAAAMMDDTHHMMSSZ-rotulo.backup.enc.sha256
```

Arquivos SQL, uploads e `.env` em claro são temporários, usam permissão restrita
e são removidos pelo `trap` do script.

## Verificar um backup

```bash
./scripts/wsl/verify-backup.sh \
  ~/sigme-data/backups/sigme-AAAAMMDDTHHMMSSZ-manual.backup.enc
```

A verificação:

1. confere o SHA-256 externo;
2. descriptografa em diretório temporário protegido;
3. rejeita entradas inesperadas ou não regulares;
4. confere o manifesto SHA-256 interno;
5. testa o gzip do dump e o tar dos uploads;
6. valida os metadados mínimos e a presença de `APP_KEY`/`DB_DATABASE`;
7. remove todo o conteúdo temporário.

Um arquivo existente não é considerado backup válido enquanto essa verificação
não terminar com código zero.

## Ensaio real de restauração

Antes de confiar em um backup, importe-o em um banco descartável:

```bash
./scripts/wsl/restore-test.sh \
  ~/sigme-data/backups/sigme-AAAAMMDDTHHMMSSZ-manual.backup.enc
```

O ensaio cria um banco com prefixo `sigme_restore_test_`, importa o dump, executa
`CHECK TABLE`, confirma histórico de migrations, extrai os uploads em área de
ensaio e compara a quantidade de arquivos com os metadados. Ao final, remove o
banco e a área temporária. O banco definido por `DB_DATABASE` nunca é usado como
destino do ensaio.

O aceite operacional exige executar esse comando sobre um backup novo e guardar
a saída da execução. Apenas `bash -n` ou a existência do arquivo não comprovam
recuperação.

## Restaurar o banco principal

Esta operação é destrutiva. Primeiro faça o ensaio acima. Depois execute em um
terminal interativo:

```bash
./scripts/wsl/restore-backup.sh \
  ~/sigme-data/backups/sigme-AAAAMMDDTHHMMSSZ-manual.backup.enc
```

O script informa projeto, banco e diretório de uploads e exige uma frase exata
no formato:

```text
RESTAURAR projeto:banco
```

Não existe opção `--force` ou confirmação por argumento. Antes de substituir
qualquer dado, o script cria e verifica um novo backup com rótulo
`pre-restore`.

Depois da confirmação e do backup preventivo, o fluxo:

1. valida o pacote e compara a `APP_KEY` preservada com a instalação atual;
2. prepara os uploads em diretório separado;
3. para aplicação, fila e agendador;
4. recria e importa somente o banco explicitamente configurado;
5. executa `CHECK TABLE` em todas as tabelas;
6. troca o diretório de uploads;
7. reinicia apenas os serviços que estavam ativos antes da restauração.

Se qualquer passo falhar depois que a substituição começar, aplicação, fila e
agendador permanecem parados. O backup pré-restauração e, quando já movidos, os
uploads anteriores permanecem preservados, e a mensagem de erro mostra seus
caminhos.

O `.env` do artefato não substitui automaticamente o `.env` atual. Em uma
recuperação completa de outro servidor, restaure-o manualmente com permissão
`600`, junto da chave correta, antes de iniciar containers. Essa separação evita
trocar inadvertidamente credenciais de um servidor ativo.

## Tarefa diária no Windows/WSL

Abra PowerShell na pasta WSL do projeto. Para registrar uma execução diária às
20h, sem iniciar a tarefa imediatamente:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass `
  -File .\scripts\windows\register-backup-task.ps1 -At 20:00
```

O registro:

- aponta para a distribuição e o caminho WSL exatos do projeto atual;
- usa nome estável `SIGME - Backup diario criptografado`;
- executa tarefas perdidas quando o computador voltar;
- ignora uma segunda execução simultânea;
- tenta novamente até três vezes;
- não contém chave, senha ou conteúdo do `.env`;
- não inicia um backup no momento do registro.

O Docker precisa estar disponível no WSL no horário. Depois do registro, faça
um teste consciente pelo Agendador de Tarefas ou com:

```powershell
Start-ScheduledTask -TaskName 'SIGME - Backup diario criptografado'
```

Em seguida, confirme o resultado pelo histórico da tarefa e valide o novo
artefato com `verify-backup.sh` e `restore-test.sh`.

Para substituir conscientemente uma tarefa já existente:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass `
  -File .\scripts\windows\register-backup-task.ps1 -At 20:00 -Force
```

Para remover somente a tarefa, preservando chave, banco, uploads e backups:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass `
  -File .\scripts\windows\unregister-backup-task.ps1
```

## Retenção e segunda cópia

Não há remoção automática nesta versão. Isso é intencional: apagar backups sem
confirmar uma cópia mais nova e restaurável pode eliminar o último ponto de
recuperação.

Até definir a infraestrutura final:

1. crie o backup diário;
2. verifique-o;
3. execute periodicamente `restore-test.sh`;
4. copie o par `.backup.enc`/`.sha256` para outro disco ou local;
5. registre data, hash e resultado do ensaio;
6. só então remova manualmente artefatos antigos conforme política aprovada.

Nunca copie `backup.key` junto do mesmo conjunto de artefatos. Mantenha a chave
em custódia separada e protegida.
