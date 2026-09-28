<?php
require_once __DIR__ . '/config.php';
session_start();

// A conexão é criada uma vez e usada pelas páginas.
function banco(): PDO
{
    static $conexao = null;
    if ($conexao === null) {
        try {
            $conexao = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $erro) {
            http_response_code(500);
            exit('Não foi possível conectar ao banco. Confira o arquivo config.php e importe banco.sql.');
        }
    }
    return $conexao;
}

function buscar(string $sql, array $dados = []): ?array
{
    $consulta = banco()->prepare($sql);
    $consulta->execute($dados);
    $linha = $consulta->fetch();
    return $linha ?: null;
}

function listar(string $sql, array $dados = []): array
{
    $consulta = banco()->prepare($sql);
    $consulta->execute($dados);
    return $consulta->fetchAll();
}

function executar(string $sql, array $dados = []): int
{
    $consulta = banco()->prepare($sql);
    $consulta->execute($dados);
    return $consulta->rowCount();
}

function h($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ir(string $pagina): void
{
    header('Location: ' . $pagina);
    exit;
}

function aviso(string $texto, string $tipo = 'ok'): void
{
    $_SESSION['aviso'] = [$texto, $tipo];
}

function mostrar_aviso(): void
{
    if (!empty($_SESSION['aviso'])) {
        [$texto, $tipo] = $_SESSION['aviso'];
        echo '<div class="aviso ' . h($tipo) . '">' . h($texto) . '</div>';
        unset($_SESSION['aviso']);
    }
}

function token(): string
{
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['token'];
}

function campo_token(): void
{
    echo '<input type="hidden" name="token" value="' . h(token()) . '">';
}

function conferir_token(): void
{
    if (!hash_equals(token(), (string) ($_POST['token'] ?? ''))) {
        http_response_code(403);
        exit('Formulário inválido. Atualize a página e tente de novo.');
    }
}

function usuario(): ?array
{
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }
    return buscar('SELECT * FROM usuarios WHERE id = ? AND ativo = 1', [(int) $_SESSION['usuario_id']]);
}

function exigir_gestor(): array
{
    $pessoa = usuario();
    if (!$pessoa || $pessoa['perfil'] !== 'gestor') {
        ir('login.php');
    }
    return $pessoa;
}

function escola_do_gestor(): int
{
    $pessoa = exigir_gestor();
    return (int) $pessoa['escola_id'];
}

function pertence(string $tabela, int $id, int $escola): ?array
{
    $permitidas = ['ambientes', 'categorias', 'ocorrencias', 'ordens_servico'];
    if (!in_array($tabela, $permitidas, true)) {
        exit('Tabela inválida.');
    }
    return buscar("SELECT * FROM $tabela WHERE id = ? AND escola_id = ?", [$id, $escola]);
}

function historico(string $tipo, int $registroId, string $acao, string $nota = ''): void
{
    executar('INSERT INTO historico (escola_id, tipo, registro_id, usuario_id, acao, nota) VALUES (?, ?, ?, ?, ?, ?)',
        [escola_do_gestor(), $tipo, $registroId, (int) $_SESSION['usuario_id'], $acao, $nota]);
}

function data_br($valor): string
{
    return $valor ? date('d/m/Y H:i', strtotime($valor)) : '—';
}

function obrigatorio(string $nome, int $maximo = 255): string
{
    $valor = trim((string) ($_POST[$nome] ?? ''));
    if ($valor === '' || mb_strlen($valor) > $maximo) {
        throw new RuntimeException('Preencha o campo ' . str_replace('_', ' ', $nome) . '.');
    }
    return $valor;
}

function inteiro_post(string $nome): int
{
    return filter_var($_POST[$nome] ?? null, FILTER_VALIDATE_INT) ?: 0;
}

function falha(string $mensagem): void
{
    aviso($mensagem, 'erro');
    ir($_SERVER['REQUEST_URI']);
}

function protocolo(int $id): string
{
    return 'SIG-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

function codigo_os(int $id): string
{
    return 'OS-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}
