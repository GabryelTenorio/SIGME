<?php
$gestor = exigir_gestor();
$paginaAtual = basename($_SERVER['PHP_SELF']);
$links = [
    'index.php' => 'Painel', 'escola.php' => 'Minha escola',
    'ambientes.php' => 'Ambientes', 'categorias.php' => 'Categorias',
    'ocorrencias.php' => 'Ocorrências', 'ordens.php' => 'Ordens de serviço'
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo ?? 'SIGME') ?> · SIGME</title>
    <link rel="stylesheet" href="estilo.css">
    <script src="script.js" defer></script>
</head>
<body>
<div class="estrutura">
    <aside class="menu" id="menu">
        <a class="marca" href="index.php"><span class="marca-icone">S</span><span><strong>SIGME</strong><small>Manutenção escolar</small></span></a>
        <div class="menu-titulo">GESTÃO</div>
        <nav aria-label="Menu principal">
            <?php foreach ($links as $arquivo => $nome): ?>
                <a class="<?= $paginaAtual === $arquivo ? 'ativo' : '' ?>" href="<?= h($arquivo) ?>">
                    <?= h($nome) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="menu-rodape"><a href="sair.php">Sair</a></div>
    </aside>
    <div class="conteudo">
        <header class="barra"><button class="botao-menu" type="button" id="abrir-menu" aria-label="Abrir menu">☰</button><span>Escolar</span></header>
        <main class="principal">
            <?php mostrar_aviso(); ?>
