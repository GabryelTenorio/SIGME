<?php
require_once __DIR__ . '/funcoes.php';
$escola = escola_do_gestor();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    conferir_token();
    try {
        $nome = obrigatorio('nome', 150);
        $endereco = trim((string) ($_POST['endereco'] ?? ''));
        $telefone = trim((string) ($_POST['telefone'] ?? ''));
        if (mb_strlen($endereco) > 255 || mb_strlen($telefone) > 30) throw new RuntimeException('Endereço ou telefone muito longo.');
        executar('UPDATE escolas SET nome = ?, endereco = ?, telefone = ? WHERE id = ?', [$nome, $endereco, $telefone, $escola]);
        aviso('Dados da escola salvos.');
        ir('escola.php');
    } catch (RuntimeException $erro) { falha($erro->getMessage()); }
}
$dados = buscar('SELECT * FROM escolas WHERE id = ?', [$escola]);
$titulo = 'Minha escola';
require __DIR__ . '/topo.php';
?>
<div class="cabecalho"><div><span class="etiqueta">ADMINISTRAÇÃO</span><h1>Minha escola</h1><p>Dados básicos da unidade escolar.</p></div></div>
<section class="cartao estreito"><h2>Dados da escola</h2><form method="post" class="formulario"><?php campo_token(); ?><label>Nome<input name="nome" maxlength="150" required value="<?= h($dados['nome']) ?>"></label><label>Endereço<input name="endereco" maxlength="255" value="<?= h($dados['endereco']) ?>"></label><label>Telefone<input name="telefone" maxlength="30" value="<?= h($dados['telefone']) ?>"></label><button class="botao" type="submit">Salvar</button></form></section>
<?php require __DIR__ . '/rodape.php'; ?>
