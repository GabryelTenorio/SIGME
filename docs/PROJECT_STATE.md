# Estado do projeto

Atualizado em 08/09/2026.

## Fase atual

O SIGME é um projeto existente em consolidação. O marco aprovado é estabilizar e documentar o que já existe, corrigir os gaps críticos e somente depois ativar o módulo de Ordens de Serviço no banco principal.

Não está autorizada nesta fase a expansão para equipamentos, indicadores ou outras funcionalidades futuras.

## Baseline verificado

Estão implementados e cobertos pela suíte atual:

- autenticação com validação de usuário ativo;
- organizações com modo escola única ou rede;
- escolas, usuários, perfis acumuláveis e permissões por capacidade;
- isolamento de acesso por organização e escola;
- ambientes e histórico de ambientes;
- categorias de ocorrência e disponibilidade por escola;
- ocorrências desde a abertura até `ENCAMINHADA`;
- protocolo sequencial por escola e ano;
- triagem, solicitação de informação, duplicidade, não procede e prioridade;
- histórico de ocorrências;
- código do módulo de Ordens de Serviço, incluindo atribuição, aprovação, execução, materiais, custos, tempos, anexos privados, conclusão, resolução, encerramento e reabertura.
- catálogo consolidado com 43 capacidades e seis perfis padrão; a interface e o backend rejeitam perfis personalizados na V1.

Verificações executadas até 04/09/2026:

- serviços Docker/Sail ativos;
- 72 rotas registradas pela aplicação;
- suíte completa: **112 testes aprovados, 778 asserções**;
- as 28 migrations da fundação e do núcleo de OS estão executadas no banco principal;
- quatro migrations funcionais posteriores permanecem pendentes no banco principal: notificações internas, preferência de e-mail, dados do fornecedor externo e remoção da capacidade redundante; no banco isolado `sigme_testing`, todas estão executadas.

Os testes de OS usam o banco de testes isolado. Em 03/09/2026, as migrations ensaiadas também foram aplicadas e verificadas no banco principal deste computador.

## Decisões aprovadas em 31/08/2026

1. Toda execução de manutenção deve possuir uma Ordem de Serviço.
2. A reabertura deve ser registrada como evento e retornar a ocorrência para `EM_TRIAGEM`; não haverá estado persistido `REABERTA`.
3. Somente usuário ativo da escola com capacidades técnicas de visualizar e executar OS pode ser responsável ou membro executor.
4. Quem cria uma OS que exige aprovação não pode aprová-la.
5. Equipamentos e patrimônio ficam fora da primeira versão.
6. Notificações internas fazem parte do primeiro fluxo; o envio por e-mail deve ser configurável por usuário.
7. O primeiro marco é estabilizar, documentar, corrigir gaps e ativar as OS com validação completa.
8. Uma OS urgente pode começar antes da aprovação regular somente com autorização de outro gestor/administrador, motivo, notificação e ratificação posterior.
9. Para novos usuários, o canal de e-mail começa desativado e pode ser ativado individualmente.
10. O estado de OS `PLANEJADA` foi removido do fluxo ativo.
11. Históricos serão append-only na aplicação, com restrições de exclusão no banco e testes, sem triggers.
12. A ratificação de início emergencial deve ocorrer até o final do próximo dia útil.
13. Na primeira versão, históricos não terão exclusão automática; a retenção definitiva será decidida antes da produção.
14. Notificações de triagem e aprovação serão enviadas a todos os usuários elegíveis da escola pela respectiva capacidade.
15. Uma ocorrência reaberta que exija nova manutenção deve receber nova OS; as OS anteriores permanecem concluídas e preservadas.
16. O gestor valida e encerra a ocorrência; o solicitante pode registrar recorrência e pedir reabertura, mas não muda o estado diretamente.
17. Se a ratificação emergencial perder o prazo, a execução pode continuar, mas a OS não pode ser concluída e os gestores devem receber alertas até a ratificação.
18. A triagem não terá dono exclusivo; alterações concorrentes serão detectadas e a segunda gravação deverá recarregar os dados.
19. A abertura de ocorrência aceitará imagens e PDF opcionais, privados, com limite de 10 MB por arquivo.
20. A primeira versão usará somente os perfis padrão; administradores poderão atribuí-los, mas não criar ou personalizar perfis.
21. Serviço externo continuará como OS, com responsável interno, dados do fornecedor, custos e evidências; o fornecedor não terá conta no SIGME.
22. A abertura aceitará no máximo 5 anexos por ocorrência.
23. O solicitante poderá adicionar evidências enquanto a ocorrência estiver `ABERTA`, `EM_TRIAGEM` ou `AGUARDANDO_INFORMACOES`.
24. Fornecedor externo exigirá nome/empresa e descrição do serviço; contato e CNPJ/CPF serão opcionais.
25. Ratificação emergencial vencida gerará alerta no vencimento e uma vez por dia útil até ser ratificada.
26. E-mails conterão somente protocolo, tipo do evento, status e link, sem descrição sensível.

Documentos normativos desta etapa:

- `docs/12_STATE_MACHINE.md`;
- `docs/13_PERMISSION_MATRIX.md`;
- `docs/GAP_AUDIT_OCCURRENCE_FLOW.md`.

## Bloqueadores atuais

1. Resolvido em 03/09/2026: as dez migrations foram aplicadas no banco principal após backup, ensaio e autorização explícita.
2. As transições estão distribuídas entre controllers, sem um componente central de domínio.
3. Notificações internas, preferência individual e e-mail mínimo opcional estão concluídos em código e no banco de teste; as migrations específicas aguardam implantação no banco principal.
4. Resolvido em código e testes em 08/09/2026: início emergencial, prazo, ratificação, continuidade técnica, bloqueio da conclusão e alertas recorrentes em dias úteis estão concluídos; a ativação no principal depende da migration de notificações já pendente.
5. Resolvido em 01/09/2026: `PLANEJADA` foi removido do catálogo, das transições e do valor padrão da migration; o ensaio não encontrou registros para converter.
6. Resolvido em 04/09/2026: ocorrências aceitam até 5 JPEG, PNG ou PDF privados de 10 MB na abertura e inclusão posterior exclusiva do autor nos três estados aprovados, com download isolado e limite total transacional de 20 arquivos.
7. Resolvido em código e banco de teste em 04/09/2026: OS externa exige fornecedor estruturado e responsável interno elegível; o fornecedor não recebe identidade ou acesso; custo, documentos, evidências, histórico, conclusão e resolução foram comprovados no fluxo completo; a migration correspondente ainda não foi aplicada ao banco principal.
8. Resolvido em 01/09/2026: `external_cost` e `other_cost` foram removidos, `service_order_costs` ficou como fonte canônica e o histórico de OS passou a nascer com FK restritiva.
9. Resolvido em código e testes em 04/09/2026: autor pode pedir reabertura de ocorrência encerrada sem alterar o estado; decisores elegíveis recebem uma notificação por evento e somente o gestor realiza a transição.
10. Resolvido em código e banco de teste em 04/09/2026: a capacidade redundante `ocorrencias.visualizar_todas` foi removida sem ampliar acessos, com migration reversível aguardando implantação no banco principal; somente os seis perfis padrão podem ser atribuídos pela interface e pelo backend.

## Pendências documentais

- manter `docs/14_TRACEABILITY.md` e `docs/10_TASKS.md` atualizados conforme as tarefas forem realmente validadas.

## Próxima tarefa recomendada

Executar `TSK-VAL-002`: revisar as quatro migrations pendentes, preparar a implantação controlada no banco principal e validar containers, fila, agendador e HTTP antes dos fluxos manuais finais.

Depois, seguir a sequência funcional e a validação final definidas em `docs/10_TASKS.md`. O backup imediato da ativação está em `/home/e-not-094/backups/sigme/20260903-091925-pre-db004`.

## Desvios deste computador

O ambiente oficial ainda não foi certificado neste equipamento:

- distribuição instalada: Ubuntu 26.04 LTS, não Ubuntu 24.04;
- usuário atual: root, não um usuário Linux comum;
- Docker Engine nativo no WSL, não Docker Desktop;
- o Docker Engine nativo acompanha o ciclo de vida de sessões do WSL e não permanece estável sem uma sessão ativa;
- na validação de 04/09/2026, o SIGME foi exposto em `127.0.0.1:8080` e respondeu `200` em `/up` e `/entrar`; uma rota autenticada redirecionou corretamente para o login.

Os scripts do Windows continuam recusando esse ambiente sem a autorização técnica explícita `SIGME_ALLOW_UNSUPPORTED_HOST=1`.

## Pendências operacionais posteriores

- implementar e testar backup criptografado, verificação e restauração;
- implementar reset de demonstração;
- registrar a tarefa automática de backup no Windows;
- executar ensaio de restauração em instalação limpa;
- validar o pacote oficial em pelo menos dois computadores Windows 11;
- certificar Ubuntu 24.04 com Docker Desktop e porta 8080 livre.

Requisitos documentados não devem ser apresentados como funcionalidades implementadas.
