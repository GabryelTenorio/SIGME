# Matriz de Rastreabilidade — SIGME

Atualizado em 08/09/2026.

Status: baseline de rastreabilidade aprovado para orientar a estabilização. Esta matriz não comprova implementação; a coluna **Situação** distingue regra, código, banco e teste.

## 1. Como usar esta matriz

Cada requisito recebe um identificador estável e aponta para:

- o bloco de origem do fluxo auditado;
- a regra aprovada na máquina de estados ou matriz de permissões;
- a evidência de código e teste já existente;
- o gap ou tarefa que ainda impede a entrega.

Situações possíveis:

- **Operacional**: código, banco principal e testes proporcionais existem;
- **Código testado**: código e testes existem, mas as migrations de OS ainda não foram aplicadas ao banco principal;
- **Parcial**: há implementação, mas falta parte da regra aprovada;
- **Pendente**: não há implementação correspondente;
- **Fora da V1**: requisito deliberadamente excluído da primeira versão.

### 1.1 Fontes normativas

- estados, transições, prazos, destinatários e efeitos: `docs/12_STATE_MACHINE.md`;
- capacidades, perfis, escopos, segregação e elegibilidade: `docs/13_PERMISSION_MATRIX.md`;
- comparação com o fluxograma e achados: `docs/GAP_AUDIT_OCCURRENCE_FLOW.md`;
- sequência de implementação e critérios de aceite: `docs/10_TASKS.md`.

### 1.2 Mapa de autorização por requisito

Este mapa completa a ligação `fluxo → requisito → permissão`. A consolidação de TSK-SEC-005 removeu explicitamente a capacidade redundante `ocorrencias.visualizar_todas` e preservou `ocorrencias.visualizar_escola` como regra escolar efetiva.

| Requisitos | Capacidade ou condição mínima | Escopo adicional |
| --- | --- | --- |
| REQ-AUTH-001/002 | autenticação, usuário ativo e capacidade da ação | organização e/ou escola do recurso |
| REQ-ADM-001 a REQ-ADM-004 | capacidades administrativas correspondentes | organização ou escola do papel |
| REQ-OCC-001 a REQ-OCC-005 | `ocorrencias.criar` para abertura; autoria ou capacidade de visualizar para leitura/anexo | escola da ocorrência e autoria quando aplicável |
| REQ-TRI-001 a REQ-TRI-004 | capacidades de triar, solicitar informação, classificar e confirmar prioridade | escola da ocorrência |
| REQ-FWD-001/002 | capacidade de encaminhar; destinatários definidos pela capacidade de criar/atribuir OS | escola da ocorrência |
| REQ-OS-001 | capacidade de criar OS | ocorrência `ENCAMINHADA` na escola autorizada |
| REQ-OS-002/003/004 | `ordens_servico.visualizar`, `ordens_servico.executar`, atribuição e capacidade específica da ação | mesma organização e escola; usuário ativo |
| REQ-OS-005/006 | capacidade de aprovar/rejeitar e segregação obrigatória | mesma escola; aprovador diferente do criador |
| REQ-OS-007/008 | gestor/administrador autorizador e aprovador elegível | mesma escola; diferente do criador; urgência confirmada |
| REQ-OS-009/010 | gestor cria/atribui; responsável interno executa | fornecedor não recebe identidade nem autorização |
| REQ-RES-001 | capacidade de concluir OS e atribuição técnica | escola da OS |
| REQ-RES-002/003 | capacidade de encerrar/reabrir ocorrência | escola da ocorrência |
| REQ-RES-004/005 | autoria para solicitar; capacidade de reabrir para decidir | pedido não altera o estado por si só |
| REQ-HIST-001 a REQ-HIST-003 | nenhuma capacidade permite update/delete de evento | correção somente por novo evento |
| REQ-NOT-001 a REQ-NOT-005 | elegibilidade pela capacidade relacionada ao evento | usuário ativo, escola e deduplicação |
| REQ-KPI-001 | `indicadores.visualizar` reservada | módulo ainda fora do marco |

## 2. Identidade, acesso e administração

| Requisito | Fluxo | Regra aprovada | Código ou banco atual | Teste atual | Situação | Gap / tarefa |
| --- | --- | --- | --- | --- | --- | --- |
| REQ-AUTH-001 | FLOW-AUTH-001 | Usuário deve autenticar e estar ativo | `LoginController`, `LoginRequest` | `LoginTest` | Operacional | Manter regressão |
| REQ-AUTH-002 | FLOW-AUTH-002 | Toda ação exige capacidade e escopo válidos | `AccessCatalog`, `RoleAssignment`, policies | `OrganizationAccessTest`, `Admin/UserManagementTest` e testes de workflow | Operacional | TSK-SEC-005 concluída; manter regressão |
| REQ-ADM-001 | FLOW-ADM-001/002 | Rede e escola respeitam isolamento organizacional | `OrganizationController`, `SchoolController`, policies | `OrganizationAccessTest`, `Admin/SchoolManagementTest` | Operacional | Manter regressão de isolamento |
| REQ-ADM-002 | FLOW-ADM-003 | Administrador atribui perfis padrão a usuários | `UserController`, `AccessCatalog` | `Admin/UserManagementTest` | Operacional com lacuna de auditoria | Histórico de vínculos fica após o marco de OS |
| REQ-ADM-003 | FLOW-ADM-004 | V1 permite atribuir, mas não personalizar perfis padrão | `AccessCatalog`, `UserController` e `UserRequest` limitam seleção, validação e gravação aos seis perfis padrão | `Admin/UserManagementTest` cobre tela, ID manipulado e ausência de rotas/campos de personalização | Operacional | TSK-SEC-006 concluída; manter regressão |
| REQ-ADM-004 | FLOW-ADM-005/007 | Ambientes e categorias são limitados à escola | Controllers, policies e models correspondentes | Testes de administração e categorias | Operacional | Manter regressão |
| REQ-ADM-005 | FLOW-ADM-006 | Equipamentos e patrimônio não fazem parte da V1 | Não existe domínio correspondente | Não aplicável | Fora da V1 | Não iniciar sem nova aprovação |
| REQ-ADM-006 | FLOW-ADM-008 | Consulta global de histórico e relatórios | Históricos existem por registro; não há consulta global | Não existe | Pendente fora do marco atual | Planejar somente depois da estabilização |

## 3. Abertura e triagem de ocorrência

| Requisito | Fluxo | Regra aprovada | Código ou banco atual | Teste atual | Situação | Gap / tarefa |
| --- | --- | --- | --- | --- | --- | --- |
| REQ-OCC-001 | FLOW-OCC-001/002 | Criar ocorrência na escola com ambiente e categoria válidos | `OccurrenceController`, `OccurrenceRequest` | `OccurrenceWorkflowTest` | Operacional | Manter regressão |
| REQ-OCC-002 | FLOW-OCC-003 | Gerar protocolo sequencial por escola e ano | `OccurrenceSequence` e transação de criação | `OccurrenceWorkflowTest` | Operacional | Manter teste de concorrência quando necessário |
| REQ-OCC-003 | FLOW-OCC-004 | Abrir em `ABERTA` e registrar evento de criação | `Occurrence`, `OccurrenceHistory` append-only e FK restritiva | `OccurrenceWorkflowTest` e `HistoryImmutabilityTest` | Operacional | TSK-HIST-001/002/003/004 concluídas |
| REQ-OCC-004 | FLOW-OCC-001 | Aceitar até 5 imagens/PDF privados, 10 MB por arquivo, na abertura | `OccurrenceRequest`, `OccurrenceController` e relação polimórfica armazenam JPEG/PNG/PDF no disco privado `local`; download autenticado reaplica a política e oculta anexos negados com 404 | `OccurrenceWorkflowTest` cobre limites 5/6, formatos, tamanho, autor, gestor da escola, outro solicitante, outra escola e organização; `ServiceOrderWorkflowTest` preserva isolamento das evidências de OS | Código testado | TSK-ATT-001/002/003/004 concluídas |
| REQ-OCC-005 | FLOW-OCC-001 | Autor pode adicionar evidências em `ABERTA`, `EM_TRIAGEM` e `AGUARDANDO_INFORMACOES`, até 20 arquivos no total | `OccurrencePolicy::addEvidence()` restringe ator e estado; `PrivateAttachmentController::storeForOccurrence()` revalida dentro de bloqueio transacional, armazena no disco privado e registra `attachment_added` sem mudar o estado | `OccurrenceWorkflowTest` cobre os três estados, autoria exclusiva inclusive contra gestor/admin, formato, 10 MB e fronteira 19/20/21 | Código testado | TSK-ATT-005 concluída |
| REQ-TRI-001 | FLOW-TRI-001 | Usuário elegível inicia triagem sem posse exclusiva | `Occurrence::concurrencyToken()`, transação e `lockForUpdate()` no `OccurrenceTriageController` | `OccurrenceWorkflowTest` prova rejeição da gravação obsoleta sem sobrescrita | Operacional | TSK-TRI-001/002 concluídas |
| REQ-TRI-002 | FLOW-TRI-002 | Gestor solicita informação e autor responde | `OccurrenceTriageController` e estados correspondentes | `OccurrenceWorkflowTest` | Operacional sem notificações | TSK-NOT-003 |
| REQ-TRI-003 | FLOW-TRI-003/004 | Gestor marca duplicada ou não procede com validação e histórico | `OccurrenceTriageController` | `OccurrenceWorkflowTest` | Operacional | Manter regressão |
| REQ-TRI-004 | FLOW-TRI-005/006 | Sistema sugere prioridade e gestor confirma | `Occurrence::suggestedPriority()` e controller | `OccurrenceWorkflowTest` | Operacional | Formalizar a matriz de impacto/urgência antes de alterá-la |
| REQ-FWD-001 | FLOW-FWD-001/002 | Ocorrência confirmada é encaminhada para criação de OS | `forwarded_destination` e estado `ENCAMINHADA` | `OccurrenceWorkflowTest` | Operacional com modelo textual | A atribuição estruturada ocorre na OS; não duplicar técnico na ocorrência |
| REQ-FWD-002 | FLOW-FWD-003 | Encaminhamento notifica criadores/atribuidores de OS elegíveis | Não existe | Não existe | Pendente | TSK-NOT-004 |

## 4. Ordem de Serviço e execução

| Requisito | Fluxo | Regra aprovada | Código ou banco atual | Teste atual | Situação | Gap / tarefa |
| --- | --- | --- | --- | --- | --- | --- |
| REQ-OS-001 | FLOW-OS-001 | Toda manutenção é executada por uma OS | `ServiceOrderController` e rotas de OS | `ServiceOrderWorkflowTest` | Operacional no banco principal deste computador | TSK-DB-001/002/003/004 e DB-REV-001/002 concluídos |
| REQ-OS-002 | FLOW-OS-002 | Responsável e membros devem estar ativos, na escola e possuir `visualizar` + `executar` | `User::isEligibleForServiceOrderAssignment()`, filtro da tela e `ServiceOrderRequest` | `ServiceOrderWorkflowTest` cobre casos positivos e negativos | Operacional | TSK-SEC-001/002 e TSK-DB-004 concluídas |
| REQ-OS-003 | FLOW-OS-003/004 | Registrar diagnóstico, solução, atualizações, material, tempo, custo e evidência privada | `ServiceOrderEntryController`, `ServiceOrder` e `BrazilianCurrency` usam strings decimais e Brick Math | `ServiceOrderWorkflowTest` cobre arredondamento, soma, limites e renderização | Código testado | TSK-OS-006 concluída |
| REQ-OS-004 | FLOW-OS-005 | Permitir aguardar material, pausar e retomar | `ServiceOrderWorkflowController` | `ServiceOrderWorkflowTest` | Operacional | TSK-DB-004 concluída |
| REQ-OS-005 | FLOW-OS-007 | OS sujeita a aprovação não pode ser aprovada/rejeitada pelo criador | `ServiceOrderWorkflowController::requireIndependentDecisionMaker()` | `ServiceOrderWorkflowTest` cobre perfis acumulados, plataforma, outra escola e segundo aprovador | Operacional | TSK-APR-001/002 e TSK-DB-004 concluídas |
| REQ-OS-006 | FLOW-OS-007 | Estado ativo de criação é `APROVADA` ou `AGUARDANDO_APROVACAO`; `PLANEJADA` não integra a V1 | `ServiceOrderController::store()` define o estado; catálogo e migration não contêm `PLANEJADA` | `ServiceOrderWorkflowTest` cobre limite, custo acima do limite e todas as condições especiais | Operacional | TSK-OS-001/002 e TSK-DB-004 concluídas |
| REQ-OS-007 | FLOW-OS-008 | Emergência exige urgência, autorizador diferente do criador, motivo e histórico | `ServiceOrderWorkflowController::emergency()` e campos de autorização emergencial | `ServiceOrderWorkflowTest` cobre autoautorização, escola, motivo e executor | Operacional sem notificações | TSK-EMG-001/002/003/007 e TSK-DB-004 concluídas |
| REQ-OS-008 | FLOW-OS-008 | Ratificar até fim do próximo dia útil; vencimento bloqueia conclusão, não execução | `EmergencyRatificationDeadline`, ratificação, bloqueio de conclusão e `EmergencyRatificationAlertService` | `ServiceOrderWorkflowTest` cobre prazo, atraso e continuidade; `EmergencyRatificationAlertTest` cobre alertas recorrentes | Código testado; ativação das notificações no principal pendente | TSK-EMG-004/005/006/007 concluídas |
| REQ-OS-009 | FLOW-OS-006 | Serviço externo continua como OS, com responsável interno, fornecedor e descrição | Campos estruturados na OS; validações exigem responsável elegível; custos e anexos permanecem relacionados à OS | `ServiceOrderWorkflowTest` cobre dados, elegibilidade e fluxo completo com aprovação, atualização, custo, PDF, imagem, diagnóstico, conclusão e resolução | Código testado; migration principal pendente | TSK-EXT-001/002/004 concluídas |
| REQ-OS-010 | FLOW-OS-006 | Fornecedor externo não possui conta no SIGME | Dados do fornecedor são campos da OS, sem identidade ou vínculo com `users` | `ServiceOrderWorkflowTest` prova contagens invariantes de usuários, vínculos, papéis e permissões, falha de login e acesso autenticado obrigatório | Código testado | TSK-EXT-003 concluída |

## 5. Resolução, reabertura e histórico

| Requisito | Fluxo | Regra aprovada | Código ou banco atual | Teste atual | Situação | Gap / tarefa |
| --- | --- | --- | --- | --- | --- | --- |
| REQ-RES-001 | FLOW-RES-001 | Ocorrência só resolve após ao menos uma OS concluída e todas as OS válidas concluídas | `Occurrence::canBeResolvedFromServiceOrders()` é usada na conclusão e no encerramento | `ServiceOrderWorkflowTest` cobre ausência de concluída, conclusão parcial e total | Código testado | TSK-OS-003/004 concluídas |
| REQ-RES-002 | FLOW-RES-002 | Gestor encerra ocorrência resolvida | `OccurrenceResolutionController` | `ServiceOrderWorkflowTest` | Código testado | Validar após migration principal |
| REQ-RES-003 | FLOW-RES-002 | Reabertura é evento e retorna a `EM_TRIAGEM`, sem estado `REABERTA` | Controller retorna a `EM_TRIAGEM` | `ServiceOrderWorkflowTest` | Código testado | Atualizar representação visual quando houver novo diagrama |
| REQ-RES-004 | FLOW-RES-002 | Solicitante pede reabertura; somente gestor muda o estado | `OccurrenceResolutionController::requestReopening()` registra `reopen_requested` sem transição; policy exige autor e `ENCERRADA`; gestor mantém a ação `reopen` separada | `ServiceOrderWorkflowTest` cobre autoria exclusiva, estado, motivo, histórico, destinatários, deduplicação e decisão posterior | Código testado | TSK-REO-001/002/003 concluídas |
| REQ-RES-005 | FLOW-RES-002 | Nova manutenção após reabertura cria nova OS e preserva a anterior | Reabertura retorna à triagem e permite novo encaminhamento e nova OS | `ServiceOrderWorkflowTest` cobre o ciclo completo com duas OS e dois eventos de resolução | Código testado | TSK-OS-004 e TSK-REO-001/003 concluídas |
| REQ-HIST-001 | FLOW-HIST-001 | Histórico é append-only na aplicação | `OccurrenceHistory` e `ServiceOrderHistory` bloqueiam update/delete | `HistoryImmutabilityTest` | Código testado | TSK-HIST-001/002 concluídas |
| REQ-HIST-002 | FLOW-HIST-001 | FKs não podem apagar histórico por cascata | `2026_08_31_152841_restrict_history_parent_deletions.php` | Teste de integridade, ensaio e validação no principal | Operacional no banco principal deste computador | TSK-HIST-003/004 e TSK-DB-004 concluídas |
| REQ-HIST-003 | FLOW-HIST-001 | Correção gera evento compensatório; sem triggers e sem expurgo automático na V1 | Não existe fluxo genérico de compensação | Não existe | Pendente não bloqueante | Especificar quando houver caso de correção |

## 6. Notificações

| Requisito | Fluxo | Regra aprovada | Código ou banco atual | Teste atual | Situação | Gap / tarefa |
| --- | --- | --- | --- | --- | --- | --- |
| REQ-NOT-001 | FLOW-OCC-005 e eventos subsequentes | Todo destinatário elegível recebe notificação interna | `InternalNotification`, caixa autenticada e `InternalNotificationService`; eventos de informação, encaminhamento, atribuição, aprovação, rejeição, pausa, material, resolução, encerramento, pedido de reabertura e reabertura integrados; `InternalNotificationController::open()` reaplica a política atual do recurso | `InternalNotificationTest` cobre caixa, leitura e isolamento; testes de ocorrência e OS cobrem destinatários, deduplicação e reautorização | Eventos e links seguros do fluxo principal implementados e testados; ativação principal pendente | TSK-NOT-001/003/004/005/006 e TSK-REO-002 concluídas |
| REQ-NOT-002 | FLOW-OCC-005/FWD-003 | Destinatários por capacidade são ativos, da escola e deduplicados por usuário/evento | `NotificationRecipientResolver` respeita atividade, organização, escola e funções acumuladas; chave única `(user_id, event_key)` | `InternalNotificationTest` cobre escopos, inativos, múltiplos papéis e reprocessamento | Código testado; migration pendente no principal | TSK-NOT-002 concluída |
| REQ-NOT-003 | Eventos aprovados em `12_STATE_MACHINE.md` | E-mail é opcional por usuário e começa desativado | `users.email_notifications_enabled` com padrão `false` e alteração exclusiva pelo próprio usuário na caixa de notificações | `InternalNotificationTest` cobre padrão, ativação, desativação, isolamento entre usuários e preservação da notificação interna | Código testado; migration pendente no principal | TSK-NOT-007/008 concluídas |
| REQ-NOT-004 | Eventos aprovados em `12_STATE_MACHINE.md` | E-mail contém somente protocolo, evento, status e link autenticado | `InternalNotificationMail` é enfileirado após commit somente para usuário opt-in e usa o link autenticado `notifications.open`; título, corpo e URL armazenada não são repassados | `InternalNotificationTest` verifica destinatário, campos permitidos, link seguro, ausência de título/descrição e nenhum envio para opt-out | Código testado; migrations de notificações pendentes no principal | TSK-NOT-009 concluída |
| REQ-NOT-005 | FLOW-OS-008 | Ratificação vencida alerta no vencimento e uma vez por dia útil | `EmergencyRatificationAlertService` seleciona OS operacionais vencidas; comando horário registrado com `withoutOverlapping`; chave contém OS e data | `EmergencyRatificationAlertTest` cobre elegibilidade, escopo, criador excluído, repetição, fim de semana, ratificação, cancelamento e deduplicação | Código testado; migration de notificações pendente no principal | TSK-EMG-006 concluída |

## 7. Indicadores e requisitos adiados

| Requisito | Fluxo | Regra aprovada | Código ou banco atual | Teste atual | Situação | Gap / tarefa |
| --- | --- | --- | --- | --- | --- | --- |
| REQ-KPI-001 | FLOW-KPI-001/002 | Indicadores precisam de definições aprovadas antes da implementação | Há apenas capacidade reservada | Não existe | Pendente fora do marco | Não implementar no marco de estabilização |
| REQ-SCOPE-001 | FLOW-ADM-006 | Equipamento/patrimônio fica fora da V1 | Ausente | Não aplicável | Fora da V1 | Requer nova decisão de escopo |
| REQ-SCOPE-002 | Administração | Perfis personalizados ficam fora da V1 | Não há interface de personalização; backend aceita somente perfis padrão do catálogo | `Admin/UserManagementTest` | Operacional | TSK-SEC-006 concluída |

## 8. Decisão concluída

| ID | Decisão | Situação | Impacto |
| --- | --- | --- | --- |
| DEC-ATT-001 | Máximo de 20 arquivos por ocorrência, mantendo até 5 na abertura e 10 MB por arquivo | Aprovada em 31/08/2026 | Desbloqueia a especificação de TSK-ATT-005 |

## 9. Critério para atualizar a situação

Um requisito só pode mudar para **Operacional** quando houver, proporcionalmente ao risco:

1. regra aprovada e referenciada;
2. implementação revisada;
3. teste automatizado positivo e negativo;
4. migration aplicada e validada quando houver alteração de banco;
5. autorização e isolamento entre escolas testados;
6. validação manual do fluxo afetado;
7. atualização desta matriz com evidência real, sem usar documentação como prova de execução.
