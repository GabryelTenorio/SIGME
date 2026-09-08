# Revisão e ativação das migrations de Ordens de Serviço

Data da revisão: 01/09/2026.

Escopo: `TSK-DB-001/002/003/004` e resolução de `DB-REV-001/002`. A ativação no banco principal foi autorizada e executada em 03/09/2026.

## 1. Estado confirmado

- Banco principal identificado: `sigme`.
- `service_orders` existe no banco principal após a ativação.
- As dez migrations foram aplicadas; o banco principal possui 28 migrations executadas e zero pendências.
- Dados existentes no preflight: 2 organizações, 4 escolas, 4 usuários, 1 ocorrência e 4 eventos de histórico de ocorrência.
- A FK `occurrence_histories.occurrence_id` está em `ON DELETE RESTRICT` após a migration corretiva.
- O SQL de subida foi validado com `migrate --pretend` no banco principal, sem execução.
- As dez migrations foram revertidas e reaplicadas com sucesso exclusivamente em `sigme_testing` após as correções.
- O schema reconstruído em `sigme_testing` não contém `external_cost` ou `other_cost` e confirma `ON DELETE RESTRICT` na FK do histórico de OS.
- A suíte completa passou com 84 testes e 483 asserções; os 25 testes focados de OS e histórico passaram com 231 asserções.

## 2. Inventário e decisão por migration

| Ordem | Migration | Objeto principal | Revisão | Decisão |
| --- | --- | --- | --- | --- |
| 1 | `2026_08_27_170109_create_service_orders_table` | `service_orders` | FKs de tenant e origem restritivas; responsável anulável; código e sequência únicos; índice escolar de status/prazo; custos redundantes removidos | Aprovada; `service_order_costs` é a fonte canônica de custos realizados |
| 2 | `2026_08_27_170116_create_service_order_sequences_table` | contador escola/ano | unicidade e rollback adequados; cascata da escola aceitável para contador técnico | Aprovada |
| 3 | `2026_08_27_170121_create_service_order_histories_table` | histórico de OS | estrutura append-only adequada; a FK nasce em `RESTRICT` | Aprovada |
| 4 | `2026_08_27_170126_create_service_order_materials_table` | materiais consumidos | precisão, autoria e FKs coerentes; total é calculado no servidor | Aprovada |
| 5 | `2026_08_27_170131_create_service_order_work_logs_table` | tempo trabalhado | datas, duração e autoria coerentes | Aprovada |
| 6 | `2026_08_27_170136_create_service_order_costs_table` | custos adicionais | fonte efetivamente usada pelos cálculos e pela interface | Aprovada como fonte canônica de custos realizados |
| 7 | `2026_08_27_170141_create_private_attachments_table` | anexos privados polimórficos | índice morfológico e autoria adequados; integridade do pai depende da regra da aplicação | Aprovada para V1 |
| 8 | `2026_08_27_170146_create_service_order_members_table` | equipe executora | par OS/usuário único e índices criados corretamente | Aprovada sob a política de desativar usuários em vez de excluí-los |
| 9 | `2026_08_31_152841_restrict_history_parent_deletions` | FKs de históricos | nomes das constraints e estado anterior conferem no principal; subida e descida são simétricas | Aprovada; rollback somente com backup e janela controlada |
| 10 | `2026_09_01_115542_add_emergency_ratification_fields_to_service_orders_table` | governança emergencial | campos anuláveis para OS comuns, atores restritivos e índice de vencimento presentes | Aprovada |

## 3. FKs e política de exclusão

- `service_orders` restringe exclusão de organização, escola, ocorrência, criador, autorizador e ratificador.
- `assigned_user_id` usa `SET NULL`, compatível com a preservação da OS, embora a operação normal seja desativar usuários.
- Históricos preservam o evento e aceitam `actor_id = null`; o histórico de OS já nasce com exclusão do pai em `RESTRICT`, e a migration corretiva mantém compatibilidade com ambientes anteriores.
- Materiais, tempos, custos e membros usam cascata a partir da OS. No fluxo suportado, toda OS nasce com histórico e a FK restritiva impede a exclusão física do pai.
- O contador de sequência usa cascata da escola por ser dado técnico sem valor histórico próprio.
- Anexos são polimórficos e não possuem FK para o pai; o módulo não expõe exclusão física dos recursos vinculados.

## 4. Índices e nulabilidade

- `service_orders.code` é único globalmente.
- `(school_id, code_year, code_sequence)` é único e protege a sequência anual escolar.
- `(school_id, status, due_date)` cobre a fila principal e o filtro de atraso por escola.
- FKs geram os índices necessários para ocorrência, usuários e demais relações.
- `emergency_ratification_due_at` possui índice próprio para varredura de vencimentos.
- `(attachable_type, attachable_id)` suporta a relação polimórfica dos anexos.
- Campos de execução, responsável e emergência são anuláveis onde o fluxo permite preenchimento posterior.
- `status` é obrigatório e não possui valor padrão, impedindo criação silenciosa em estado legado.

## 5. Achados

### Resolvido DB-REV-001 — fonte única para custos

`service_orders.external_cost` e `service_orders.other_cost` foram removidos da migration original, do mass assignment e dos casts. `service_order_costs` permanece como fonte canônica usada por `additionalCostTotal()` e `totalCost()`.

Não houve conversão de dados porque `service_orders` ainda não existe no banco principal. O teste de regressão confirma que as colunas e os atributos preenchíveis redundantes não existem.

### Aplicado DB-REV-002 — histórico de OS nasce restritivo

A migration-base agora cria `service_order_histories.service_order_id` diretamente com `RESTRICT`. A migration corretiva foi mantida para tratar o histórico de ocorrência e preservar compatibilidade com ambientes existentes. O ensaio reconstruído e o teste de exclusão confirmaram a restrição.

### Recomendação DB-REV-003 — índices de histórico

Os índices atuais são suficientes para a V1 e para o volume observado. Se a timeline crescer, avaliar índice composto `(service_order_id, created_at)` e equivalente para ocorrências com base em plano de execução real; não adicionar preventivamente nesta etapa.

### Risco operacional DB-REV-004 — ciclo de vida do WSL

Durante a revisão, os serviços reiniciaram com o ciclo do WSL e houve uma conexão recusada transitória. Eles retornaram saudáveis sem intervenção. A ativação não deve ocorrer neste host não certificado; a etapa de backup e ensaio deve registrar estabilidade do ambiente oficial.

## 6. Estratégia de rollback revisada

1. Parar novas gravações antes da janela de ativação.
2. Confirmar backup verificável do banco, `.env` e uploads privados.
3. Aplicar as migrations como um conjunto ordenado.
4. Se houver falha, não executar rollback improvisado no principal.
5. Usar rollback somente dentro da janela controlada, após avaliar quais migrations foram registradas.
6. Restaurar o backup quando a reversão lógica não preservar com segurança o estado anterior.
7. Validar novamente a FK de histórico de ocorrência, pois o `down()` corretivo restaura temporariamente `CASCADE`.

## 7. Veredito

`TSK-DB-001/002/003/004` e as correções `DB-REV-001/002` estão concluídos. Após backup imediato e autorização explícita, as dez migrations foram aplicadas no banco principal `sigme` deste computador. O estado final possui 28 migrations executadas, zero pendências e smoke tests aprovados.

## 8. Evidência de backup e cópia descartável

- Data: 03/09/2026.
- Backup: `/home/e-not-094/backups/sigme/20260903-084331`.
- Cópia restaurada: `sigme_rehearsal_20260903_084331`.
- Integridade: hashes dos artefatos aprovados, 23 tabelas e 18 migrations em origem e cópia, `CHECK TABLE` aprovado e dados estáveis com hash idêntico.
- Manifesto e comando de restauração: `/home/e-not-094/backups/sigme/20260903-084331/RESTORE_AND_VALIDATION.md`.
- Ensaio `TSK-DB-003`: 10 migrations aplicadas e revertidas, hash integral preservado após rollback e restauração real do dump aprovada.
- Ativação `TSK-DB-004`: evidência em `/home/e-not-094/backups/sigme/20260903-091925-pre-db004/DB004_ACTIVATION.md`.
