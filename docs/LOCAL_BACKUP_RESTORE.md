# Backup e restauração

## Estado

Não implementado e não certificado nesta fundação. Não dependa de ~/sigme-data/backups para recuperação até que os scripts e o ensaio de restauração sejam concluídos.

## Contrato da próxima etapa

O fluxo deverá:

1. produzir dump consistente do banco;
2. incluir uploads e metadados necessários;
3. criptografar antes de gravar o artefato final;
4. não registrar segredos nem passá-los em argumentos de processo;
5. gerar manifesto e SHA-256;
6. verificar integridade e descriptografia;
7. aplicar retenção segura;
8. restaurar primeiro em ambiente isolado;
9. bloquear qualquer restauração sobre dados reais sem confirmação explícita;
10. registrar uma tarefa automática no Windows e validar sua execução.

O aceite exige uma restauração real em instalação limpa, não apenas a criação de um arquivo.

