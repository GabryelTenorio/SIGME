<?php
require_once __DIR__ . '/funcoes.php';
if (usuario()) ir('index.php');
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    conferir_token();
    $pessoa = buscar('SELECT * FROM usuarios WHERE email = ? AND ativo = 1', [trim((string) ($_POST['email'] ?? ''))]);
    if ($pessoa && $pessoa['perfil'] === 'gestor' && password_verify((string) ($_POST['senha'] ?? ''), (string) $pessoa['senha'])) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $pessoa['id'];
        ir('index.php');
    }
    $erro = 'E-mail ou senha incorretos para o perfil de gestor.';
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Entrar · SIGME</title><link rel="stylesheet" href="estilo.css"></head>
<body class="acesso"><main class="caixa-acesso"><div class="marca marca-acesso"><span class="marca-icone">S</span><span><strong>SIGME</strong><small>Manutenção escolar</small></span></div><h1>Entrar</h1>
<?php mostrar_aviso(); if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif; ?>
<form method="post" class="formulario"><?php campo_token(); ?><label>E-mail ou usuário local<input type="text" name="email" autocomplete="username" required></label><label>Senha<input type="password" name="senha" autocomplete="current-password" required></label><button class="botao" type="submit">Entrar</button></form></main></body></html>
