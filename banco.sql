-- Importe este arquivo no phpMyAdmin antes de abrir instalar.php.
CREATE DATABASE IF NOT EXISTS sigme_escola_unica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sigme_escola_unica;

CREATE TABLE IF NOT EXISTS escolas (
    id INT NOT NULL PRIMARY KEY CHECK (id = 1),
    nome VARCHAR(150) NOT NULL,
    endereco VARCHAR(255) DEFAULT NULL,
    telefone VARCHAR(30) DEFAULT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1
);

-- Esta versão sempre usa somente a escola de ID 1.
INSERT IGNORE INTO escolas (id, nome) VALUES (1, 'Escola Principal');

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escola_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    senha VARCHAR(255) DEFAULT NULL,
    perfil ENUM('gestor') NOT NULL DEFAULT 'gestor',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (escola_id) REFERENCES escolas(id),
    UNIQUE (escola_id)
);

CREATE TABLE IF NOT EXISTS ambientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escola_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (escola_id) REFERENCES escolas(id)
);

CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escola_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (escola_id) REFERENCES escolas(id)
);

CREATE TABLE IF NOT EXISTS ocorrencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escola_id INT NOT NULL,
    ambiente_id INT NOT NULL,
    categoria_id INT NOT NULL,
    solicitante_id INT NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descricao TEXT NOT NULL,
    prioridade ENUM('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
    prioridade_confirmada TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('aberta','em_triagem','encaminhada','resolvida','encerrada','duplicada','nao_procede') NOT NULL DEFAULT 'aberta',
    observacao_triagem TEXT DEFAULT NULL,
    destino VARCHAR(180) DEFAULT NULL,
    duplicada_de INT DEFAULT NULL,
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (escola_id) REFERENCES escolas(id),
    FOREIGN KEY (ambiente_id) REFERENCES ambientes(id),
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    FOREIGN KEY (solicitante_id) REFERENCES usuarios(id),
    INDEX (escola_id, status)
);

CREATE TABLE IF NOT EXISTS ordens_servico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escola_id INT NOT NULL,
    ocorrencia_id INT NOT NULL,
    responsavel_id INT NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descricao TEXT NOT NULL,
    prazo DATE DEFAULT NULL,
    fornecedor VARCHAR(180) DEFAULT NULL,
    status ENUM('aguardando_aprovacao','aprovada','em_execucao','concluida','rejeitada','cancelada') NOT NULL DEFAULT 'aguardando_aprovacao',
    diagnostico TEXT DEFAULT NULL,
    solucao TEXT DEFAULT NULL,
    motivo_emergencia TEXT DEFAULT NULL,
    emergencia_ratificada TINYINT(1) NOT NULL DEFAULT 0,
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    concluida_em DATETIME DEFAULT NULL,
    FOREIGN KEY (escola_id) REFERENCES escolas(id),
    FOREIGN KEY (ocorrencia_id) REFERENCES ocorrencias(id),
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id),
    INDEX (escola_id, status)
);

CREATE TABLE IF NOT EXISTS registros_os (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordem_id INT NOT NULL,
    tipo ENUM('atualizacao') NOT NULL,
    descricao TEXT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ordem_id) REFERENCES ordens_servico(id)
);

CREATE TABLE IF NOT EXISTS historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escola_id INT NOT NULL,
    tipo ENUM('ocorrencia','ordem') NOT NULL,
    registro_id INT NOT NULL,
    usuario_id INT NOT NULL,
    acao VARCHAR(100) NOT NULL,
    nota TEXT DEFAULT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (escola_id) REFERENCES escolas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX (tipo, registro_id)
);
