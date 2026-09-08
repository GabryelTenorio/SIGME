# Máquina de Estados — Ocorrências e Ordens de Serviço

Atualizado em 03/09/2026.

Status: baseline funcional aprovado; implementação ainda possui gaps identificados neste documento.

## 1. Princípios aprovados

1. Ocorrência e Ordem de Serviço são agregados diferentes.
2. Toda execução de manutenção deve ocorrer por meio de uma OS.
3. Uma ocorrência pode possuir uma ou mais OS.
4. Encaminhar uma ocorrência não inicia o atendimento automaticamente.
5. O atendimento começa quando a primeira OS válida entra em execução.
6. A ocorrência só pode ser resolvida quando todas as OS válidas estiverem concluídas.
7. OS `CANCELADA` ou `REJEITADA` não é considerada válida para bloquear a resolução, desde que exista ao menos uma OS concluída que sustente a resolução.
8. Reabertura é um evento auditável que retorna a ocorrência a `EM_TRIAGEM`; `REABERTA` não é estado persistido.
9. Equipamento ou patrimônio não faz parte da primeira versão e não bloqueia a ocorrência.
10. Toda transição deve validar estado atual, capacidade, escopo escolar, ator e dados obrigatórios no backend.
11. Toda transição aceita deve gerar evento de histórico na mesma transação sempre que tecnicamente possível.
12. Notificações internas não substituem autorização nem histórico.
13. Históricos são append-only na aplicação, com exclusões restringidas no banco e testes, sem triggers.
14. Transições de triagem usam concorrência otimista; não existe posse exclusiva da ocorrência.
15. Evidências enviadas na abertura são opcionais, privadas e não alteram o estado.
16. Serviço externo usa a mesma OS e sempre possui responsável interno; fornecedor não é usuário do SIGME.

## 2. Estados da Ocorrência

| Estado | Significado | Tipo |
| --- | --- | --- |
| `ABERTA` | Ocorrência registrada e aguardando análise | Ativo |
| `EM_TRIAGEM` | Ocorrência sob análise funcional | Ativo |
| `AGUARDANDO_INFORMACOES` | Triagem aguarda complemento do solicitante | Ativo com pendência |
| `ENCAMINHADA` | Triagem concluída; ocorrência liberada para criação de OS | Ativo |
| `EM_ATENDIMENTO` | Pelo menos uma OS válida está em execução | Ativo |
| `RESOLVIDA` | Todas as OS válidas foram concluídas; aguarda validação/encerramento | Ativo de validação |
| `ENCERRADA` | Resultado aceito e fluxo encerrado | Terminal reversível por evento de reabertura |
| `DUPLICADA` | Ocorrência vinculada a uma ocorrência principal | Terminal |
| `NAO_PROCEDE` | Solicitação não pertence ao fluxo ou não possui fundamento | Terminal |

`REABERTA` não integra a lista de estados. O histórico deve registrar `reopened`, o estado anterior e o retorno a `EM_TRIAGEM`.

## 3. Transições da Ocorrência

| ID | Estado atual | Ação | Próximo estado | Capacidade/ator | Dados obrigatórios |
| --- | --- | --- | --- | --- | --- |
| OCC-T01 | criação | registrar ocorrência | `ABERTA` | `ocorrencias.criar` na escola | escola, ambiente, categoria, título, descrição, impacto, urgência e anexos opcionais |
| OCC-T02 | `ABERTA` | iniciar triagem | `EM_TRIAGEM` | `ocorrencias.triar` | ator e data da triagem |
| OCC-T03 | `ABERTA` ou `EM_TRIAGEM` | solicitar informação | `AGUARDANDO_INFORMACOES` | `ocorrencias.solicitar_informacoes` | mensagem objetiva |
| OCC-T04 | `AGUARDANDO_INFORMACOES` | fornecer informação | `EM_TRIAGEM` | autor da ocorrência | mensagem de resposta |
| OCC-T05 | `ABERTA`, `EM_TRIAGEM` ou `AGUARDANDO_INFORMACOES` | marcar duplicada | `DUPLICADA` | `ocorrencias.marcar_duplicada` | ocorrência principal da mesma escola; justificativa recomendada |
| OCC-T06 | `ABERTA`, `EM_TRIAGEM` ou `AGUARDANDO_INFORMACOES` | marcar não procede | `NAO_PROCEDE` | `ocorrencias.marcar_nao_procede` | justificativa obrigatória |
| OCC-T07 | `EM_TRIAGEM` | confirmar prioridade | `EM_TRIAGEM` | `ocorrencias.confirmar_prioridade` | prioridade e nota de triagem |
| OCC-T08 | `EM_TRIAGEM` | encaminhar para manutenção | `ENCAMINHADA` | `ocorrencias.encaminhar` | prioridade confirmada e destino funcional |
| OCC-T09 | `ENCAMINHADA` | iniciar primeira OS válida | `EM_ATENDIMENTO` | executor elegível da OS | OS aprovada ou início emergencial permitido |
| OCC-T10 | `EM_ATENDIMENTO` | concluir todas as OS válidas | `RESOLVIDA` | executor elegível | diagnóstico e solução em cada OS concluída |
| OCC-T11 | `RESOLVIDA` | encerrar | `ENCERRADA` | `ocorrencias.encerrar` | validação do resultado |
| OCC-T12 | `RESOLVIDA` ou `ENCERRADA` | reabrir | `EM_TRIAGEM` | `ocorrencias.reabrir` | motivo obrigatório e evento `reopened` |
| OCC-T13 | `ENCERRADA` | solicitar reabertura | `ENCERRADA` | autor da ocorrência | relato da recorrência e evento `reopen_requested` |
| OCC-T14 | `ABERTA`, `EM_TRIAGEM` ou `AGUARDANDO_INFORMACOES` | adicionar evidência | mesmo estado | autor da ocorrência | imagem ou PDF privado dentro dos limites |

### Regras adicionais

- A prioridade sugerida é calculada pela matriz de impacto e urgência; o encaminhamento exige prioridade confirmada.
- Uma ocorrência `ENCAMINHADA` não pode ser executada diretamente sem OS.
- A criação de uma OS não muda a ocorrência para `EM_ATENDIMENTO`; a execução da primeira OS muda.
- Deve existir pelo menos uma OS concluída para que a ocorrência se torne `RESOLVIDA`.
- A reabertura preserva as OS anteriores e exige nova triagem. Se houver nova manutenção, deve ser criada nova OS; OS concluídas não são reativadas.
- O gestor valida o resultado e encerra. O solicitante pode pedir reabertura, mas somente usuário com `ocorrencias.reabrir` realiza a transição.
- O pedido do solicitante mantém a ocorrência `ENCERRADA`, registra evento e notifica os gestores elegíveis até a decisão.
- `DUPLICADA` e `NAO_PROCEDE` não podem iniciar OS.
- Imagens e PDF enviados na abertura devem ser privados, autorizados por ocorrência, limitados a 10 MB por arquivo e a 5 arquivos iniciais.
- O autor pode adicionar novas evidências em `ABERTA`, `EM_TRIAGEM` ou `AGUARDANDO_INFORMACOES`; isso não altera o estado.
- Cada ocorrência aceita no máximo 20 arquivos no total, somando os anexos da abertura e as evidências adicionadas posteriormente.

### Concorrência da triagem

- A ocorrência não possui um único dono de triagem.
- Usuários elegíveis podem atuar dentro do escopo da escola.
- Cada comando deve carregar uma versão ou marca de atualização da ocorrência.
- Se outro usuário tiver alterado o registro depois da leitura, a gravação é rejeitada sem sobrescrever dados.
- A interface informa o conflito e exige recarregar o estado atual.
- A tentativa rejeitada não cria transição nem histórico de negócio.

## 4. Estados da Ordem de Serviço

| Estado | Significado | Tipo |
| --- | --- | --- |
| `AGUARDANDO_APROVACAO` | OS bloqueada até decisão de aprovador diferente do criador | Ativo com pendência |
| `APROVADA` | OS autorizada e pronta para execução | Ativo |
| `EM_EXECUCAO` | Trabalho técnico em andamento | Ativo |
| `AGUARDANDO_MATERIAL` | Trabalho suspenso por falta de material | Ativo com pendência |
| `PAUSADA` | Trabalho suspenso por motivo operacional registrado | Ativo com pendência |
| `CONCLUIDA` | Trabalho concluído com diagnóstico e solução | Terminal |
| `REJEITADA` | Aprovação negada com motivo | Terminal |
| `CANCELADA` | OS interrompida administrativamente com motivo | Terminal |

`PLANEJADA` não faz parte do fluxo aprovado. Foi removida do catálogo, das transições ativas e do valor padrão do banco em 01/09/2026. A aplicação define explicitamente `APROVADA` ou `AGUARDANDO_APROVACAO` na criação.

## 5. Transições da Ordem de Serviço

| ID | Estado atual | Ação | Próximo estado | Capacidade/ator | Dados obrigatórios |
| --- | --- | --- | --- | --- | --- |
| OS-T01 | criação sem aprovação necessária | criar OS | `APROVADA` | `ordens_servico.criar` | ocorrência encaminhada, escopo, responsável/equipe elegível e dados da OS |
| OS-T02 | criação com aprovação necessária | criar OS | `AGUARDANDO_APROVACAO` | `ordens_servico.criar` | mesmos dados e motivo objetivo da aprovação |
| OS-T03 | `AGUARDANDO_APROVACAO` | aprovar | `APROVADA` | `ordens_servico.aprovar`; ator diferente do criador | decisão auditada |
| OS-T04 | `AGUARDANDO_APROVACAO` | rejeitar | `REJEITADA` | `ordens_servico.rejeitar`; ator diferente do criador | motivo obrigatório |
| OS-T05 | `APROVADA` | iniciar | `EM_EXECUCAO` | executor elegível e atribuído | data/hora de início |
| OS-T05E | `AGUARDANDO_APROVACAO` urgente | autorizar início emergencial | `EM_EXECUCAO` | outro gestor/administrador autorizado e executor elegível | motivo, autorizador, notificação e ratificação posterior |
| OS-T06 | `EM_EXECUCAO` | aguardar material | `AGUARDANDO_MATERIAL` | executor elegível e atribuído | motivo obrigatório |
| OS-T07 | `EM_EXECUCAO` | pausar | `PAUSADA` | executor elegível e atribuído | motivo obrigatório |
| OS-T08 | `AGUARDANDO_MATERIAL` ou `PAUSADA` | retomar | `EM_EXECUCAO` | executor elegível e atribuído | evento auditado |
| OS-T09 | `EM_EXECUCAO` | concluir | `CONCLUIDA` | executor elegível e atribuído | diagnóstico e solução obrigatórios |
| OS-T10 | estado não terminal permitido | cancelar | `CANCELADA` | `ordens_servico.cancelar` | motivo obrigatório |

### Início emergencial aprovado

Uma OS urgente em `AGUARDANDO_APROVACAO` pode entrar em `EM_EXECUCAO` antes da aprovação regular quando todas as condições forem atendidas:

1. a prioridade da ocorrência é urgente;
2. o responsável técnico é elegível e está atribuído;
3. outro gestor ou administrador da escola, diferente do criador da OS, autoriza o início;
4. o motivo é obrigatório;
5. aprovadores, criador e equipe recebem notificação interna;
6. a decisão fica registrada no histórico;
7. a OS recebe ratificação posterior por aprovador diferente do criador.

A ratificação posterior é evento de governança e não faz a OS retornar de `EM_EXECUCAO` para `APROVADA`. Ela deve ocorrer até o final do próximo dia útil.

Se o prazo vencer:

1. o trabalho técnico pode continuar;
2. a OS não pode mudar para `CONCLUIDA`;
3. gestores e aprovadores elegíveis recebem alertas até a ratificação;
4. a ratificação e o atraso ficam registrados no histórico.

O primeiro alerta é enviado no vencimento. Enquanto a ratificação continuar pendente, um novo alerta é enviado uma vez por dia útil.

## 6. Elegibilidade de responsável e equipe

Para ser responsável ou membro executor de uma OS, o usuário deve cumulativamente:

1. estar ativo;
2. pertencer à mesma organização;
3. possuir acesso à escola da ocorrência;
4. possuir `ordens_servico.visualizar` na escola;
5. possuir `ordens_servico.executar` na escola.

Além da elegibilidade, cada ação continua exigindo sua capacidade específica, como iniciar, pausar, diagnosticar, registrar material, registrar tempo, registrar custo ou concluir.

Valores monetários usam escala de duas casas sem conversão para `float`. Quantidade de material admite três casas; o custo da linha é calculado no servidor com arredondamento comercial `HALF_UP`. Entradas acima da capacidade de `DECIMAL(12,2)` são rejeitadas antes da persistência, e a apresentação em reais formata diretamente a string decimal.

## 7. Aprovação e segregação

- Quando a OS exigir aprovação, o criador não pode aprovar nem rejeitar sua própria OS.
- A regra vale mesmo que o criador acumule o perfil de aprovador.
- Permissão de plataforma ou administração não deve contornar silenciosamente essa regra de negócio.
- A decisão deve registrar aprovador, data, resultado e motivo quando houver rejeição.
- Limite de custo, serviço externo, substituição, descarte e compra extraordinária continuam como gatilhos existentes até revisão específica.

## 8. Serviço externo

- Serviço externo continua sendo uma OS do SIGME.
- A OS deve possuir responsável interno elegível e atribuído.
- O fornecedor não recebe conta nem acesso ao sistema.
- Nome ou empresa e descrição do serviço são obrigatórios.
- Contato e CNPJ/CPF são opcionais e devem ser coletados somente quando necessários.
- Dados do fornecedor, custos e evidências devem permanecer vinculados à OS.
- O responsável interno registra atualizações, documentos, custos e conclusão.
- Serviço externo continua sujeito a aprovação, segregação, histórico e notificações.

## 9. Notificações associadas ao fluxo

Notificação interna é obrigatória para os eventos definidos abaixo. E-mail é um canal adicional configurável individualmente pelo destinatário.

| Evento | Destinatários mínimos |
| --- | --- |
| ocorrência criada | responsáveis pela triagem da escola |
| informação solicitada | solicitante |
| informação fornecida | responsáveis pela triagem |
| ocorrência encaminhada | responsáveis por criar/atribuir OS |
| OS atribuída ou equipe alterada | responsável e membros executores |
| OS aguardando aprovação | aprovadores elegíveis, exceto o criador |
| ratificação emergencial vencida | gestores e aprovadores elegíveis |
| OS aprovada ou rejeitada | criador, responsável e membros |
| OS aguardando material ou pausada | gestor responsável e equipe |
| ocorrência resolvida | solicitante e responsáveis por encerramento |
| ocorrência encerrada ou reaberta | solicitante, gestor e equipe relacionada |
| reabertura solicitada | todos os usuários da escola com capacidade de reabrir |

Para novos usuários, o e-mail começa desativado e pode ser ativado individualmente. A preferência de e-mail nunca desativa a notificação interna nem o histórico.

Quando o destinatário for definido por capacidade, todos os usuários ativos e elegíveis da escola recebem a notificação interna. O sistema deve deduplicar usuários que acumulam vários perfis.

E-mails devem conter somente protocolo, tipo do evento, status e link autenticado. Título detalhado, descrição, diagnóstico, solução, custos e outros dados sensíveis não devem ser enviados por e-mail.

## 10. Proteção do histórico

- Históricos de ocorrência e OS não podem ser atualizados ou excluídos por fluxos da aplicação.
- Relações de banco devem restringir exclusões que apagariam eventos históricos.
- Correções de informação devem ser registradas como novo evento compensatório.
- Testes devem provar a ausência de update/delete e a preservação dos eventos.
- Triggers de banco não serão usadas nesta fase.
- Na primeira versão não haverá exclusão automática. A retenção definitiva deve ser aprovada antes da produção.

## 11. Gaps entre esta regra e o código atual

1. Transições estão distribuídas entre controllers.
2. Resolvido no banco principal em 03/09/2026: atribuição exige usuário ativo, organização, escola, `visualizar` e `executar` OS.
3. Resolvido no banco principal em 03/09/2026: criador não pode aprovar nem rejeitar a própria OS, inclusive com perfis acumulados ou administração de plataforma.
4. Resolvido no banco principal em 03/09/2026: início emergencial exige autorizador diferente do criador, executor elegível, motivo e ratificação posterior; a notificação ainda depende do módulo próprio.
5. Armazenamento, leitura, eventos operacionais, links com reautorização, preferência individual desativada por padrão, e-mail mínimo opcional e alertas recorrentes de ratificação vencida foram implementados e testados em `sigme_testing`.
6. Resolvido no banco principal em 03/09/2026: `PLANEJADA` foi removido do fluxo ativo e a coluna `status` não possui valor padrão.
7. Resolvido em código e testes em 01/09/2026: resolução e encerramento exigem ao menos uma OS concluída e nenhuma OS válida pendente; reabertura e nova OS preservam os registros anteriores.
8. Resolvido em 03/09/2026: as migrations de OS foram aplicadas no banco principal deste computador.
9. Resolvido no banco principal em 03/09/2026: models são append-only e FKs usam `RESTRICT`.
10. Resolvido em código e testes em 04/09/2026: pedido de reabertura é exclusivo do autor em `ENCERRADA`, registra motivo e histórico sem alterar o estado até decisão do gestor.
11. Resolvido em código e testes: notificações por capacidade usam destinatários ativos da escola e deduplicação por usuário e evento, inclusive no pedido de reabertura.
12. Resolvido em código e testes em 31/08/2026: ações de triagem validam token dentro de transação com bloqueio da ocorrência e rejeitam gravações obsoletas com instrução de recarregamento.
13. Anexos privados JPEG, PNG e PDF de até 10 MB podem ser enviados na abertura, com limite de 5, e posteriormente apenas pelo autor nos três estados aprovados, até 20 arquivos no total; download e inclusão reaplicam a autorização atual.
14. Resolvido em código e testes em 04/09/2026: serviço externo possui fornecedor estruturado e responsável interno obrigatório e elegível; o fornecedor não recebe identidade, permissão ou acesso ao sistema.
15. Resolvido em código e testes em 01/09/2026: ratificação vencida não bloqueia registros técnicos, mas bloqueia a conclusão da OS.
16. Resolvido em código e testes em 08/09/2026: o agendador executa a varredura a cada hora; cada destinatário elegível recebe no máximo um alerta por OS e data, fins de semana não repetem o alerta e o próximo dia útil gera nova notificação.
17. E-mails ainda não possuem o formato mínimo aprovado.
18. Resolvido em 04/09/2026: inclusão posterior é exclusiva do autor em `ABERTA`, `EM_TRIAGEM` ou `AGUARDANDO_INFORMACOES`, preserva o estado, registra histórico e respeita o limite total transacional de 20 arquivos.

Esses gaps devem ser transformados em testes de caracterização antes das correções.
