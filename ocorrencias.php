<?php
require_once __DIR__ . '/funcoes.php';
$gestor = exigir_gestor();
$escola = escola_do_gestor();

// Os formulários desta página criam ocorrências e mudam sua situação.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    conferir_token();
    try {
        $acao = (string) ($_POST['acao'] ?? '');
        $id = inteiro_post('id');
        if ($acao === 'criar') {
            $ambiente = pertence('ambientes', inteiro_post('ambiente_id'), $escola);
            $categoria = pertence('categorias', inteiro_post('categoria_id'), $escola);
            if (!$ambiente || !$ambiente['ativo'] || !$categoria || !$categoria['ativo']) {
                throw new RuntimeException('Selecione ambiente e categoria ativos.');
            }
            $tituloNovo = obrigatorio('titulo', 180);
            $descricao = obrigatorio('descricao', 5000);
            $prioridade = (string) ($_POST['prioridade'] ?? 'media');
            if (!in_array($prioridade, ['baixa', 'media', 'alta', 'urgente'], true)) throw new RuntimeException('Prioridade inválida.');
            executar('INSERT INTO ocorrencias (escola_id, ambiente_id, categoria_id, solicitante_id, titulo, descricao, prioridade) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$escola, $ambiente['id'], $categoria['id'], $gestor['id'], $tituloNovo, $descricao, $prioridade]);
            $novoId = (int) banco()->lastInsertId();
            historico('ocorrencia', $novoId, 'Ocorrência criada');
            aviso('Ocorrência criada.');
            ir('ocorrencias.php?id=' . $novoId);
        }

        $ocorrencia = pertence('ocorrencias', $id, $escola);
        if (!$ocorrencia) throw new RuntimeException('Ocorrência não encontrada.');
        $status = $ocorrencia['status'];
        if ($acao === 'iniciar' && $status === 'aberta') {
            executar("UPDATE ocorrencias SET status = 'em_triagem' WHERE id = ?", [$id]);
            historico('ocorrencia', $id, 'Triagem iniciada');
        } elseif ($acao === 'prioridade' && $status === 'em_triagem') {
            $prioridade = (string) ($_POST['prioridade'] ?? '');
            if (!in_array($prioridade, ['baixa', 'media', 'alta', 'urgente'], true)) throw new RuntimeException('Prioridade inválida.');
            $nota = obrigatorio('nota', 3000);
            executar('UPDATE ocorrencias SET prioridade = ?, prioridade_confirmada = 1, observacao_triagem = ? WHERE id = ?', [$prioridade, $nota, $id]);
            historico('ocorrencia', $id, 'Prioridade confirmada', $nota);
        } elseif ($acao === 'encaminhar' && $status === 'em_triagem') {
            if (!$ocorrencia['prioridade_confirmada']) throw new RuntimeException('Confirme a prioridade antes de encaminhar.');
            $destino = obrigatorio('destino', 180);
            executar("UPDATE ocorrencias SET status = 'encaminhada', destino = ? WHERE id = ?", [$destino, $id]);
            historico('ocorrencia', $id, 'Encaminhada', $destino);
        } elseif ($acao === 'nao_procede' && in_array($status, ['aberta', 'em_triagem'], true)) {
            $nota = obrigatorio('nota', 3000);
            executar("UPDATE ocorrencias SET status = 'nao_procede' WHERE id = ?", [$id]);
            historico('ocorrencia', $id, 'Não procede', $nota);
        } elseif ($acao === 'duplicada' && in_array($status, ['aberta', 'em_triagem'], true)) {
            $original = pertence('ocorrencias', inteiro_post('duplicada_de'), $escola);
            if (!$original || (int) $original['id'] === $id) throw new RuntimeException('Escolha outra ocorrência da escola.');
            executar("UPDATE ocorrencias SET status = 'duplicada', duplicada_de = ? WHERE id = ?", [$original['id'], $id]);
            historico('ocorrencia', $id, 'Marcada como duplicada', protocolo((int) $original['id']));
        } elseif ($acao === 'encerrar' && $status === 'resolvida') {
            executar("UPDATE ocorrencias SET status = 'encerrada' WHERE id = ?", [$id]);
            historico('ocorrencia', $id, 'Ocorrência encerrada');
        } elseif ($acao === 'reabrir' && $status === 'encerrada') {
            $nota = obrigatorio('nota', 3000);
            executar("UPDATE ocorrencias SET status = 'em_triagem', prioridade_confirmada = 0 WHERE id = ?", [$id]);
            historico('ocorrencia', $id, 'Ocorrência reaberta', $nota);
        } else throw new RuntimeException('Esta ação não é permitida na situação atual.');
        aviso('Ocorrência atualizada.');
        ir('ocorrencias.php?id=' . $id);
    } catch (RuntimeException $erro) { falha($erro->getMessage()); }
}

$ambientes = listar('SELECT * FROM ambientes WHERE escola_id = ? AND ativo = 1 ORDER BY nome', [$escola]);
$categorias = listar('SELECT * FROM categorias WHERE escola_id = ? AND ativo = 1 ORDER BY nome', [$escola]);
$id = (int) ($_GET['id'] ?? 0);
$item = $id ? buscar('SELECT o.*, a.nome AS ambiente, c.nome AS categoria, u.nome AS solicitante FROM ocorrencias o JOIN ambientes a ON a.id = o.ambiente_id JOIN categorias c ON c.id = o.categoria_id JOIN usuarios u ON u.id = o.solicitante_id WHERE o.id = ? AND o.escola_id = ?', [$id, $escola]) : null;
if ($id && !$item) { http_response_code(404); exit('Ocorrência não encontrada.'); }
$titulo = $item ? protocolo($id) : 'Ocorrências';
require __DIR__ . '/topo.php';
?>
<?php if ($item): ?>
<div class="cabecalho"><div><span class="etiqueta"><?= h(protocolo($id)) ?></span><h1><?= h($item['titulo']) ?></h1><p><?= h($item['ambiente']) ?> · <?= h($item['categoria']) ?> · <?= h(data_br($item['criada_em'])) ?></p></div><span class="selo"><?= h(str_replace('_', ' ', $item['status'])) ?></span></div>
<div class="grade-duas"><section class="cartao"><h2>Detalhes</h2><div class="detalhes"><div><small>Solicitante</small><strong><?= h($item['solicitante']) ?></strong></div><div><small>Prioridade</small><strong><?= h(ucfirst($item['prioridade'])) ?><?= $item['prioridade_confirmada'] ? ' (confirmada)' : '' ?></strong></div><div><small>Destino</small><strong><?= h($item['destino'] ?: '—') ?></strong></div></div><p class="texto-longo"><?= nl2br(h($item['descricao'])) ?></p><?php if ($item['observacao_triagem']): ?><p><strong>Última observação:</strong> <?= h($item['observacao_triagem']) ?></p><?php endif; ?><?php if ($item['duplicada_de']): ?><p>Duplicada de <?= h(protocolo((int) $item['duplicada_de'])) ?></p><?php endif; ?>
</section>
<section class="cartao"><h2>Ações da triagem</h2><div class="pilha-acoes">
<?php if ($item['status'] === 'aberta'): ?><form method="post"><?php campo_token(); ?><input type="hidden" name="acao" value="iniciar"><input type="hidden" name="id" value="<?= $id ?>"><button class="botao" type="submit">Iniciar triagem</button></form><?php endif; ?>
<?php if ($item['status'] === 'em_triagem'): ?><form method="post" class="formulario"><?php campo_token(); ?><input type="hidden" name="acao" value="prioridade"><input type="hidden" name="id" value="<?= $id ?>"><h3>Confirmar prioridade</h3><label>Prioridade<select name="prioridade"><?php foreach (['baixa','media','alta','urgente'] as $nivel): ?><option value="<?= $nivel ?>" <?= $item['prioridade'] === $nivel ? 'selected' : '' ?>><?= h(ucfirst($nivel)) ?></option><?php endforeach; ?></select></label><label>Observação<input name="nota" required maxlength="3000"></label><button class="botao" type="submit">Confirmar</button></form><form method="post" class="formulario"><?php campo_token(); ?><input type="hidden" name="acao" value="encaminhar"><input type="hidden" name="id" value="<?= $id ?>"><h3>Encaminhar para manutenção</h3><label>Destino<input name="destino" required placeholder="Ex.: Equipe de manutenção"></label><button class="botao" type="submit">Encaminhar</button></form><?php endif; ?>
<?php if (in_array($item['status'], ['aberta','em_triagem'], true)): ?><details><summary>Marcar como não procede</summary><form method="post" class="formulario compacto"><?php campo_token(); ?><input type="hidden" name="acao" value="nao_procede"><input type="hidden" name="id" value="<?= $id ?>"><label>Motivo<textarea name="nota" required></textarea></label><button class="botao perigo">Confirmar</button></form></details><details><summary>Marcar como duplicada</summary><form method="post" class="formulario compacto"><?php campo_token(); ?><input type="hidden" name="acao" value="duplicada"><input type="hidden" name="id" value="<?= $id ?>"><label>Ocorrência original<select name="duplicada_de" required><option value="">Escolha</option><?php foreach (listar('SELECT id, titulo FROM ocorrencias WHERE escola_id = ? AND id <> ? ORDER BY id DESC', [$escola, $id]) as $outra): ?><option value="<?= (int) $outra['id'] ?>"><?= h(protocolo((int) $outra['id']) . ' · ' . $outra['titulo']) ?></option><?php endforeach; ?></select></label><button class="botao perigo">Confirmar</button></form></details><?php endif; ?>
<?php $ordemAtual = buscar("SELECT id, status FROM ordens_servico WHERE ocorrencia_id = ? AND escola_id = ? ORDER BY id DESC LIMIT 1", [$id, $escola]); ?>
<?php if ($ordemAtual): ?><a href="ordens.php?id=<?= (int) $ordemAtual['id'] ?>">Ver <?= h(codigo_os((int) $ordemAtual['id'])) ?></a><?php endif; ?>
<?php if ($item['status'] === 'encaminhada' && (!$ordemAtual || in_array($ordemAtual['status'], ['concluida','cancelada','rejeitada'], true))): ?><a class="botao" href="ordens.php?nova=<?= $id ?>">Criar ordem de serviço</a><?php endif; ?>
<?php if ($item['status'] === 'resolvida'): ?><form method="post"><?php campo_token(); ?><input type="hidden" name="acao" value="encerrar"><input type="hidden" name="id" value="<?= $id ?>"><button class="botao">Encerrar ocorrência</button></form><?php endif; ?>
<?php if ($item['status'] === 'encerrada'): ?><form method="post" class="formulario"><?php campo_token(); ?><input type="hidden" name="acao" value="reabrir"><input type="hidden" name="id" value="<?= $id ?>"><label>Motivo da reabertura<textarea name="nota" required></textarea></label><button class="botao secundario">Reabrir</button></form><?php endif; ?>
</div></section></div>
<section class="cartao"><h2>Histórico</h2><?php foreach (listar("SELECT h.*, u.nome FROM historico h JOIN usuarios u ON u.id = h.usuario_id WHERE h.tipo = 'ocorrencia' AND h.registro_id = ? AND h.escola_id = ? ORDER BY h.id DESC", [$id, $escola]) as $evento): ?><div class="linha-historico"><strong><?= h($evento['acao']) ?></strong><small><?= h(data_br($evento['criado_em'])) ?> · <?= h($evento['nome']) ?></small><?php if ($evento['nota']): ?><p><?= h($evento['nota']) ?></p><?php endif; ?></div><?php endforeach; ?></section>
<?php elseif (isset($_GET['novo'])): ?>
<div class="cabecalho"><div><span class="etiqueta">OCORRÊNCIAS</span><h1>Nova ocorrência</h1><p>Registre o problema para começar a triagem.</p></div></div><section class="cartao estreito"><form method="post" class="formulario"><?php campo_token(); ?><input type="hidden" name="acao" value="criar"><label>Ambiente<select name="ambiente_id" required><option value="">Escolha</option><?php foreach ($ambientes as $ambiente): ?><option value="<?= (int) $ambiente['id'] ?>"><?= h($ambiente['nome']) ?></option><?php endforeach; ?></select></label><label>Categoria<select name="categoria_id" required><option value="">Escolha</option><?php foreach ($categorias as $categoria): ?><option value="<?= (int) $categoria['id'] ?>"><?= h($categoria['nome']) ?></option><?php endforeach; ?></select></label><label>Título<input name="titulo" maxlength="180" required></label><label>Descrição<textarea name="descricao" required></textarea></label><label>Urgência percebida<select name="prioridade"><option value="baixa">Baixa</option><option value="media" selected>Média</option><option value="alta">Alta</option><option value="urgente">Urgente</option></select></label><button class="botao">Registrar ocorrência</button></form></section>
<?php else: ?>
<?php $situacao = (string) ($_GET['situacao'] ?? ''); $busca = trim((string) ($_GET['busca'] ?? '')); $sql = 'SELECT o.*, a.nome AS ambiente FROM ocorrencias o JOIN ambientes a ON a.id = o.ambiente_id WHERE o.escola_id = ?'; $params = [$escola]; if (in_array($situacao, ['aberta','em_triagem','encaminhada','resolvida','encerrada','duplicada','nao_procede'], true)) { $sql .= ' AND o.status = ?'; $params[] = $situacao; } if ($busca !== '') { $sql .= ' AND o.titulo LIKE ?'; $params[] = '%' . $busca . '%'; } $sql .= ' ORDER BY o.id DESC'; $itens = listar($sql, $params); ?>
<div class="cabecalho"><div><span class="etiqueta">OPERAÇÃO</span><h1>Ocorrências</h1><p>Registros, triagem e acompanhamento.</p></div><a class="botao" href="ocorrencias.php?novo=1">+ Nova ocorrência</a></div><section class="cartao"><form method="get" class="filtros"><input name="busca" placeholder="Buscar pelo título" value="<?= h($busca) ?>"><select name="situacao"><option value="">Todas as situações</option><?php foreach (['aberta','em_triagem','encaminhada','resolvida','encerrada','duplicada','nao_procede'] as $opcao): ?><option value="<?= $opcao ?>" <?= $situacao === $opcao ? 'selected' : '' ?>><?= h(str_replace('_', ' ', ucfirst($opcao))) ?></option><?php endforeach; ?></select><button class="botao secundario">Filtrar</button></form><?php if (!$itens): ?><p class="vazio">Nenhuma ocorrência encontrada.</p><?php else: ?><div class="tabela-rolagem"><table><thead><tr><th>Protocolo</th><th>Título</th><th>Ambiente</th><th>Prioridade</th><th>Situação</th><th>Data</th></tr></thead><tbody><?php foreach ($itens as $linha): ?><tr><td><a href="ocorrencias.php?id=<?= (int) $linha['id'] ?>"><?= h(protocolo((int) $linha['id'])) ?></a></td><td><?= h($linha['titulo']) ?></td><td><?= h($linha['ambiente']) ?></td><td><?= h(ucfirst($linha['prioridade'])) ?></td><td><span class="selo"><?= h(str_replace('_', ' ', $linha['status'])) ?></span></td><td><?= h(data_br($linha['criada_em'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php endif; require __DIR__ . '/rodape.php'; ?>
