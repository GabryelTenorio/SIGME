<?php
require_once __DIR__ . '/funcoes.php';
$gestor = exigir_gestor();
$escola = escola_do_gestor();

// Primeiro tratamos o botão enviado; depois mostramos a lista ou os detalhes.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    conferir_token();
    try {
        $acao = (string) ($_POST['acao'] ?? '');
        $id = inteiro_post('id');
        if ($acao === 'criar') {
            $ocorrencia = pertence('ocorrencias', inteiro_post('ocorrencia_id'), $escola);
            if (!$ocorrencia || $ocorrencia['status'] !== 'encaminhada') throw new RuntimeException('Escolha uma ocorrência encaminhada.');
            $ativa = buscar("SELECT id FROM ordens_servico WHERE ocorrencia_id = ? AND status NOT IN ('concluida','cancelada','rejeitada')", [$ocorrencia['id']]);
            if ($ativa) throw new RuntimeException('Já existe uma ordem ativa para esta ocorrência.');
            $tituloNovo = obrigatorio('titulo', 180);
            $descricao = obrigatorio('descricao', 5000);
            $prazo = trim((string) ($_POST['prazo'] ?? '')) ?: null;
            $fornecedor = trim((string) ($_POST['fornecedor'] ?? '')) ?: null;
            executar('INSERT INTO ordens_servico (escola_id, ocorrencia_id, responsavel_id, titulo, descricao, prazo, fornecedor) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$escola, $ocorrencia['id'], $gestor['id'], $tituloNovo, $descricao, $prazo, $fornecedor]);
            $novoId = (int) banco()->lastInsertId();
            historico('ordem', $novoId, 'Ordem criada');
            aviso('Ordem de serviço criada.');
            ir('ordens.php?id=' . $novoId);
        }

        $ordem = pertence('ordens_servico', $id, $escola);
        if (!$ordem) throw new RuntimeException('Ordem não encontrada.');
        $status = $ordem['status'];
        if ($acao === 'aprovar' && $status === 'aguardando_aprovacao') {
            executar("UPDATE ordens_servico SET status = 'aprovada' WHERE id = ?", [$id]);
            historico('ordem', $id, 'Ordem aprovada');
        } elseif ($acao === 'rejeitar' && $status === 'aguardando_aprovacao') {
            $motivo = obrigatorio('nota', 3000);
            executar("UPDATE ordens_servico SET status = 'rejeitada' WHERE id = ?", [$id]);
            historico('ordem', $id, 'Ordem rejeitada', $motivo);
        } elseif ($acao === 'iniciar' && $status === 'aprovada') {
            executar("UPDATE ordens_servico SET status = 'em_execucao', responsavel_id = ? WHERE id = ?", [$gestor['id'], $id]);
            historico('ordem', $id, 'Execução iniciada');
        } elseif ($acao === 'emergencia' && $status === 'aguardando_aprovacao') {
            $ocorrencia = pertence('ocorrencias', (int) $ordem['ocorrencia_id'], $escola);
            if (!$ocorrencia || $ocorrencia['prioridade'] !== 'urgente') throw new RuntimeException('Emergência exige prioridade urgente.');
            $motivo = obrigatorio('nota', 3000);
            executar("UPDATE ordens_servico SET status = 'em_execucao', responsavel_id = ?, motivo_emergencia = ? WHERE id = ?", [$gestor['id'], $motivo, $id]);
            historico('ordem', $id, 'Atendimento emergencial iniciado', $motivo);
        } elseif ($acao === 'ratificar' && $ordem['motivo_emergencia'] && !$ordem['emergencia_ratificada']) {
            executar('UPDATE ordens_servico SET emergencia_ratificada = 1 WHERE id = ?', [$id]);
            historico('ordem', $id, 'Emergência ratificada');
        } elseif ($acao === 'diagnostico' && $status === 'em_execucao') {
            $nota = obrigatorio('nota', 5000);
            executar('UPDATE ordens_servico SET diagnostico = ? WHERE id = ?', [$nota, $id]);
            historico('ordem', $id, 'Diagnóstico registrado', $nota);
        } elseif ($acao === 'concluir' && $status === 'em_execucao') {
            // Concluir a OS também deixa a ocorrência pronta para encerramento.
            if (!$ordem['diagnostico']) throw new RuntimeException('Registre o diagnóstico antes de concluir.');
            if ($ordem['motivo_emergencia'] && !$ordem['emergencia_ratificada']) throw new RuntimeException('Ratifique a emergência antes de concluir.');
            $solucao = obrigatorio('nota', 5000);
            banco()->beginTransaction();
            executar("UPDATE ordens_servico SET status = 'concluida', solucao = ?, concluida_em = NOW() WHERE id = ?", [$solucao, $id]);
            executar("UPDATE ocorrencias SET status = 'resolvida' WHERE id = ? AND escola_id = ?", [$ordem['ocorrencia_id'], $escola]);
            historico('ordem', $id, 'Ordem concluída', $solucao);
            historico('ocorrencia', (int) $ordem['ocorrencia_id'], 'Problema resolvido pela OS', codigo_os($id));
            banco()->commit();
        } elseif ($acao === 'cancelar' && !in_array($status, ['concluida','cancelada','rejeitada'], true)) {
            $motivo = obrigatorio('nota', 3000);
            executar("UPDATE ordens_servico SET status = 'cancelada' WHERE id = ?", [$id]);
            historico('ordem', $id, 'Ordem cancelada', $motivo);
        } elseif ($acao === 'atualizacao' && $status === 'em_execucao') {
            $descricao = obrigatorio('descricao', 3000);
            executar("INSERT INTO registros_os (ordem_id, tipo, descricao) VALUES (?, 'atualizacao', ?)", [$id, $descricao]);
            historico('ordem', $id, 'Atualização registrada', $descricao);
        } else throw new RuntimeException('Esta ação não é permitida na situação atual.');
        aviso('Ordem atualizada.');
        ir('ordens.php?id=' . $id);
    } catch (Throwable $erro) {
        if (banco()->inTransaction()) banco()->rollBack();
        falha($erro instanceof PDOException ? 'Não foi possível salvar. Confira os campos.' : $erro->getMessage());
    }
}

$id = (int) ($_GET['id'] ?? 0);
$ordem = $id ? buscar('SELECT os.*, o.titulo AS ocorrencia_titulo, o.prioridade, u.nome AS responsavel FROM ordens_servico os JOIN ocorrencias o ON o.id = os.ocorrencia_id LEFT JOIN usuarios u ON u.id = os.responsavel_id WHERE os.id = ? AND os.escola_id = ?', [$id, $escola]) : null;
if ($id && !$ordem) { http_response_code(404); exit('Ordem não encontrada.'); }
$titulo = $ordem ? codigo_os($id) : 'Ordens de serviço';
require __DIR__ . '/topo.php';
?>
<?php if ($ordem): ?>
<div class="cabecalho"><div><span class="etiqueta"><?= h(codigo_os($id)) ?></span><h1><?= h($ordem['titulo']) ?></h1><p>Ocorrência <a href="ocorrencias.php?id=<?= (int) $ordem['ocorrencia_id'] ?>"><?= h(protocolo((int) $ordem['ocorrencia_id'])) ?></a> · <?= h(data_br($ordem['criada_em'])) ?></p></div><span class="selo"><?= h(str_replace('_', ' ', $ordem['status'])) ?></span></div>
<div class="grade-duas"><section class="cartao"><h2>Serviço</h2><p class="texto-longo"><?= nl2br(h($ordem['descricao'])) ?></p><div class="detalhes"><div><small>Responsável</small><strong><?= h($ordem['responsavel'] ?: 'A definir') ?></strong></div><div><small>Prazo</small><strong><?= h($ordem['prazo'] ?: '—') ?></strong></div><div><small>Fornecedor externo</small><strong><?= h($ordem['fornecedor'] ?: '—') ?></strong></div></div><?php if ($ordem['diagnostico']): ?><p><strong>Diagnóstico:</strong> <?= h($ordem['diagnostico']) ?></p><?php endif; ?><?php if ($ordem['solucao']): ?><p><strong>Solução:</strong> <?= h($ordem['solucao']) ?></p><?php endif; ?><?php if ($ordem['motivo_emergencia']): ?><p><strong>Emergência:</strong> <?= h($ordem['motivo_emergencia']) ?> · <?= $ordem['emergencia_ratificada'] ? 'ratificada' : 'pendente de ratificação' ?></p><?php endif; ?>
<h3>Atualizações</h3><?php $registros = listar("SELECT * FROM registros_os WHERE ordem_id = ? AND tipo = 'atualizacao' ORDER BY id DESC", [$id]); if (!$registros): ?><p class="vazio">Nenhuma atualização ainda.</p><?php endif; foreach ($registros as $registro): ?><div class="linha-historico"><strong><?= h($registro['descricao']) ?></strong><small><?= h(data_br($registro['criado_em'])) ?></small></div><?php endforeach; ?>
</section>
<section class="cartao"><h2>Ações</h2><div class="pilha-acoes">
<?php if ($ordem['status'] === 'aguardando_aprovacao'): ?><div class="acoes"><form method="post"><?php campo_token(); ?><input type="hidden" name="acao" value="aprovar"><input type="hidden" name="id" value="<?= $id ?>"><button class="botao">Aprovar OS</button></form></div><details><summary>Rejeitar OS</summary><form method="post" class="formulario compacto"><?php campo_token(); ?><input type="hidden" name="acao" value="rejeitar"><input type="hidden" name="id" value="<?= $id ?>"><label>Motivo<textarea name="nota" required></textarea></label><button class="botao perigo">Rejeitar</button></form></details><?php if ($ordem['prioridade'] === 'urgente'): ?><details><summary>Iniciar como emergência</summary><form method="post" class="formulario compacto"><?php campo_token(); ?><input type="hidden" name="acao" value="emergencia"><input type="hidden" name="id" value="<?= $id ?>"><label>Motivo<textarea name="nota" required></textarea></label><button class="botao perigo">Iniciar emergência</button></form></details><?php endif; ?><?php endif; ?>
<?php if ($ordem['status'] === 'aprovada'): ?><form method="post"><?php campo_token(); ?><input type="hidden" name="acao" value="iniciar"><input type="hidden" name="id" value="<?= $id ?>"><button class="botao">Iniciar execução</button></form><?php endif; ?>
<?php if ($ordem['motivo_emergencia'] && !$ordem['emergencia_ratificada']): ?><form method="post"><?php campo_token(); ?><input type="hidden" name="acao" value="ratificar"><input type="hidden" name="id" value="<?= $id ?>"><button class="botao">Ratificar emergência</button></form><?php endif; ?>
<?php if ($ordem['status'] === 'em_execucao'): ?><form method="post" class="formulario"><?php campo_token(); ?><input type="hidden" name="acao" value="diagnostico"><input type="hidden" name="id" value="<?= $id ?>"><label>Diagnóstico<textarea name="nota" required><?= h($ordem['diagnostico']) ?></textarea></label><button class="botao secundario">Salvar diagnóstico</button></form><?php if ($ordem['diagnostico']): ?><form method="post" class="formulario"><?php campo_token(); ?><input type="hidden" name="acao" value="concluir"><input type="hidden" name="id" value="<?= $id ?>"><label>Solução aplicada<textarea name="nota" required></textarea></label><button class="botao">Concluir OS</button></form><?php endif; ?><?php endif; ?>
<?php if ($ordem['status'] === 'em_execucao'): ?>
<details><summary>Adicionar atualização</summary><form method="post" class="formulario compacto"><?php campo_token(); ?><input type="hidden" name="acao" value="atualizacao"><input type="hidden" name="id" value="<?= $id ?>"><label>Atualização<textarea name="descricao" required></textarea></label><button class="botao secundario">Registrar</button></form></details>
<?php endif; ?>
<?php if (!in_array($ordem['status'], ['concluida','cancelada','rejeitada'], true)): ?><details><summary>Cancelar ordem</summary><form method="post" class="formulario compacto"><?php campo_token(); ?><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="id" value="<?= $id ?>"><label>Motivo<textarea name="nota" required></textarea></label><button class="botao perigo">Cancelar OS</button></form></details><?php endif; ?>
</div></section></div><section class="cartao"><h2>Histórico</h2><?php foreach (listar("SELECT h.*, u.nome FROM historico h JOIN usuarios u ON u.id = h.usuario_id WHERE h.tipo = 'ordem' AND h.registro_id = ? AND h.escola_id = ? ORDER BY h.id DESC", [$id, $escola]) as $evento): ?><div class="linha-historico"><strong><?= h($evento['acao']) ?></strong><small><?= h(data_br($evento['criado_em'])) ?> · <?= h($evento['nome']) ?></small><?php if ($evento['nota']): ?><p><?= h($evento['nota']) ?></p><?php endif; ?></div><?php endforeach; ?></section>
<?php elseif (isset($_GET['nova'])): ?>
<?php $ocorrencia = pertence('ocorrencias', (int) $_GET['nova'], $escola); if (!$ocorrencia || $ocorrencia['status'] !== 'encaminhada'): ?><div class="aviso erro">Ocorrência não está encaminhada.</div><?php else: ?>
<div class="cabecalho"><div><span class="etiqueta">ORDEM DE SERVIÇO</span><h1>Nova ordem</h1><p>Para <?= h(protocolo((int) $ocorrencia['id'])) ?> · <?= h($ocorrencia['titulo']) ?></p></div></div><section class="cartao estreito"><form method="post" class="formulario"><?php campo_token(); ?><input type="hidden" name="acao" value="criar"><input type="hidden" name="ocorrencia_id" value="<?= (int) $ocorrencia['id'] ?>"><label>Título<input name="titulo" required maxlength="180" value="<?= h($ocorrencia['titulo']) ?>"></label><label>Serviço necessário<textarea name="descricao" required><?= h($ocorrencia['descricao']) ?></textarea></label><p>Responsável: <?= h($gestor['nome']) ?></p><label>Prazo<input type="date" name="prazo"></label><label>Fornecedor externo, se houver<input name="fornecedor" maxlength="180"></label><button class="botao">Criar OS</button></form></section><?php endif; ?>
<?php else: ?>
<?php $situacao = (string) ($_GET['situacao'] ?? ''); $sql = 'SELECT os.*, u.nome AS responsavel FROM ordens_servico os LEFT JOIN usuarios u ON u.id = os.responsavel_id WHERE os.escola_id = ?'; $params = [$escola]; if (in_array($situacao, ['aguardando_aprovacao','aprovada','em_execucao','concluida','rejeitada','cancelada'], true)) { $sql .= ' AND os.status = ?'; $params[] = $situacao; } $sql .= ' ORDER BY os.id DESC'; $itens = listar($sql, $params); ?>
<div class="cabecalho"><div><span class="etiqueta">OPERAÇÃO</span><h1>Ordens de serviço</h1><p>Aprovação, execução e conclusão.</p></div></div><section class="cartao"><form method="get" class="filtros"><select name="situacao"><option value="">Todas as situações</option><?php foreach (['aguardando_aprovacao','aprovada','em_execucao','concluida','rejeitada','cancelada'] as $opcao): ?><option value="<?= $opcao ?>" <?= $situacao === $opcao ? 'selected' : '' ?>><?= h(str_replace('_', ' ', ucfirst($opcao))) ?></option><?php endforeach; ?></select><button class="botao secundario">Filtrar</button></form><?php if (!$itens): ?><p class="vazio">Nenhuma ordem encontrada. Encaminhe uma ocorrência para criar a primeira.</p><?php else: ?><div class="tabela-rolagem"><table><thead><tr><th>Código</th><th>Serviço</th><th>Responsável</th><th>Prazo</th><th>Situação</th></tr></thead><tbody><?php foreach ($itens as $linha): ?><tr><td><a href="ordens.php?id=<?= (int) $linha['id'] ?>"><?= h(codigo_os((int) $linha['id'])) ?></a></td><td><?= h($linha['titulo']) ?></td><td><?= h($linha['responsavel'] ?: 'A definir') ?></td><td><?= h($linha['prazo'] ?: '—') ?></td><td><span class="selo"><?= h(str_replace('_', ' ', $linha['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php endif; require __DIR__ . '/rodape.php'; ?>
