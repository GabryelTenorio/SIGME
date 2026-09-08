# Migração futura para produção

O beta local não é uma arquitetura de produção. A migração deverá ser tratada como projeto separado e só começa após requisitos funcionais e não funcionais aprovados.

Itens mínimos:

- runtime Linux suportado e processo de implantação reproduzível;
- TLS, domínio e política de acesso;
- banco gerenciado ou operação equivalente com alta disponibilidade;
- armazenamento de objetos para uploads;
- serviço real de e-mail;
- filas e scheduler supervisionados;
- cofre de segredos e rotação;
- observabilidade, alertas e trilha de auditoria;
- backups externos, retenção e ensaios periódicos de restauração;
- migrações com rollback e plano de janela;
- testes de carga, segurança e recuperação;
- política de privacidade, retenção e acesso aos dados.

Configuração local, Mailpit, senhas e volumes não devem ser promovidos diretamente.

