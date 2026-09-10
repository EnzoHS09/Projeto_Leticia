-- =============================================================
-- MY CASH - BANCO DE DADOS
-- MySQL 8+
-- =============================================================

CREATE DATABASE IF NOT EXISTS my_cash
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE my_cash;

-- =============================================================
-- 1. ADMINISTRADORES
-- =============================================================
CREATE TABLE administradores (
    id_admin INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- =============================================================
-- 2. SETORES
-- =============================================================
CREATE TABLE setores (
    id_setor INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    saldo_atual DECIMAL(15,2) NOT NULL DEFAULT 0.00,

    CONSTRAINT chk_setores_saldo_nao_negativo
        CHECK (saldo_atual >= 0)
) ENGINE=InnoDB;

-- =============================================================
-- 3. CATEGORIAS
-- =============================================================
CREATE TABLE categorias (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('RECEITA', 'DESPESA') NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_categoria_nome_tipo UNIQUE (nome, tipo)
) ENGINE=InnoDB;

-- =============================================================
-- 10. SALDO GERAL
-- Criado antes das tabelas financeiras porque e uma estrutura base.
-- Havera apenas um registro, de id 1.
-- =============================================================
CREATE TABLE saldo_geral (
    id_saldo_geral TINYINT UNSIGNED PRIMARY KEY,
    saldo_atual DECIMAL(15,2) NOT NULL DEFAULT 0.00,

    CONSTRAINT chk_saldo_geral_id_unico
        CHECK (id_saldo_geral = 1),

    CONSTRAINT chk_saldo_geral_nao_negativo
        CHECK (saldo_atual >= 0)
) ENGINE=InnoDB;

-- Cria o unico Saldo Geral do sistema, inicialmente zerado.
INSERT INTO saldo_geral (id_saldo_geral, saldo_atual)
VALUES (1, 0.00);

-- =============================================================
-- 9. CONTAS A RECEBER
-- Criada antes de receitas porque receitas pode referencia-la.
-- Cada parcela de uma venda parcelada sera uma linha separada.
-- =============================================================
CREATE TABLE contas_receber (
    id_conta_receber INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    vencimento DATE NOT NULL,
    status ENUM('PENDENTE', 'RECEBIDO', 'ATRASADO') NOT NULL DEFAULT 'PENDENTE',
    metodo_pagamento VARCHAR(30) NOT NULL,
    numero_parcela SMALLINT UNSIGNED NULL,
    total_parcelas SMALLINT UNSIGNED NULL,
    data_recebimento DATE NULL,
    id_setor INT UNSIGNED NOT NULL,
    id_categoria INT UNSIGNED NOT NULL,
    id_admin INT UNSIGNED NOT NULL,

    CONSTRAINT chk_conta_receber_valor_positivo
        CHECK (valor > 0),

    CONSTRAINT chk_conta_receber_parcelas
        CHECK (
            (numero_parcela IS NULL AND total_parcelas IS NULL)
            OR
            (
                numero_parcela IS NOT NULL
                AND total_parcelas IS NOT NULL
                AND total_parcelas >= 1
                AND numero_parcela >= 1
                AND numero_parcela <= total_parcelas
            )
        ),

    CONSTRAINT fk_conta_receber_setor
        FOREIGN KEY (id_setor)
        REFERENCES setores(id_setor)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_conta_receber_categoria
        FOREIGN KEY (id_categoria)
        REFERENCES categorias(id_categoria)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_conta_receber_admin
        FOREIGN KEY (id_admin)
        REFERENCES administradores(id_admin)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_contas_receber_status_vencimento (status, vencimento)
) ENGINE=InnoDB;

-- =============================================================
-- 6. COMPROMISSOS
-- Dinheiro que ainda precisa ser pago.
-- =============================================================
CREATE TABLE compromissos (
    id_compromisso INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    vencimento DATE NOT NULL,
    status ENUM('PENDENTE', 'PAGO', 'ATRASADO') NOT NULL DEFAULT 'PENDENTE',
    id_setor INT UNSIGNED NOT NULL,
    id_categoria INT UNSIGNED NOT NULL,
    id_admin INT UNSIGNED NOT NULL,

    CONSTRAINT chk_compromisso_valor_positivo
        CHECK (valor > 0),

    CONSTRAINT fk_compromisso_setor
        FOREIGN KEY (id_setor)
        REFERENCES setores(id_setor)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_compromisso_categoria
        FOREIGN KEY (id_categoria)
        REFERENCES categorias(id_categoria)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_compromisso_admin
        FOREIGN KEY (id_admin)
        REFERENCES administradores(id_admin)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_compromissos_status_vencimento (status, vencimento)
) ENGINE=InnoDB;

-- =============================================================
-- 4. RECEITAS
-- Somente dinheiro que realmente entrou na empresa.
-- Se veio de uma conta a receber, id_conta_receber aponta para ela.
-- =============================================================
CREATE TABLE receitas (
    id_receita INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data DATE NOT NULL,
    metodo_pagamento VARCHAR(30) NOT NULL,
    id_setor INT UNSIGNED NOT NULL,
    id_categoria INT UNSIGNED NOT NULL,
    id_admin INT UNSIGNED NOT NULL,
    id_conta_receber INT UNSIGNED NULL,

    CONSTRAINT chk_receita_valor_positivo
        CHECK (valor > 0),

    CONSTRAINT uq_receita_conta_receber
        UNIQUE (id_conta_receber),

    CONSTRAINT fk_receita_setor
        FOREIGN KEY (id_setor)
        REFERENCES setores(id_setor)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_receita_categoria
        FOREIGN KEY (id_categoria)
        REFERENCES categorias(id_categoria)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_receita_admin
        FOREIGN KEY (id_admin)
        REFERENCES administradores(id_admin)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_receita_conta_receber
        FOREIGN KEY (id_conta_receber)
        REFERENCES contas_receber(id_conta_receber)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =============================================================
-- 5. DESPESAS
-- Somente dinheiro que realmente saiu de um setor.
-- Se veio do pagamento de um compromisso, id_compromisso aponta para ele.
-- =============================================================
CREATE TABLE despesas (
    id_despesa INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data DATE NOT NULL,
    metodo_pagamento VARCHAR(30) NOT NULL,
    id_setor INT UNSIGNED NOT NULL,
    id_categoria INT UNSIGNED NOT NULL,
    id_admin INT UNSIGNED NOT NULL,
    id_compromisso INT UNSIGNED NULL,

    CONSTRAINT chk_despesa_valor_positivo
        CHECK (valor > 0),

    CONSTRAINT uq_despesa_compromisso
        UNIQUE (id_compromisso),

    CONSTRAINT fk_despesa_setor
        FOREIGN KEY (id_setor)
        REFERENCES setores(id_setor)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_despesa_categoria
        FOREIGN KEY (id_categoria)
        REFERENCES categorias(id_categoria)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_despesa_admin
        FOREIGN KEY (id_admin)
        REFERENCES administradores(id_admin)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_despesa_compromisso
        FOREIGN KEY (id_compromisso)
        REFERENCES compromissos(id_compromisso)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =============================================================
-- 7. TRANSFERENCIAS
-- DISTRIBUICAO: Saldo Geral -> Setor
-- REALOCACAO: Setor -> Setor
-- =============================================================
CREATE TABLE transferencias (
    id_transferencia INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('DISTRIBUICAO', 'REALOCACAO') NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data DATE NOT NULL,
    id_setor_origem INT UNSIGNED NULL,
    id_setor_destino INT UNSIGNED NOT NULL,
    id_admin INT UNSIGNED NOT NULL,

    CONSTRAINT chk_transferencia_valor_positivo
        CHECK (valor > 0),

    CONSTRAINT chk_transferencia_origem
        CHECK (
            (tipo = 'DISTRIBUICAO' AND id_setor_origem IS NULL)
            OR
            (
                tipo = 'REALOCACAO'
                AND id_setor_origem IS NOT NULL
                AND id_setor_origem <> id_setor_destino
            )
        ),

    CONSTRAINT fk_transferencia_origem
        FOREIGN KEY (id_setor_origem)
        REFERENCES setores(id_setor)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_transferencia_destino
        FOREIGN KEY (id_setor_destino)
        REFERENCES setores(id_setor)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_transferencia_admin
        FOREIGN KEY (id_admin)
        REFERENCES administradores(id_admin)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =============================================================
-- 8. MOVIMENTACOES
-- Historico simples das operacoes.
-- Nesta primeira versao, nao possui varias FKs para as tabelas de origem.
-- A descricao deve registrar informacao suficiente para auditoria basica.
-- =============================================================
CREATE TABLE movimentacoes (
    id_movimentacao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(30) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    descricao VARCHAR(255) NOT NULL,
    id_admin INT UNSIGNED NOT NULL,

    CONSTRAINT chk_movimentacao_valor_positivo
        CHECK (valor > 0),

    CONSTRAINT fk_movimentacao_admin
        FOREIGN KEY (id_admin)
        REFERENCES administradores(id_admin)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_movimentacoes_data (data),
    INDEX idx_movimentacoes_tipo (tipo)
) ENGINE=InnoDB;

-- =============================================================
-- CATEGORIAS INICIAIS
-- =============================================================
INSERT INTO categorias (nome, tipo, ativo) VALUES
    ('Vendas', 'RECEITA', TRUE),
    ('Servicos', 'RECEITA', TRUE),
    ('Outras Receitas', 'RECEITA', TRUE),
    ('Salarios', 'DESPESA', TRUE),
    ('Aluguel', 'DESPESA', TRUE),
    ('Agua', 'DESPESA', TRUE),
    ('Energia', 'DESPESA', TRUE),
    ('Internet', 'DESPESA', TRUE),
    ('Fornecedores', 'DESPESA', TRUE),
    ('Impostos', 'DESPESA', TRUE),
    ('Outras Despesas', 'DESPESA', TRUE);

-- =============================================================
-- OBSERVACOES IMPORTANTES
-- =============================================================
-- 1) O banco.sql cria a estrutura. As regras de negocio devem ser executadas
--    pelo PHP usando transacoes (START TRANSACTION / COMMIT / ROLLBACK).
--
-- 2) Exemplo: confirmar uma conta a receber exige, na mesma transacao:
--      - marcar contas_receber como RECEBIDO;
--      - preencher data_recebimento;
--      - criar a receita correspondente;
--      - aumentar saldo_geral.saldo_atual;
--      - registrar a movimentacao.
--
-- 3) Exemplo: pagar um compromisso exige, na mesma transacao:
--      - verificar saldo suficiente do setor;
--      - marcar o compromisso como PAGO;
--      - criar a despesa correspondente;
--      - diminuir setores.saldo_atual;
--      - registrar a movimentacao.
--
-- 4) Para marcar itens vencidos como ATRASADO, o PHP pode executar:
--
-- UPDATE contas_receber
-- SET status = 'ATRASADO'
-- WHERE status = 'PENDENTE'
--   AND vencimento < CURDATE();
--
-- UPDATE compromissos
-- SET status = 'ATRASADO'
-- WHERE status = 'PENDENTE'
--   AND vencimento < CURDATE();
--
-- 5) O PHP tambem deve validar o tipo da categoria:
--      - receitas / contas_receber -> categoria tipo RECEITA
--      - despesas / compromissos   -> categoria tipo DESPESA
-- =============================================================
