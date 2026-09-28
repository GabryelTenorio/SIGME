# SIGME v1 Simples

Sistema de Gestão de Manutenção Escolar para **uma escola e um gestor**, feito com PHP, HTML, CSS, JavaScript e MySQL/MariaDB. Esta versão roda localmente no XAMPP e mantém os arquivos da aplicação em uma única pasta.

## Instalação no XAMPP

1. Instale o XAMPP com PHP 8.1 ou superior e inicie **Apache** e **MySQL** no painel de controle.
2. Baixe este repositório e coloque seus arquivos em `C:\xampp\htdocs\sigme-simples`.
3. Abra `http://127.0.0.1/phpmyadmin` e importe `banco.sql`. Ele cria o banco `sigme_escola_unica` e uma escola inicial.
4. Confira `config.php`. Ele já usa `root` sem senha, como em uma instalação padrão do XAMPP. Se o seu banco tiver outra senha, ajuste o arquivo apenas no seu computador.
5. Abra `http://127.0.0.1/sigme-simples/instalar.php` e defina o nome, o usuário e a senha do gestor. Depois, acesse `http://127.0.0.1/sigme-simples/`.

O banco desta edição é separado do banco da versão anterior em Laravel. O repositório contém apenas o esquema SQL e a escola inicial: **não contém usuários, senhas ou dados da instalação local**.

## O que o gestor pode fazer

- Ver o painel e editar os dados da escola única.
- Cadastrar e desativar ambientes e categorias.
- Registrar ocorrências, fazer triagem, confirmar prioridade, encaminhar, marcar como duplicada ou não procedente, encerrar e reabrir.
- Criar ordens de serviço, aprovar ou rejeitar, iniciar a execução, registrar diagnóstico e atualizações, concluir ou cancelar.
- Consultar o histórico das ocorrências e das ordens.

Esta edição não inclui cadastro de usuários ou escolas, anexos, notificações, autenticação em dois fatores, relatórios, controle de custos e tempo, nem troca de senha pelo site. A senha é salva com hash e as consultas ao banco usam parâmetros.

## Arquivos principais

- `banco.sql`: tabelas do banco.
- `config.php`: conexão com o banco.
- `funcoes.php`: funções comuns e sessão.
- `index.php`: painel.
- `ocorrencias.php` e `ordens.php`: fluxo de manutenção.
- `estilo.css` e `script.js`: aparência e menu no celular.

A versão anterior do SIGME, feita com Laravel e Docker, continua disponível no histórico de commits deste repositório.
