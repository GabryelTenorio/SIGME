# Plano de Tarefas — Estabilização da V1 do SIGME

Atualizado em 03/09/2026.

Status: plano executável aprovado para o próximo marco. Nenhuma tarefa deste documento está implicitamente concluída por existir no código ou na documentação.

## 1. Objetivo do marco

Tornar o fluxo existente de ocorrência e Ordem de Serviço seguro e operacional no banco principal antes de ampliar o produto.

Ordem obrigatória:

1. caracterizar os gaps críticos com testes;
2. corrigir autorização, segregação, estados, histórico e emergência;
3. ensaiar as migrations de OS em cópia segura;
4. aplicar e validar no banco principal;
5. implementar notificações, anexos de ocorrência e serviço externo;
6. executar validação final completa.

## 2. Definition of Done do marco

O marco só termina quando:

- nenhum usuário inelegível pode ser responsável ou membro de OS;
- o criador não aprova, rejeita nem autoriza emergencialmente a própria OS;
- gravações concorrentes de triagem não se sobrescrevem silenciosamente;
- histórico não pode ser alterado, excluído ou apagado por cascata nos fluxos suportados;
- início emergencial possui autorizador, prazo e ratificação; atraso bloqueia apenas a conclusão;
- `PLANEJADA` não participa do fluxo ativo;
- migrations de OS foram ensaiadas com recuperação comprovada e depois aplicadas ao banco principal;
- suíte completa, build, verificação HTTP e fluxos manuais prioritários passam no ambiente oficial;
- a matriz `docs/14_TRACEABILITY.md` reflete evidências atuais.

## 3. Fase 0 — Decisão de anexos concluída

### TSK-DEC-001 — Limite total de evidências

- Status: concluída em 31/08/2026.
- Decisão: no máximo 20 arquivos por ocorrência.
- Mantidos: no máximo 5 arquivos na abertura; 10 MB por imagem/PDF.
- Evidência: `DEC-ATT-001` em `docs/14_TRACEABILITY.md`, com regra registrada em `12_STATE_MACHINE.md` e `13_PERMISSION_MATRIX.md`.

## 4. Fase 1 — Testes de caracterização críticos

### TSK-SEC-001 — Caracterizar elegibilidade de responsável e equipe

- Status: concluída em 31/08/2026.
- Arquivo principal: `tests/Feature/ServiceOrderWorkflowTest.php`.
- Cobrir usuário inativo, outra escola, outra organização, solicitante sem capacidades técnicas e ID manipulado.
- Cobrir técnico elegível com `ordens_servico.visualizar` e `ordens_servico.executar`.
- Aceite executado: o novo teste reproduziu a aceitação indevida antes da correção e passou depois dela.

### TSK-APR-001 — Caracterizar segregação de aprovação

- Status: concluída em 31/08/2026.
- Cobrir aprovação e rejeição pelo criador, inclusive com perfis acumulados e administração de plataforma.
- Cobrir outro aprovador autorizado e aprovador de outra escola.
- Aceite executado: o teste reproduziu a autoaprovação antes da correção e passou depois; o atalho de administração de plataforma não contorna a regra.

### TSK-TRI-001 — Caracterizar concorrência de triagem

- Status: concluída em 31/08/2026.
- Simular duas gravações baseadas na mesma versão da ocorrência.
- Aceite executado: a segunda gravação obsoleta é rejeitada, a primeira permanece e somente um evento é registrado.

### TSK-HIST-001 — Caracterizar imutabilidade

- Status: concluída em 01/09/2026.
- Cobrir tentativa de update/delete dos históricos de ocorrência e OS pela aplicação.
- Cobrir tentativa de exclusão do registro-pai que apagaria histórico por cascata.
- Aceite executado: os testes reproduziram ambas as falhas antes da correção e preservam os eventos depois dela.

### TSK-EMG-001 — Caracterizar emergência e ratificação

- Status: concluída em 01/09/2026.
- Cobrir autoautorização, autorizador de outra escola, motivo obrigatório e autorizador válido.
- Cobrir prazo até o fim do próximo dia útil, atraso, continuidade da execução e bloqueio da conclusão.
- Aceite executado: testes determinísticos com relógio controlado passaram para autorização, ratificação e vencimento.

## 5. Fase 2 — Correções críticas de domínio

### TSK-SEC-002 — Impor elegibilidade técnica no backend e na interface

- Status: concluída em 31/08/2026.
- Ajustar request/serviço de domínio da OS para exigir usuário ativo, organização, escola, visualização e execução.
- Filtrar a interface para mostrar apenas elegíveis, repetindo a validação no servidor.
- Dependência: TSK-SEC-001.

### TSK-APR-002 — Impor segregação fora do atalho de policy

- Status: concluída em 31/08/2026.
- Bloquear aprovação/rejeição pelo criador em regra de domínio não contornável por `before()`.
- Registrar decisão, ator, data e motivo de rejeição.
- Dependência: TSK-APR-001.

### TSK-TRI-002 — Implementar concorrência otimista

- Status: concluída em 31/08/2026.
- Adicionar versão ou precondição equivalente às alterações de triagem.
- Exibir instrução de recarregamento quando o registro estiver obsoleto.
- Dependência: TSK-TRI-001.

### TSK-HIST-002 — Tornar models de histórico append-only

- Status: concluída em 01/09/2026.
- Impedir update/delete por fluxos normais da aplicação.
- Correções futuras devem adicionar evento compensatório.
- Dependência: TSK-HIST-001.

### TSK-HIST-003 — Restringir exclusões no banco

- Status: concluída em código, banco de teste e banco principal em 03/09/2026.
- Criar migration corretiva para substituir cascatas incompatíveis por restrições preservadoras.
- Não usar trigger e não implementar expurgo automático.
- Dependência: TSK-HIST-001.

### TSK-HIST-004 — Validar integridade de histórico no banco de teste

- Status: concluída em 01/09/2026.
- Provar que as FKs impedem perda de eventos.
- Aceite executado: rollback, reaplicação e testes passaram exclusivamente em `sigme_testing`.

### TSK-OS-001 — Remover `PLANEJADA` do fluxo ativo

- Status: concluída em código, banco de teste e banco principal em 03/09/2026.
- Remover constante, opções e transições ativas sem reescrever registros históricos.
- O ensaio encontrou zero registros nesse estado; nenhuma migration de dados foi necessária.

### TSK-OS-002 — Provar estados iniciais válidos

- Status: concluída em 01/09/2026.
- Testar criação direta em `APROVADA` ou `AGUARDANDO_APROVACAO` conforme os gatilhos.
- Aceite executado: custo no limite cria `APROVADA`; custo acima do limite e cada condição especial criam `AGUARDANDO_APROVACAO`; nenhum caminho testado cria `PLANEJADA`.

### TSK-OS-003 — Garantir requisito mínimo para resolução

- Status: concluída em código e testes em 01/09/2026.
- Exigir ao menos uma OS concluída antes de resolver a ocorrência.
- Preservar a regra de que canceladas/rejeitadas não contam como conclusão.
- Aceite executado: encerramento sem OS concluída é bloqueado; resolução exige ao menos uma concluída e nenhuma OS válida pendente.

### TSK-OS-004 — Testar múltiplas OS e reabertura

- Status: concluída em 01/09/2026.
- Cobrir conclusão parcial, conclusão de todas as OS válidas e nova OS após reabertura.
- Aceite executado: a ocorrência foi reaberta, reencaminhada, recebeu nova OS e voltou a ser resolvida mantendo a OS e o histórico anteriores.

### TSK-OS-006 — Revisar decimais de ponta a ponta

- Status: concluída em código e testes em 01/09/2026.
- Confirmar cálculo e apresentação sem conversões monetárias inseguras para `float`.
- Aceite executado: multiplicações fracionárias usam `HALF_UP`, limites impedem estouro de `DECIMAL(12,2)`, somas permanecem exatas e a renderização brasileira opera sobre strings decimais.

## 6. Fase 3 — Emergência completa

### TSK-EMG-002 — Registrar autorizador emergencial separado

- Status: concluída em código, banco de teste e banco principal em 03/09/2026.
- Exigir gestor/administrador ativo da mesma escola e diferente do criador.
- Exigir motivo e manter executor tecnicamente elegível.

### TSK-EMG-003 — Auditar início emergencial

- Status: concluída em código e testes em 01/09/2026.
- Registrar OS, criador, autorizador, motivo, data e ratificação pendente.

### TSK-EMG-004 — Calcular prazo em dias úteis

- Status: concluída em 01/09/2026 considerando fins de semana; feriados dependem de calendário configurado.
- Definir serviço testável para o fim do próximo dia útil.
- Feriados além de fins de semana exigem calendário configurado antes de serem considerados.

### TSK-EMG-005 — Implementar ratificação

- Status: concluída em código e testes em 01/09/2026.
- Ratificador deve ser aprovador elegível, diferente do criador.
- Registrar resultado e data no histórico.

### TSK-EMG-006 — Tratar vencimento

- Status: concluída em código e banco de teste em 08/09/2026; comando horário identifica ratificações vencidas, notifica gestores e aprovadores elegíveis exceto o criador e deduplica por destinatário, OS e data de alerta. Fins de semana não geram repetição e o próximo dia útil cria um novo alerta.
- Permitir registros técnicos e continuidade do trabalho.
- Bloquear conclusão enquanto não ratificada.
- Preparar evento de alerta no vencimento e em cada dia útil posterior.

### TSK-EMG-007 — Validar transições emergenciais

- Status: concluída em 01/09/2026.
- Executar testes positivos, negativos, de prazo e de isolamento escolar.

## 7. Fase 4 — Ensaio e ativação das migrations de OS

### TSK-DB-001 — Inventariar e revisar migrations pendentes

- Status: concluída em 01/09/2026; `DB-REV-001/002` corrigidos e validados em `sigme_testing`.
- Revisar as oito migrations `2026_08_27_170109` a `2026_08_27_170146` e migrations corretivas criadas nas fases anteriores.
- Confirmar FKs, índices, nulabilidade, estados e estratégia de rollback.
- Evidência: `docs/DB_MIGRATION_REVIEW_2026-09-01.md`.

### TSK-DB-002 — Criar backup e cópia de ensaio

- Status: concluída em 03/09/2026; backup verificado e restaurado em cópia descartável isolada.
- Preservar `.env`, banco principal, uploads e backups existentes.
- Nunca usar remoção de volumes.
- Registrar origem, destino, data e comando de restauração validado.
- Evidência: `/home/e-not-094/backups/sigme/20260903-084331/RESTORE_AND_VALIDATION.md`.

### TSK-DB-003 — Ensaiar migrate, rollback e restauração

- Status: concluída em 03/09/2026 na cópia descartável `sigme_rehearsal_20260903_084331`.
- Executar em cópia descartável e isolada, nunca em `sigme` nem confundindo com `sigme_testing`.
- Validar schema, dados anteriores, rollback suportado e restauração do backup.
- Aceite: recuperação comprovada antes de tocar o banco principal.
- Evidência: `/home/e-not-094/backups/sigme/20260903-084331/RESTORE_AND_VALIDATION.md`.

### TSK-DB-004 — Aplicar no banco principal

- Status: concluída em 03/09/2026 no banco principal `sigme` deste computador, após autorização explícita.
- Pré-condições: fases 1 a 3 concluídas, ensaio aprovado e backup verificado.
- Aplicar pelo Sail no ambiente oficial.
- Registrar `migrate:status` posterior e smoke test das rotas de OS.
- Evidência: `/home/e-not-094/backups/sigme/20260903-091925-pre-db004/DB004_ACTIVATION.md`.

## 8. Fase 5 — Funcionalidades aprovadas após a estabilização

### Notificações

- TSK-NOT-001: concluída em 03/09/2026; armazenamento, caixa interna e leitura individual/em lote implementados e testados em `sigme_testing`. A migration permanece pendente no banco principal até autorização de implantação.
- TSK-NOT-002: concluída em 03/09/2026; destinatários ativos são resolvidos por capacidade e escopo de escola/organização, com deduplicação por usuário/evento protegida também por índice único.
- TSK-NOT-003: concluída em 03/09/2026; solicitação notifica o solicitante e fornecimento notifica todos os responsáveis ativos pela triagem da escola, dentro da mesma transação do histórico.
- TSK-NOT-004: concluída em 03/09/2026; encaminhamento notifica quem pode criar/atribuir OS, e criação ou alteração de equipe notifica responsável e membros atuais, com histórico e deduplicação.
- TSK-NOT-005: concluída em 03/09/2026; aprovação pendente, aprovação, rejeição, pausa, espera de material, resolução, encerramento e reabertura notificam os destinatários aprovados e preservam deduplicação por evento.
- TSK-NOT-006: concluída em 03/09/2026; o acesso usa um resolvedor interno, ignora a URL armazenada, confirma o dono da notificação e reaplica a política atual da ocorrência ou OS antes de redirecionar.
- TSK-NOT-007: concluída em 03/09/2026; preferência individual de e-mail adicionada à própria caixa de notificações, com valor padrão desativado e sem permitir alteração de outro usuário. A migration permanece pendente no banco principal até autorização de implantação.
- TSK-NOT-008: concluída em 03/09/2026; teste prova que a preferência de e-mail desativada não impede a criação da notificação interna.
- TSK-NOT-009: concluída em 03/09/2026; usuários com preferência ativa recebem e-mail enfileirado após commit contendo somente protocolo, tipo do evento, status e link autenticado da notificação. Título, descrição e demais dados internos não entram na mensagem.

### Anexos de ocorrência

- TSK-ATT-001: concluída em 04/09/2026; anexos opcionais da abertura usam a relação polimórfica e o disco privado `local` já existentes, são exibidos por rota autenticada e nunca expõem o caminho físico.
- TSK-ATT-002: concluída em 04/09/2026; validação aceita somente JPEG, PNG ou PDF de até 10 MB por arquivo e rejeita tipo ou tamanho inválido antes de criar a ocorrência.
- TSK-ATT-003: concluída em 04/09/2026; a abertura aceita até 5 anexos e rejeita o sexto antes de criar nova ocorrência ou arquivo.
- TSK-ATT-004: concluída em 04/09/2026; download exige autenticação e acesso atual à ocorrência, permite autor e gestor da mesma escola e responde 404 para outro solicitante, outra escola ou organização.
- TSK-ATT-005: concluída em 04/09/2026; somente o autor com capacidade atual pode adicionar JPEG, PNG ou PDF de até 10 MB em `ABERTA`, `EM_TRIAGEM` ou `AGUARDANDO_INFORMACOES`; a inclusão preserva o estado, registra histórico e usa bloqueio transacional para limitar a ocorrência a 20 arquivos no total.

### Serviço externo

- TSK-EXT-001: concluída em código e banco de teste em 04/09/2026; a OS externa armazena nome/empresa e descrição obrigatórios, além de contato e CNPJ/CPF opcionais.
- TSK-EXT-002: concluída em código e banco de teste em 04/09/2026; criação e alteração de equipe exigem responsável principal ativo, da organização e escola, com capacidades de visualizar e executar OS.
- TSK-EXT-003: concluída em 04/09/2026; teste prova que criar OS externa não cria usuário, vínculo escolar, papel ou permissão, mantém o responsável como usuário interno e não permite autenticação ou acesso do fornecedor.
- TSK-EXT-004: concluída em 04/09/2026; teste ponta a ponta prova aprovação independente, execução pelo responsável interno, atualização, custo externo, PDF e imagem privados, diagnóstico, conclusão e resolução vinculados à mesma OS.

### Reabertura solicitada

- TSK-REO-001: concluída em 04/09/2026; somente o autor com acesso atual pode registrar motivo e pedir reabertura de ocorrência `ENCERRADA`, gerando o evento `reopen_requested`.
- TSK-REO-002: concluída em 04/09/2026; todos os usuários ativos e elegíveis para reabrir na escola recebem uma notificação por evento, sem duplicidade mesmo com papéis acumulados.
- TSK-REO-003: concluída em 04/09/2026; o pedido preserva `ENCERRADA` e somente a ação posterior de um gestor elegível muda o estado para `EM_TRIAGEM`.
- TSK-REO-004: concluída em 01/09/2026; nova manutenção após reabertura recebe nova OS e preserva as anteriores.

### Proteção do escopo

- TSK-SEC-005: concluída em código e banco de teste em 04/09/2026; `ocorrencias.visualizar_todas` foi removida do catálogo e do perfil gestor, preservando `ocorrencias.visualizar_escola` como capacidade escolar efetiva. Migration reversível remove o registro e suas concessões históricas sem adicionar acesso; aplicação no banco principal aguarda a janela controlada das migrations pendentes.
- TSK-SEC-006: concluída em 04/09/2026; tela, validação e gravação aceitam somente os seis perfis padrão provisionados, e testes comprovam a ausência de rotas ou campos para personalizar perfis e permissões na V1.

## 9. Fase 6 — Validação final

### TSK-VAL-001 — Qualidade automatizada

- Status: concluída para o baseline de 08/09/2026 com Pint aprovado, suíte completa de 112 testes e 778 asserções e build frontend aprovado.
- Executar formatter apenas nos arquivos PHP alterados.
- Executar testes focados após cada correção e depois a suíte completa.
- Executar build frontend com as versões travadas do projeto.

### TSK-VAL-002 — Banco e runtime

- Confirmar containers saudáveis, filas e agendador quando usados.
- Confirmar migrations sem pendências inesperadas.
- Validar resposta HTTP real da aplicação.

### TSK-VAL-003 — Fluxos manuais prioritários

- Abertura, triagem, informação, encaminhamento, criação e atribuição de OS.
- Aprovação segregada, execução, pausa/material, conclusão, resolução e encerramento.
- Emergência com ratificação em dia e vencida.
- Reabertura e criação de nova OS.
- Acesso autorizado e negado a anexos e notificações.
- Verificar desktop e viewport móvel nos fluxos afetados.

### TSK-VAL-004 — Atualizar evidências

- Atualizar `PROJECT_STATE.md`, `GAP_AUDIT_OCCURRENCE_FLOW.md` e `14_TRACEABILITY.md` somente com resultados realmente executados.
- Não declarar o módulo operacional com base apenas em testes isolados.

## 10. Fora deste marco

Não iniciar sem nova aprovação:

- equipamentos e patrimônio;
- indicadores e relatórios consolidados;
- perfis personalizados;
- inventário de materiais;
- acesso de fornecedor externo;
- política definitiva de retenção e expurgo;
- reescrita geral da aplicação ou troca da stack aprovada.

## 11. Próxima tarefa executável

Próxima tarefa: `TSK-VAL-002` — revisar as quatro migrations pendentes, preparar a implantação controlada no banco principal e validar containers, fila, agendador e respostas HTTP antes dos fluxos manuais finais.
