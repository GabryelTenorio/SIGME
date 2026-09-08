# Matriz de Permissões — SIGME

Atualizado em 08/09/2026.

Status: baseline de autorização aprovado; diferenças do código atual estão registradas como gaps.

## 1. Princípios

1. Autorização é definida por capacidades, não por nomes de cargos.
2. Perfis são conjuntos reutilizáveis de capacidades e podem ser acumulados.
3. Toda capacidade possui escopo de organização, escola, autoria ou atribuição.
4. Um papel de organização pode alcançar as escolas da organização; um papel escolar não pode sair da escola atribuída.
5. Acesso à escola e permissão para a ação são verificações diferentes e ambas são obrigatórias.
6. Alterar URL ou identificador nunca pode ampliar o escopo autorizado.
7. Ser responsável por uma OS não concede automaticamente todas as ações; cada ação exige capacidade específica.
8. Regras de negócio, como segregação entre criador e aprovador, continuam válidas mesmo quando o usuário possui a capacidade técnica.
9. Histórico e notificação não substituem autorização.
10. Perfis padrão podem evoluir, mas capacidades aprovadas não devem ser alteradas silenciosamente.
11. Na primeira versão, perfis padrão não podem ser criados, clonados ou personalizados pela interface.
12. Fornecedor externo não é usuário e não recebe permissão no SIGME.

## 2. Escopos

| Escopo | Regra |
| --- | --- |
| Plataforma | Administração técnica transversal; ainda sujeita a regras de negócio explícitas |
| Organização | Ação limitada à organização do papel e às escolas pertencentes a ela |
| Escola | Ação limitada à escola vinculada ao papel |
| Autoria | Usuário acessa o recurso criado por ele quando a capacidade permitir |
| Atribuição | Usuário acessa ou executa OS em que é responsável ou membro, dentro da escola autorizada |

## 3. Perfis padrão

| Perfil | Escopo padrão | Finalidade |
| --- | --- | --- |
| Administrador da rede | Organização | Administrar a rede e suas escolas |
| Administrador da escola | Escola | Administrar uma escola específica |
| Direção / Gestor | Escola | Triar, encaminhar, governar OS e encerrar ocorrências |
| Solicitante | Escola | Registrar e acompanhar ocorrências próprias |
| Técnico / Manutenção | Escola | Executar OS atribuídas |
| Representante de aluno | Escola | Registrar e acompanhar ocorrências próprias, quando habilitado |

Os nomes acima são perfis iniciais, não regras rígidas de cargo. Um usuário pode acumular perfis compatíveis.

Na primeira versão, administradores apenas atribuem perfis padrão existentes. Criação, clonagem ou edição de perfis e capacidades fica fora do escopo.

## 4. Matriz funcional aprovada

Legenda: `S` permitido por padrão; `A` permitido somente por autoria; `T` permitido somente por atribuição; `N` não permitido por padrão.

| Capacidade | Rede | Escola | Gestor | Solicitante | Técnico | Rep. aluno |
| --- | --- | --- | --- | --- | --- | --- |
| Gerenciar escolas | S | S no escopo | N | N | N | N |
| Gerenciar usuários | S | S no escopo | N | N | N | N |
| Gerenciar ambientes | S | S no escopo | Visualizar | Visualizar | Visualizar | Visualizar |
| Gerenciar categorias | S | S no escopo | Visualizar | N | N | N |
| Criar ocorrência | S | S | S | S | S | S |
| Enviar anexo privado na própria ocorrência | S | S | S | A | A | A |
| Visualizar ocorrência própria | S | S | S | A | A | A |
| Visualizar ocorrências da escola | S | S | S | N | Encaminhadas no escopo | N |
| Iniciar e executar triagem | S | S | S | N | N | N |
| Confirmar prioridade | S | S | S | N | N | N |
| Solicitar informação | S | S | S | N | N | N |
| Fornecer informação solicitada | S quando autor | S quando autor | A | A | A | A |
| Marcar duplicada ou não procede | S | S | S | N | N | N |
| Encaminhar ocorrência | S | S | S | N | N | N |
| Criar OS | S | S | S | N | N | N |
| Atribuir responsável/equipe | S | S | S | N | N | N |
| Visualizar todas as OS da escola | S | S | S | N | N | N |
| Visualizar OS atribuída | S | S | S | N | T | N |
| Aprovar/rejeitar OS | S com segregação | S com segregação | S com segregação | N | N | N |
| Cancelar OS | S | S | S | N | N | N |
| Iniciar/retomar OS | S se atribuído ou gestor técnico | S se atribuído ou gestor técnico | N por padrão | N | T | N |
| Pausar/aguardar material | S se atribuído ou gestor técnico | S se atribuído ou gestor técnico | N por padrão | N | T | N |
| Registrar diagnóstico | S se atribuído ou gestor técnico | S se atribuído ou gestor técnico | N por padrão | N | T | N |
| Registrar atualização/material/tempo/custo | S se atribuído ou gestor técnico | S se atribuído ou gestor técnico | N por padrão | N | T | N |
| Concluir OS | S se atribuído ou gestor técnico | S se atribuído ou gestor técnico | N por padrão | N | T | N |
| Encerrar/reabrir ocorrência | S | S | S | N | N | N |
| Solicitar reabertura de ocorrência própria | S quando autor | S quando autor | A | A | A | A |
| Visualizar indicadores | S | S | S | N | N | N |

Administrador que executar trabalho técnico deve possuir as capacidades técnicas e estar atribuído, ou assumir uma função técnica explicitamente registrada. A condição administrativa isolada não deve transformar silenciosamente o usuário em executor.

## 5. Capacidades técnicas mínimas para atribuição

Um usuário só pode ser escolhido como responsável ou membro executor de OS quando atender a todas as condições:

- usuário ativo;
- mesma organização da ocorrência;
- acesso à escola;
- `ordens_servico.visualizar` na escola;
- `ordens_servico.executar` na escola.

A interface deve listar somente usuários elegíveis. O backend deve repetir a validação e rejeitar IDs manipulados.

Capacidades específicas continuam obrigatórias:

- `ordens_servico.iniciar` para iniciar ou retomar;
- `ordens_servico.atualizar` para atualizações e anexos;
- `ordens_servico.pausar` para pausar ou aguardar material;
- `ordens_servico.registrar_diagnostico` para diagnóstico;
- `ordens_servico.registrar_material` para material;
- `ordens_servico.registrar_tempo` para tempo trabalhado;
- `ordens_servico.registrar_custo` para custos;
- `ordens_servico.concluir` para conclusão.

## 6. Segregação de aprovação

Quando `approval_required = true`:

1. o criador não pode aprovar nem rejeitar a própria OS;
2. o aprovador deve possuir `ordens_servico.aprovar` ou `ordens_servico.rejeitar` na mesma escola;
3. o aprovador deve estar ativo;
4. a decisão deve ser auditada;
5. a ausência de outro aprovador mantém a OS em `AGUARDANDO_APROVACAO`;
6. acumular perfis não remove a separação entre criador e aprovador;
7. início emergencial exige autorização de outro gestor/administrador, diferente do criador, e ratificação posterior.

## 7. Autorização emergencial

- A OS deve estar urgente e em `AGUARDANDO_APROVACAO`.
- O criador da OS não pode autorizar o próprio início emergencial.
- O autorizador deve ser outro gestor ou administrador com escopo na escola.
- O executor deve cumprir a elegibilidade técnica e estar atribuído.
- A autorização exige motivo, histórico e notificação.
- A ratificação posterior deve ser feita por aprovador diferente do criador.
- A ratificação deve ocorrer até o final do próximo dia útil.
- Se o prazo vencer, a execução continua, a conclusão fica bloqueada e gestores/aprovadores recebem alertas até a ratificação.

## 8. Notificações e preferência de e-mail

- Notificações internas seguem o escopo e os destinatários definidos na máquina de estados.
- Todo usuário destinatário recebe a notificação interna independentemente da preferência de e-mail.
- Cada usuário poderá habilitar ou desabilitar o canal de e-mail para si.
- A preferência não concede acesso ao recurso; o link da notificação deve passar novamente pela autorização normal.
- E-mails não devem expor conteúdo sensível além do mínimo necessário.
- Para novos usuários, o canal de e-mail começa desativado.
- Eventos destinados por capacidade são enviados a todos os usuários ativos e elegíveis da escola.
- Acúmulo de perfis não pode gerar notificações duplicadas para o mesmo usuário e evento.
- Ratificação vencida notifica gestores e aprovadores no vencimento e uma vez por dia útil enquanto permanecer pendente.
- E-mail contém somente protocolo, tipo do evento, status e link autenticado, sem descrição sensível.

## 9. Anexos de ocorrência

- O autor pode enviar imagens e PDF ao criar a própria ocorrência.
- Cada arquivo é privado e limitado a 10 MB.
- A abertura aceita no máximo 5 arquivos.
- O autor pode adicionar evidências em `ABERTA`, `EM_TRIAGEM` ou `AGUARDANDO_INFORMACOES`.
- Visualização e download exigem autorização para visualizar a ocorrência.
- Usuário de outra escola ou sem acesso à ocorrência não pode descobrir nem baixar o arquivo.
- Cada ocorrência aceita no máximo 20 arquivos no total, incluindo os anexos enviados na abertura.

## 10. Serviço externo

- O fornecedor não possui conta, perfil ou permissão.
- Um responsável interno elegível responde pela OS no SIGME.
- Gestores criam, atribuem e aprovam conforme as mesmas regras de segregação.
- Nome/empresa e descrição do serviço são obrigatórios.
- Contato e CNPJ/CPF são opcionais.
- O responsável interno registra fornecedor, atualizações, custos, evidências e conclusão.

## 11. Proteção do histórico

- Models e serviços de histórico não devem expor operações de atualização ou exclusão.
- Relações de banco devem restringir exclusões que removeriam eventos.
- Ajustes devem gerar evento compensatório, preservando o registro anterior.
- A proteção será validada por testes de aplicação e banco.
- Não serão usados triggers nesta fase.
- Não haverá exclusão automática na primeira versão.
- A retenção definitiva deve ser aprovada antes da produção.

## 12. Capacidades atuais que exigem consolidação

| Capacidade atual | Situação | Ação necessária |
| --- | --- | --- |
| `ocorrencias.visualizar_todas` | Removida do catálogo e do perfil gestor em 04/09/2026 | Migration reversível validada em `sigme_testing`; implantação no banco principal permanece controlada |
| `ocorrencias.visualizar_escola` | Usada para visão ampla da escola | Manter como capacidade principal escolar |
| `ocorrencias.visualizar_rede` | Usada para visão de rede | Manter com escopo de organização |
| `ordens_servico.executar` | Existe, mas não é usada nas policies de ação | Usar como requisito de elegibilidade técnica |
| Capacidades específicas de OS | Usadas nas ações | Manter para menor privilégio |
| `indicadores.visualizar` | Existe sem módulo implementado | Manter reservada até o módulo ser aprovado |

## 13. Gaps entre esta matriz e o código atual

1. Resolvido no banco principal em 03/09/2026: backend e tela de criação exigem as capacidades técnicas mínimas dos responsáveis e membros.
2. `ServiceOrderPolicy::before()` permite ao administrador de plataforma contornar policies; as regras de segregação precisam ser aplicadas no domínio, não apenas na policy.
3. Resolvido em código e testes em 31/08/2026: aprovação e rejeição impedem o próprio criador, inclusive quando `ServiceOrderPolicy::before()` autoriza a administração de plataforma.
4. Perfis e permissões são provisionados por catálogo, mas não possuem administração própria.
5. Alterações de papéis e vínculos de usuários não possuem histórico dedicado.
6. Preferência de e-mail e notificações internas ainda não existem.
7. Resolvido em 04/09/2026: `ocorrencias.visualizar_todas` foi removida, mantendo `ocorrencias.visualizar_escola` como capacidade escolar efetiva sem ampliar acesso.
8. Resolvido no banco principal em 03/09/2026: autorização emergencial e ratificação exigem ator elegível da mesma escola e diferente do criador.
9. Resolvido no banco principal em 03/09/2026: models recusam update/delete e as FKs corretivas restringem a exclusão dos registros-pai.
10. Resolvido em código e testes em 04/09/2026: somente o autor pode pedir reabertura em `ENCERRADA`; gestor e administrador de plataforma não contornam a autoria, e somente o gestor decide a transição.
11. Resolvido em código e testes: notificações por capacidade filtram atividade e escola e deduplicam usuário e evento, inclusive com papéis acumulados.
12. Resolvido em 04/09/2026: anexos privados respeitam limites de 5 na abertura e 20 no total, download autorizado e inclusão posterior exclusiva do autor.
13. Resolvido em código e testes em 04/09/2026: serviço externo possui dados estruturados, exige responsável interno elegível e não cria usuário, vínculo escolar, papel ou permissão para o fornecedor; autenticação e acesso sem usuário permanecem bloqueados.
14. Resolvido em 04/09/2026: somente os seis perfis padrão definidos em código aparecem e podem ser atribuídos; IDs de perfis personalizados são rejeitados e não existem rotas ou campos de personalização.
15. Resolvido em código e testes em 08/09/2026: alertas de ratificação vencida são enviados a gestores e aprovadores elegíveis, exceto o criador, com deduplicação diária; o e-mail continua limitado aos campos mínimos aprovados.
16. Resolvido em 04/09/2026: o autor pode adicionar evidências apenas em `ABERTA`, `EM_TRIAGEM` e `AGUARDANDO_INFORMACOES`, sem alterar o estado.

## 14. Testes obrigatórios antes da correção ser concluída

- usuário de outra escola ou organização não pode ser atribuído;
- solicitante sem capacidades técnicas não pode ser atribuído;
- técnico inativo não pode ser atribuído;
- técnico elegível da escola pode ser atribuído;
- ID de usuário manipulado no request é rejeitado;
- criador não pode aprovar nem rejeitar sua própria OS;
- outro aprovador autorizado da escola pode decidir;
- aprovador de outra escola não pode decidir;
- acumular perfil de criador e aprovador não contorna segregação;
- administrador de plataforma não contorna silenciosamente a segregação;
- preferência de e-mail não desativa notificação interna;
- novo usuário começa com e-mail desativado;
- notificação não permite acesso a recurso fora do escopo.
- criador não pode autorizar o próprio início emergencial;
- outro gestor/administrador da escola pode autorizar a emergência;
- início emergencial registra motivo, autorizador, prazo e ratificação pendente; notificações permanecem pendentes;
- ratificação realizada até o fim do próximo dia útil encerra a pendência;
- ratificação vencida impede conclusão, mas não impede registros técnicos;
- históricos não podem ser atualizados ou excluídos pela aplicação;
- exclusão de recurso não pode apagar histórico por cascata.
- solicitante só pode pedir reabertura de ocorrência própria;
- pedido de reabertura não muda o estado sem decisão do gestor;
- todos os usuários elegíveis recebem uma única notificação por evento.
- segunda gravação concorrente de triagem é rejeitada sem sobrescrever a primeira;
- anexo de ocorrência aceita somente imagem/PDF dentro do limite;
- abertura rejeita o sexto anexo inicial;
- autor pode adicionar evidência somente nos três estados aprovados;
- usuário sem acesso à ocorrência não pode baixar o anexo;
- fornecedor externo não recebe usuário ou acesso;
- OS externa exige responsável interno elegível;
- OS externa exige nome/empresa e descrição e aceita contato/documento opcionais;
- perfis padrão não podem ser personalizados na primeira versão.
- ratificação vencida notifica no vencimento e uma vez por dia útil;
- e-mail não contém descrição, diagnóstico, solução ou custos.
