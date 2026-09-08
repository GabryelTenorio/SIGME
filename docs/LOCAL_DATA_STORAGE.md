# Dados locais

## Localizações

- .env: raiz do projeto, ignorado pelo Git e com permissão 600;
- banco: volume Docker sigme_mysql_data;
- uploads: ~/sigme-data/uploads;
- exports: ~/sigme-data/exports;
- logs: ~/sigme-data/logs-operacionais;
- backups futuros: ~/sigme-data/backups;
- ensaios futuros: ~/sigme-data/restore-tests;
- chave futura de backup: ~/.config/sigme/backup.key, permissão 600.

O volume do banco e os diretórios externos ao repositório sobrevivem a scripts/wsl/stop.sh.

## Limites da etapa

A criação do diretório e da chave não constitui um backup. Nenhum artefato restaurável foi certificado ainda. Consulte LOCAL_BACKUP_RESTORE.md.

