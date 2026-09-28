<?php
require_once __DIR__ . '/funcoes.php';
$jaInstalado = (int) (buscar('SELECT COUNT(*) AS total FROM usuarios')['total'] ?? 0) > 0;
if ($jaInstalado) {
    ir('login.php');
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    conferir_token();
    try {
        $nome = obrigatorio('nome', 150);
        $email = trim((string) ($_POST['email'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');
        $emailValido = filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/^[^@\s]+@local$/i', $email);
        if (!$emailValido || strlen($email) > 190 || strlen($senha) < 8) {
            throw new RuntimeException('Informe um e-mail válido e uma senha de pelo menos 8 caracteres.');
        }
        banco()->beginTransaction();
        executar('INSERT INTO usuarios (escola_id, nome, email, senha, perfil) VALUES (?, ?, ?, ?, ?)',
            [1, $nome, $email, password_hash($senha, PASSWORD_DEFAULT), 'gestor']);
        banco()->commit();
        aviso('Instalação concluída. Entre com seu e-mail e senha.');
        ir('login.php');
    } catch (Throwable $ex) {
        if (banco()->inTransaction()) banco()->rollBack();
        $erro = $ex instanceof PDOException ? 'Não foi possível criar a conta. Verifique o banco.' : $ex->getMessage();
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Instalar · SIGME</title><link rel="stylesheet" href="estilo.css"></head>
<body class="acesso"><main class="caixa-acesso"><div class="marca marca-acesso"><span class="marca-icone">S</span><span><strong>SIGME</strong><small>Manutenção escolar</small></span></div><h1>Primeiro acesso</h1><p>Defina o acesso do gestor da escola única. O nome da escola pode ser alterado depois em Minha escola.</p>
<?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif; ?>
<form method="post" class="formulario"><?php campo_token(); ?><label>E-mail ou usuário local<input type="text" name="email" required></label><label>Nome do gestor<input name="nome" required maxlength="150"></label><label>Senha<input type="password" name="senha" required minlength="8"></label><button class="botao" type="submit">Definir acesso</button></form></main></body></html>
