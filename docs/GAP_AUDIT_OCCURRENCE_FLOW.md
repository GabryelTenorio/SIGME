# Auditoria de Gap — Fluxo de Ocorrências do SIGME

Atualizado em 08/09/2026.

## 1. Objetivo e limites

Esta auditoria compara o fluxo macro fornecido em `NCIA Occurrence Management-2026-08-28-133222 (2).png` com o estado atual do SIGME em `/home/e-not-094/projetos/sigme`.

Foram considerados como evidência:

- código, migrations, rotas, políticas, views e testes existentes no SIGME;
- estado atual das migrations no banco principal;
- execução atual da suíte automatizada;
- o fluxograma como visão funcional desejada, não como especificação automaticamente aprovada;
- o Prompt Mestre como referência de processo, não como fonte de regras de negócio.

Foi explicitamente excluído:

- `School-Facility-Manager (2).zip`: não foi aberto, inspecionado ou usado como referência técnica, funcional ou visual.

Esta etapa não implementa funcionalidades, não aplica migrations e não altera regras de negócio.

## 2. Conclusão executiva

O SIGME é um projeto existente em consolidação. Não há justificativa técnica para reinício ou reescrita.

O estado real é:

1. A fundação de autenticação, organização, escola, usuário, ambiente, categoria e isolamento por escola existe e está coberta por testes.
2. O núcleo de ocorrências, da abertura até `ENCAMINHADA`, existe no banco principal, no código e nos testes.
3. O núcleo de Ordens de Serviço e manutenção existe no código e nos testes, incluindo atribuição, aprovação, execução, materiais, custos, tempos, anexos privados, conclusão, resolução, encerramento e reabertura.
4. As dez migrations de Ordem de Serviço foram aplicadas e verificadas no banco principal deste computador em 03/09/2026; o núcleo de OS e as restrições históricas estão materializados.
5. A suíte completa passou em 01/09/2026 após corrigir e ensaiar as migrations: **84 testes e 483 asserções**.
6. O `docs/PROJECT_STATE.md` foi atualizado após a auditoria para refletir o baseline real e as decisões aprovadas.
7. O fluxograma original e o código ainda divergem em pontos importantes, mas já foi decidido preservar a Ordem de Serviço intermediária e tratar reabertura como evento com retorno a `EM_TRIAGEM`.
8. A máquina de estados, a matriz de permissões, a rastreabilidade detalhada e o plano de tarefas foram formalizados após as decisões de 31/08/2026.

### Fase atual real

| Fase funcional | Situação real |
| --- | --- |
| Fundação e administração básica | Implementada e testada, com lacunas administrativas específicas |
| Ocorrência até encaminhamento | Implementada, migrada e testada |
| Ordem de Serviço e execução da manutenção | Implementada em código e testada; não migrada no banco principal |
| Notificações, equipamentos, exceções completas e indicadores | Não implementada ou apenas parcialmente representada |

## 3. Cobertura por bloco do fluxograma

Legenda:

- **Implementado**: comportamento disponível no código, banco principal e testes proporcionais.
- **Implementado em código**: existe e passa em testes, mas não está operacional no banco principal.
- **Parcial**: parte relevante existe, mas faltam ações, regras ou integrações do fluxograma.
- **Não implementado**: não há evidência executável correspondente.
- **Conflito**: o código adotou uma regra diferente da apresentada no fluxograma.
- **Não especificado**: o fluxograma não define o suficiente para uma decisão segura.

| ID | Bloco do fluxo | Situação | Evidência atual | Gap principal |
| --- | --- | --- | --- | --- |
| FLOW-AUTH-001 | Login, usuário ativo e bloqueio de acesso | Implementado | `LoginController`, `LoginRequest`, `LoginTest` | Sem gap funcional crítico identificado nesta auditoria |
| FLOW-AUTH-002 | Identificar organização, escola, perfil e permissões | Implementado | `User`, `RoleAssignment`, `AccessCatalog`, políticas e testes de perfis padrão | Capacidade redundante removida; personalização de perfis permanece deliberadamente fora da V1 |
| FLOW-ADM-001 | Gerenciar organização/rede | Implementado | `OrganizationController`, `OrganizationPolicy` | Administração de organização é restrita; precisa ser refletida no fluxo por escopo |
| FLOW-ADM-002 | Gerenciar escolas | Implementado | `SchoolController`, testes administrativos | Sem exclusão física, coerente com preservação de histórico |
| FLOW-ADM-003 | Gerenciar usuários | Implementado | `UserController`, perfis acumuláveis e escopo por escola | Alterações de vínculos e perfis não possuem histórico dedicado |
| FLOW-ADM-004 | Gerenciar perfis e permissões | Implementado conforme escopo aprovado | Os seis perfis padrão são provisionados por `AccessCatalog`; tela, validação e gravação rejeitam perfis não padrão | Personalização foi retirada da primeira versão e sua ausência está protegida por testes |
| FLOW-ADM-005 | Gerenciar ambientes/salas | Implementado | `EnvironmentController`, `EnvironmentHistory` | Sem gap crítico para o fluxo de ocorrência |
| FLOW-ADM-006 | Gerenciar equipamentos | Não implementado | Não existem model, migration, controller ou rota de equipamentos | Ocorrências não podem relacionar equipamento opcional como pede o fluxo |
| FLOW-ADM-007 | Gerenciar categorias | Implementado | `OccurrenceCategoryController` e disponibilidade por escola | Não há histórico dedicado de alterações de categoria |
| FLOW-ADM-008 | Consultar relatórios e histórico global | Não implementado | Não existem rotas ou views correspondentes | Históricos existem por registro, mas não há consulta administrativa consolidada |
| FLOW-OCC-001 | Registrar nova ocorrência | Parcial | `OccurrenceController`, `OccurrenceRequest`, `PrivateAttachmentController` e `OccurrenceWorkflowTest` cobrem abertura, inclusão posterior exclusiva do autor, privacidade, limites 5/20 e 10 MB | Equipamento ficou fora da primeira versão |
| FLOW-OCC-002 | Validar ambiente, categoria e escopo escolar | Implementado | Validação server-side em `OccurrenceRequest` | Equipamento opcional não pode ser validado porque o domínio não existe |
| FLOW-OCC-003 | Gerar protocolo sequencial por escola/ano | Implementado | `OccurrenceSequence`, transação e testes | Formato atual `SIG-{ESCOLA}-{ANO}-{SEQUÊNCIA}` está alinhado ao fluxo |
| FLOW-OCC-004 | Registrar criação no histórico e abrir ocorrência | Implementado | `OccurrenceHistory`, status `ABERTA` | Histórico é lógico, mas não é imutável no banco |
| FLOW-OCC-005 | Notificar responsável pela triagem | Não implementado | Nenhuma Notification, job ou evento correspondente | O fluxo segue apenas por consulta ao painel |
| FLOW-TRI-001 | Visualizar fila, selecionar e iniciar triagem | Implementado | Lista, transições, token de versão, transação e bloqueio da linha | Sem posse exclusiva; gravação obsoleta é rejeitada e exige recarregamento |
| FLOW-TRI-002 | Solicitar e fornecer informação adicional | Implementado | `AGUARDANDO_INFORMACOES` e retorno a `EM_TRIAGEM` | Não há notificação ao solicitante ou à triagem |
| FLOW-TRI-003 | Marcar duplicada | Implementado | Referência `duplicate_of_id`, justificativa opcional e histórico | O fluxograma sugere referência principal; implementação atende o núcleo |
| FLOW-TRI-004 | Marcar não procede | Implementado | Status `NAO_PROCEDE`, motivo e histórico | Sem gap crítico identificado |
| FLOW-TRI-005 | Avaliar impacto/urgência e sugerir prioridade | Implementado | Matriz determinística em `Occurrence::suggestedPriority()` | A matriz ainda não está registrada como regra aprovada fora do código |
| FLOW-TRI-006 | Confirmar prioridade | Implementado | Permissão específica, nota obrigatória e histórico | Sem gap crítico identificado |
| FLOW-FWD-001 | Selecionar técnico/responsável e validar acesso | Conflito | Encaminhamento salva texto livre em `forwarded_destination`; usuário é atribuído depois na OS | Não há vínculo estruturado com técnico no encaminhamento |
| FLOW-FWD-002 | Encaminhar e registrar histórico | Implementado | Status `ENCAMINHADA` e evento `forwarded` | Depende de prioridade confirmada, coerente com o fluxo |
| FLOW-FWD-003 | Notificar técnico | Não implementado | Nenhum mecanismo de notificação | Técnico depende da lista/OS para perceber a atribuição |
| FLOW-OS-001 | Visualizar encaminhadas e iniciar atendimento | Implementado | `ServiceOrderController`, `ServiceOrderWorkflowController`, testes e banco principal | O código exige criar uma OS; essa fronteira ainda precisa aparecer no fluxograma atualizado |
| FLOW-OS-002 | Atribuir técnico/equipe | Implementado | `User::isEligibleForServiceOrderAssignment()`, `assigned_user_id` e `service_order_members` | Validação e tela exigem usuário ativo, organização, escola, `visualizar` e `executar` |
| FLOW-OS-003 | Registrar diagnóstico | Implementado | Diagnóstico obrigatório antes da conclusão | Sem gap crítico identificado |
| FLOW-OS-004 | Executar manutenção e registrar ações | Implementado | Atualizações, materiais, tempos, custos e anexos privados | Sem gap crítico identificado |
| FLOW-OS-005 | Aguardar material ou pausar | Implementado em código | Estados `AGUARDANDO_MATERIAL` e `PAUSADA`, com retomada | O fluxograma possui motivos adicionais ainda não modelados de forma equivalente |
| FLOW-OS-006 | Fornecedor externo | Implementado em código | Flag, fornecedor estruturado, responsável interno obrigatório, ausência de identidade/acesso e fluxo testado de custo, PDF, imagem, histórico e conclusão | Migration dos campos do fornecedor ainda precisa de implantação controlada no banco principal |
| FLOW-OS-007 | Aguardar autorização/aprovação | Implementado | Limite configurável, condições especiais, aprovar/rejeitar e bloqueio do próprio criador | Segregação passa em testes, inclusive para perfis acumulados e administração de plataforma |
| FLOW-OS-008 | Atendimento emergencial | Implementado em código e testes | Início urgente exige autorizador separado e executor elegível, registra motivo/prazo, admite ratificação auditada e agenda alertas recorrentes por capacidade e data | Ativação dos alertas no banco principal depende da migration de notificações pendente |
| FLOW-RES-001 | Concluir manutenção e marcar resolvida | Implementado | Exige diagnóstico e solução; resolve após todas as OS válidas | Regra de múltiplas OS ainda não aparece no fluxograma |
| FLOW-RES-002 | Validar resultado, encerrar ou reabrir | Implementado em código; diagrama desatualizado | Gestor encerra ou reabre para `EM_TRIAGEM`; autor pede reabertura em `ENCERRADA` sem mudar o estado e decisores elegíveis são notificados sem duplicidade | Fluxograma original ainda representa `REABERTA`, estado deliberadamente não persistido |
| FLOW-HIST-001 | Histórico imutável com ator, data, ação e estados | Implementado | Models append-only e FKs dos pais em `RESTRICT` | Política definitiva de retenção permanece fora deste marco |
| FLOW-KPI-001 | Indicadores por período, status, prioridade, ambiente e equipamento | Não implementado | Existe apenas a permissão `indicadores.visualizar` | Dashboard atual mostra somente contagens estruturais |
| FLOW-KPI-002 | Tempos, recorrência e acompanhamento contínuo | Não implementado | Há dados de tempo em OS, mas nenhuma consolidação | Métricas, definições e consultas ainda precisam ser especificadas |

## 4. Máquina de estados encontrada no código

### 4.1 Ocorrência

| Estado atual | Ação | Próximo estado | Regra principal |
| --- | --- | --- | --- |
| `ABERTA` | iniciar triagem | `EM_TRIAGEM` | Permissão `ocorrencias.triar` |
| `ABERTA` ou `EM_TRIAGEM` | solicitar informação | `AGUARDANDO_INFORMACOES` | Permissão específica e mensagem obrigatória |
| `AGUARDANDO_INFORMACOES` | solicitante fornece informação | `EM_TRIAGEM` | Apenas o autor da ocorrência |
| `ABERTA`, `EM_TRIAGEM` ou `AGUARDANDO_INFORMACOES` | marcar não procede | `NAO_PROCEDE` | Motivo obrigatório |
| `ABERTA`, `EM_TRIAGEM` ou `AGUARDANDO_INFORMACOES` | marcar duplicada | `DUPLICADA` | Referência da mesma escola |
| `EM_TRIAGEM` | encaminhar | `ENCAMINHADA` | Prioridade deve estar confirmada |
| `ENCAMINHADA` | iniciar primeira OS | `EM_ATENDIMENTO` | OS aprovada ou início emergencial |
| `EM_ATENDIMENTO` | concluir todas as OS válidas | `RESOLVIDA` | OS canceladas/rejeitadas não bloqueiam resolução |
| `RESOLVIDA` | encerrar | `ENCERRADA` | Permissão `ocorrencias.encerrar` |
| `RESOLVIDA` ou `ENCERRADA` | reabrir | `EM_TRIAGEM` | Motivo obrigatório |

Estados existentes: `ABERTA`, `EM_TRIAGEM`, `ENCAMINHADA`, `AGUARDANDO_INFORMACOES`, `NAO_PROCEDE`, `DUPLICADA`, `EM_ATENDIMENTO`, `RESOLVIDA`, `ENCERRADA`.

Estado do fluxograma ausente: `REABERTA`.

### 4.2 Ordem de Serviço

| Estado atual | Ação | Próximo estado |
| --- | --- | --- |
| criação | avaliar aprovação | `APROVADA` ou `AGUARDANDO_APROVACAO` |
| `AGUARDANDO_APROVACAO` | aprovar | `APROVADA` |
| `AGUARDANDO_APROVACAO` | rejeitar | `REJEITADA` |
| `AGUARDANDO_APROVACAO` urgente | início emergencial autorizado | `EM_EXECUCAO` |
| `APROVADA` | iniciar | `EM_EXECUCAO` |
| `EM_EXECUCAO` | aguardar material | `AGUARDANDO_MATERIAL` |
| `EM_EXECUCAO` | pausar | `PAUSADA` |
| `AGUARDANDO_MATERIAL` ou `PAUSADA` | retomar | `EM_EXECUCAO` |
| `EM_EXECUCAO` | concluir | `CONCLUIDA` |
| estado operacional não final | cancelar | `CANCELADA` |

Decisão implementada em 01/09/2026: `PLANEJADA` foi removida do fluxo aprovado, do catálogo, das transições ativas e do valor padrão da migration.

## 5. Matriz resumida de permissões encontrada

O controle é por capacidades, não por títulos fixos. Os perfis apenas agregam permissões.

| Capacidade funcional | Solicitante | Gestor | Técnico | Administrador |
| --- | --- | --- | --- | --- |
| Criar ocorrência | Sim | Sim | Sim | Sim |
| Ver próprias ocorrências | Sim | Sim | Sim | Sim |
| Ver ocorrências da escola | Não | Sim | Encaminhadas | Sim |
| Triar, priorizar, duplicar e não proceder | Não | Sim | Não | Sim |
| Encaminhar ocorrência | Não | Sim | Não | Sim |
| Criar e atribuir OS | Não | Sim | Não | Sim |
| Aprovar/rejeitar/cancelar OS | Não | Sim | Não | Sim |
| Executar, diagnosticar e concluir OS | Não | Não por padrão | Sim se atribuído | Sim |
| Encerrar/reabrir ocorrência | Não | Sim | Não | Sim |
| Ver indicadores | Não | Sim | Não | Sim |

Lacunas da matriz executável:

1. Resolvido em 04/09/2026: `ocorrencias.visualizar_todas` foi removida do catálogo e do perfil gestor, preservando `ocorrencias.visualizar_escola`.
2. `ordens_servico.executar` permanece como requisito de elegibilidade técnica; as ações continuam exigindo capacidades específicas de menor privilégio.
3. Resolvido no banco principal em 03/09/2026: a atribuição de OS exige as capacidades de visualizar e executar.
4. Não há matriz documental aprovada que explique escopos de organização, escola, autoria e atribuição.
5. Não está decidido se a mesma pessoa pode criar e aprovar a própria OS.

## 6. Achados prioritários

### GAP-001 — Migrations de Ordens de Serviço pendentes

- Severidade: **bloqueador**.
- Evidência: `migrate:status` em 31/08/2026 mostrou oito migrations `2026_08_27_170109` a `2026_08_27_170146` como `Pending`.
- Impacto: rotas de OS referenciam tabelas inexistentes no banco principal; testes isolados não tornam o módulo operacional.
- Recomendação: só aplicar após revisão da máquina de estados, permissões e ensaio de migration/rollback/backup.

### GAP-002 — Documentação oficial não representava o código atual

- Severidade original: **alto**.
- Evidência original: `docs/PROJECT_STATE.md` declarava funcionalidades de negócio como pendentes.
- Tratamento em 31/08/2026: `PROJECT_STATE` atualizado com baseline, bloqueadores, decisões e próxima tarefa.
- Situação: **resolvido documentalmente**; as migrations de OS também foram ativadas em 03/09/2026.

### GAP-003 — Fluxo de manutenção diverge entre ocorrência e OS

- Severidade: **alto**.
- Evidência: o fluxograma leva o técnico diretamente de `ENCAMINHADA` a `EM_ATENDIMENTO`; o código exige uma Ordem de Serviço intermediária.
- Impacto: estado, permissão, telas e indicadores podem contar eventos de maneiras incompatíveis.
- Recomendação: preservar a OS como agregado operacional e atualizar o fluxo aprovado para explicitar essa fronteira.

### GAP-004 — Atribuição pode produzir OS sem executor apto

- Severidade original: **alto**.
- Tratamento em 31/08/2026: backend e tela passaram a exigir usuário ativo, mesma organização, acesso à escola e capacidades `ordens_servico.visualizar` e `ordens_servico.executar`.
- Evidência: `User::isEligibleForServiceOrderAssignment()`, `ServiceOrderRequest`, `ServiceOrderController` e `ServiceOrderWorkflowTest`.
- Validação atual: suítes focadas de OS e histórico com 25 testes/231 asserções e suíte completa com 84 testes/483 asserções.
- Situação: **resolvido em código, testes e banco principal deste computador**.

### GAP-005 — Estado `REABERTA` divergia entre fluxograma e código

- Severidade original: **alto**.
- Evidência: o fluxograma usa `REABERTA`; o código reabre diretamente em `EM_TRIAGEM`.
- Decisão em 31/08/2026: registrar evento `reopened` e retornar a `EM_TRIAGEM`, sem estado persistido `REABERTA`.
- Situação: **decisão de domínio resolvida**; resta atualizar a representação visual/rastreabilidade do fluxo.

### GAP-006 — Notificações do fluxo não existem

- Severidade: **alto** operacional.
- Evidência: não há Notification, evento, job ou canal para avisar triagem, solicitante, técnico ou gestor.
- Impacto: o fluxo depende de consulta manual e pode parar silenciosamente.
- Decisão em 31/08/2026: notificação interna para todos os elegíveis da escola, e-mail individual opcional e desativado por padrão, com deduplicação por usuário/evento.
- Situação: **regra especificada; implementação pendente**.

### GAP-007 — Histórico ainda não é tecnicamente imutável

- Severidade original: **médio**.
- Evidência original: tabelas de histórico eram gravadas como eventos, mas models permitiam mutação e FKs apagavam eventos por cascata.
- Decisão em 31/08/2026: proteção append-only na aplicação, restrições de exclusão no banco e testes, sem triggers.
- Tratamento em 01/09/2026: models bloqueiam update/delete; migration troca as FKs dos históricos para `RESTRICT`; testes provaram preservação dos pais e eventos.
- Validação: rollback e reaplicação das dez migrations do módulo passaram somente em `sigme_testing`; suíte completa com 84 testes/483 asserções.
- Situação: **resolvido em código, banco de teste e banco principal deste computador**; retenção definitiva permanece adiada até antes da produção.

### GAP-008 — Equipamentos e indicadores estão ausentes

- Severidade: **médio**, dependente de escopo.
- Evidência: não há domínio de equipamentos nem rotas de indicadores/relatórios.
- Impacto: partes administrativas e métricas do fluxograma não podem ser entregues.
- Decisão em 31/08/2026: equipamentos ficam fora da primeira versão; indicadores não avançam durante o marco de estabilização.
- Situação: **escopo resolvido para a primeira versão**.

## 7. Decisões aprovadas e pendências

Decisões aprovadas em 31/08/2026:

1. **Ordem de Serviço**: toda execução de manutenção nasce de uma OS.
2. **Reabertura**: evento auditável com retorno a `EM_TRIAGEM`; não haverá estado persistido `REABERTA`.
3. **Atribuição**: responsável e membros devem estar ativos, pertencer à escola e possuir capacidades de visualizar e executar OS.
4. **Aprovação**: o criador não pode aprovar a própria OS quando houver aprovação obrigatória.
5. **Equipamento**: fica fora da primeira versão.
6. **Notificações**: notificação interna e e-mail; o canal de e-mail será configurável por usuário.
7. **Marco inicial**: estabilizar e documentar o existente, corrigir gaps e somente então ativar as OS no banco principal.
8. **Emergência**: exige outro gestor/administrador, motivo, notificação e ratificação posterior.
9. **E-mail**: novos usuários começam com o canal desativado e podem ativá-lo.
10. **Estado `PLANEJADA`**: removido do fluxo ativo.
11. **Histórico**: append-only na aplicação, exclusões restritas no banco e testes, sem triggers.
12. **Prazo emergencial**: ratificação até o final do próximo dia útil.
13. **Retenção**: sem exclusão automática na primeira versão; política definitiva antes da produção.
14. **Destinatários**: todos os usuários ativos e elegíveis da escola pela capacidade relacionada.
15. **Nova manutenção após reabertura**: exige nova OS e preserva as anteriores.
16. **Validação da resolução**: gestor encerra; solicitante pode pedir reabertura sem alterar o estado diretamente.
17. **Ratificação vencida**: trabalho continua, conclusão fica bloqueada e gestores recebem alertas.
18. **Concorrência de triagem**: sem dono exclusivo, com detecção de conflito e recarga obrigatória.
19. **Evidências iniciais**: imagens e PDF privados, opcionais, até 10 MB por arquivo.
20. **Perfis**: somente perfis padrão atribuíveis na primeira versão.
21. **Fornecedor externo**: usa OS com responsável interno; fornecedor não acessa o sistema.
22. **Quantidade inicial**: até 5 anexos na abertura.
23. **Evidências posteriores**: autor pode adicionar em `ABERTA`, `EM_TRIAGEM` ou `AGUARDANDO_INFORMACOES`.
24. **Dados do fornecedor**: nome/empresa e descrição obrigatórios; contato e CNPJ/CPF opcionais.
25. **Alertas vencidos**: no vencimento e uma vez por dia útil até a ratificação.
26. **E-mail mínimo**: protocolo, evento, status e link, sem descrição sensível.
27. **Limite total de evidências**: no máximo 20 arquivos por ocorrência, somando abertura e inclusões posteriores.

## 8. Sequência de tarefas recomendada

As tarefas abaixo são propostas, não estão automaticamente aprovadas para implementação.

### TSK-001 — Consolidar a fonte de verdade do projeto

- Status documental: concluída em 31/08/2026.
- Atualizar `PROJECT_STATE` com o baseline auditado.
- Registrar o fluxo macro como referência e suas divergências conhecidas.
- Não alterar código funcional.

### TSK-002 — Aprovar a máquina de estados de Ocorrência e OS

- Status documental: concluída em 31/08/2026; gaps de implementação permanecem abertos.
- Criar `12_STATE_MACHINE.md`.
- Resolver OS intermediária, `REABERTA`, múltiplas OS e estados de pendência.
- Vincular transições a critérios de aceitação e testes existentes.

### TSK-003 — Aprovar a matriz de permissões

- Status documental: concluída em 31/08/2026; gaps de implementação permanecem abertos.
- Criar `13_PERMISSION_MATRIX.md`.
- Formalizar escopo de organização, escola, autoria, atribuição e aprovação.
- Remover ou justificar capacidades não utilizadas.

### TSK-004 — Criar a rastreabilidade do fluxograma

- Status documental: concluída em 31/08/2026; execução das tarefas permanece aberta.
- Criar `14_TRACEABILITY.md`.
- Mapear `FLOW → requisito → regra → tarefa → teste → código`.
- Tratar cada bloco macro separadamente.

### TSK-005 — Estabilizar atribuição e transições críticas

- Impedir responsável de OS sem capacidades mínimas.
- Centralizar ou encapsular as transições para evitar regras distribuídas.
- Adicionar testes de concorrência e transições inválidas relevantes.

### TSK-006 — Ensaiar e aplicar migrations de OS

- Concluído em 03/09/2026: backup verificável e cópia descartável restaurada em `sigme_rehearsal_20260903_084331`.
- Concluído em 03/09/2026: migrate, rollback, comparação de dados e restauração passaram na cópia controlada.
- Concluído em 03/09/2026: migrations aplicadas no banco principal deste computador após autorização explícita.
- Reexecutar migration status, suíte completa, build, HTTP e inspeção de navegador.

### TSK-007 — Implementar notificações mínimas

- Cobrir criação, solicitação/resposta de informação, encaminhamento, atribuição, pendência, resolução, encerramento e reabertura.
- Incluir testes de destinatário, isolamento escolar e falha de entrega.

### TSK-008 — Decidir e planejar equipamentos

- Só iniciar após confirmar escopo e modelo de dados.
- Preservar ocorrência sem equipamento, conforme o ramo opcional do fluxo.

### TSK-009 — Definir e implementar indicadores

- Definir métricas, filtros, fonte, timezone e regras de cálculo.
- Implementar somente após dados e estados estarem estabilizados.

## 9. Critério para considerar a Fase 1 congelada

A Fase 1 pode ser considerada baseline estável quando:

1. a máquina de estados e a matriz de permissões estiverem aprovadas;
2. as divergências de OS e reabertura estiverem resolvidas documentalmente;
3. a atribuição inválida estiver coberta por teste e correção;
4. migrations de OS estiverem aplicadas e verificadas no banco principal;
5. a suíte completa, build, `/up` e fluxos principais passarem após a última alteração;
6. `PROJECT_STATE` refletir o estado real;
7. nenhuma funcionalidade do ZIP excluído tiver sido incorporada.

## 10. Evidência de validação desta auditoria

Executado em 31/08/2026 e atualizado até 03/09/2026:

- `./vendor/bin/sail ps`: aplicação, MySQL e Mailpit saudáveis; fila e scheduler ativos.
- `php artisan migrate:status`: 28 migrations executadas e zero pendências no banco principal deste computador.
- `php artisan route:list --except-vendor --no-ansi`: 72 rotas, incluindo ocorrências e OS.
- `php artisan test`: **84 testes aprovados, 483 asserções** após corrigir e ensaiar as migrations em `sigme_testing`.

Também executado em 03/09/2026: backup imediato, ensaio de recuperação, aplicação das migrations, `CHECK TABLE`, build de produção e smoke HTTP. Permanece pendente a validação manual em navegador autenticado.
- envio real de notificações, pois o recurso não existe;
- qualquer inspeção do ZIP explicitamente excluído.
