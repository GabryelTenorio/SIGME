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
                if (!pertence('categorias', $id, $escola)) throw new RuntimeException('Categoria não encontrada.');
                executar('UPDATE categorias SET nome = ? WHERE id = ? AND escola_id = ?', [$nome, $id, $escola]);
            } else executar('INSERT INTO categorias (escola_id, nome) VALUES (?, ?)', [$escola, $nome]);
            aviso('Categoria salva.');
        } elseif ($acao === 'alternar') {
            $alvo = pertence('categorias', $id, $escola);
            if (!$alvo) throw new RuntimeException('Categoria não encontrada.');
            executar('UPDATE categorias SET ativo = ? WHERE id = ? AND escola_id = ?', [$alvo['ativo'] ? 0 : 1, $id, $escola]);
            aviso('Disponibilidade alterada.');
        } else throw new RuntimeException('Ação inválida.');
        ir('categorias.php');
    } catch (RuntimeException $erro) { falha($erro->getMessage()); }
}
$editar = isset($_GET['editar']) ? pertence('categorias', (int) $_GET['editar'], $escola) : null;
$itens = listar('SELECT * FROM categorias WHERE escola_id = ? ORDER BY nome', [$escola]);
$titulo = 'Categorias';
require __DIR__ . '/topo.php';
?>
<div class="cabecalho"><div><span class="etiqueta">ESTRUTURA</span><h1>Categorias</h1><p>Tipos de problema disponíveis para esta escola.</p></div></div>
<div class="grade-duas"><section class="cartao"><h2><?= $editar ? 'Editar categoria' : 'Nova categoria' ?></h2><form method="post" class="formulario"><?php campo_token(); ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>"><label>Nome da categoria<input name="nome" required maxlength="150" placeholder="Ex.: Elétrica" value="<?= h($editar['nome'] ?? '') ?>"></label><div class="acoes"><button class="botao" type="submit">Salvar</button><?php if ($editar): ?><a class="botao secundario" href="categorias.php">Cancelar</a><?php endif; ?></div></form></section><section class="cartao"><h2>Categorias cadastradas</h2><?php if (!$itens): ?><p class="vazio">Nenhuma categoria cadastrada.</p><?php else: ?><div class="lista"><?php foreach ($itens as $item): ?><div class="item-lista"><div><strong><?= h($item['nome']) ?></strong><small><?= $item['ativo'] ? 'Disponível' : 'Indisponível' ?></small></div><div class="acoes"><a href="categorias.php?editar=<?= (int) $item['id'] ?>">Editar</a><form method="post"><?php campo_token(); ?><input type="hidden" name="acao" value="alternar"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button type="submit" class="link"><?= $item['ativo'] ? 'Desativar' : 'Ativar' ?></button></form></div></div><?php endforeach; ?></div><?php endif; ?></section></div>
<?php require __DIR__ . '/rodape.php'; ?>
