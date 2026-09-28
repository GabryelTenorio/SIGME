<?php
require_once __DIR__ . '/funcoes.php';
$gestor = exigir_gestor();
$escola = escola_do_gestor();
$ambientes = (int) buscar('SELECT COUNT(*) AS total FROM ambientes WHERE escola_id = ?', [$escola])['total'];
$abertas = (int) buscar("SELECT COUNT(*) AS total FROM ocorrencias WHERE escola_id = ? AND status IN ('aberta','em_triagem','encaminhada')", [$escola])['total'];
$pendentes = (int) buscar("SELECT COUNT(*) AS total FROM ordens_servico WHERE escola_id = ? AND status = 'aguardando_aprovacao'", [$escola])['total'];
$concluidas = (int) buscar("SELECT COUNT(*) AS total FROM ordens_servico WHERE escola_id = ? AND status = 'concluida'", [$escola])['total'];
$titulo = 'Painel';
require __DIR__ . '/topo.php';
?>
<div class="cabecalho"><div><span class="etiqueta">VISÃO GERAL</span><h1>Olá, <?= h(explode(' ', $gestor['nome'])[0]) ?></h1><p>Veja o que está acontecendo na sua escola.</p></div><a class="botao" href="ocorrencias.php?novo=1">+ Nova ocorrência</a></div>
<div class="grade-resumo"><div class="cartao-resumo"><small>Ocorrências abertas</small><strong><?= $abertas ?></strong><a href="ocorrencias.php">Ver ocorrências →</a></div><div class="cartao-resumo"><small>OS aguardando aprovação</small><strong><?= $pendentes ?></strong><a href="ordens.php">Ver ordens →</a></div><div class="cartao-resumo"><small>OS concluídas</small><strong><?= $concluidas ?></strong><a href="ordens.php">Ver ordens →</a></div><div class="cartao-resumo"><small>Ambientes</small><strong><?= $ambientes ?></strong><a href="ambientes.php">Ver ambientes →</a></div></div>
<?php require __DIR__ . '/rodape.php'; ?>
