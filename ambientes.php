<?php
require_once __DIR__ . '/funcoes.php';
$escola = escola_do_gestor();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    conferir_token();
    try {
        $acao = (string) ($_POST['acao'] ?? '');
        $id = inteiro_post('id');
        if ($acao === 'salvar') {
            $nome = obrigatorio('nome', 150);
            if ($id) {
                if (!pertence('ambientes', $id, $escola)) throw new RuntimeException('Ambiente não encontrado.');
                executar('UPDATE ambientes SET nome = ? WHERE id = ? AND escola_id = ?', [$nome, $id, $escola]);
            } else executar('INSERT INTO ambientes (escola_id, nome) VALUES (?, ?)', [$escola, $nome]);
            aviso('Ambiente salvo.');
        } elseif ($acao === 'alternar') {
            $alvo = pertence('ambientes', $id, $escola);
            if (!$alvo) throw new RuntimeException('Ambiente não encontrado.');
            executar('UPDATE ambientes SET ativo = ? WHERE id = ? AND escola_id = ?', [$alvo['ativo'] ? 0 : 1, $id, $escola]);
            aviso('Situação alterada.');
        } else throw new RuntimeException('Ação inválida.');
        ir('ambientes.php');
    } catch (RuntimeException $erro) { falha($erro->getMessage()); }
}
$editar = isset($_GET['editar']) ? pertence('ambientes', (int) $_GET['editar'], $escola) : null;
$itens = listar('SELECT * FROM ambientes WHERE escola_id = ? ORDER BY nome', [$escola]);
$titulo = 'Ambientes';
require __DIR__ . '/topo.php';
?>
<div class="cabecalho"><div><span class="etiqueta">ESTRUTURA</span><h1>Ambientes</h1><p>Locais onde a manutenção pode ser solicitada.</p></div></div>
<div class="grade-duas"><section class="cartao"><h2><?= $editar ? 'Editar ambiente' : 'Novo ambiente' ?></h2><form method="post" class="formulario"><?php campo_token(); ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>"><label>Nome do local<input name="nome" required maxlength="150" placeholder="Ex.: Sala 03" value="<?= h($editar['nome'] ?? '') ?>"></label><div class="acoes"><button class="botao" type="submit">Salvar</button><?php if ($editar): ?><a class="botao secundario" href="ambientes.php">Cancelar</a><?php endif; ?></div></form></section><section class="cartao"><h2>Locais cadastrados</h2><?php if (!$itens): ?><p class="vazio">Nenhum ambiente cadastrado.</p><?php else: ?><div class="lista"><?php foreach ($itens as $item): ?><div class="item-lista"><div><strong><?= h($item['nome']) ?></strong><small><?= $item['ativo'] ? 'Ativo' : 'Inativo' ?></small></div><div class="acoes"><a href="ambientes.php?editar=<?= (int) $item['id'] ?>">Editar</a><form method="post"><?php campo_token(); ?><input type="hidden" name="acao" value="alternar"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button type="submit" class="link"><?= $item['ativo'] ? 'Desativar' : 'Ativar' ?></button></form></div></div><?php endforeach; ?></div><?php endif; ?></section></div>
<?php require __DIR__ . '/rodape.php'; ?>
