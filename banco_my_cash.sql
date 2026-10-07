-- =============================================================
-- MY CASH - BANCO DE DADOS - VERSAO 4
-- Compativel com MySQL 8+
--
-- Esta versao mantem o banco simples:
-- - estrutura e relacionamentos ficam no MySQL;
-- - regras de negocio e alteracoes de saldo ficam no PHP;
-- - operacoes financeiras efetivadas nao sao apagadas;
-- - todas as colunas que sao FKs terminam com _fk.
--
-- Este e um script de criacao inicial para um schema vazio.
-- Ele nao migra automaticamente um banco v2 ou v3 existente.
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
    email VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_admin_email
        UNIQUE (email)
) ENGINE=InnoDB;

-- =============================================================
-- 2. SETORES
-- =============================================================
CREATE TABLE setores (
    id_setor INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    saldo_atual DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_setor_nome
        UNIQUE (nome)
) ENGINE=InnoDB;

-- =============================================================
-- 3. CATEGORIAS
-- =============================================================
CREATE TABLE categorias (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('RECEITA', 'DESPESA') NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_categoria_nome_tipo
        UNIQUE (nome, tipo)
) ENGINE=InnoDB;

-- =============================================================
-- 4. SALDO GERAL
-- O sistema usa somente o registro de id 1.
-- =============================================================
CREATE TABLE saldo_geral (
    id_saldo_geral TINYINT UNSIGNED PRIMARY KEY,
    saldo_atual DECIMAL(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB;

INSERT INTO saldo_geral (id_saldo_geral, saldo_atual)
VALUES (1, 0.00);

-- =============================================================
-- 5. CONTAS A RECEBER
-- Dinheiro que a empresa ainda espera receber.
-- Uma parcela corresponde a uma linha.
-- =============================================================
CREATE TABLE contas_receber (
    id_conta_receber INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data DATE NOT NULL,
    vencimento DATE NOT NULL,
    metodo_pagamento ENUM(
        'PIX',
        'DINHEIRO',
        'CARTAO_CREDITO',
        'CARTAO_DEBITO',
        'BOLETO',
        'TRANSFERENCIA',
        'OUTRO'
    ) NOT NULL,
    status ENUM('PENDENTE', 'RECEBIDO', 'ATRASADO')
        NOT NULL DEFAULT 'PENDENTE',
    codigo_parcelamento VARCHAR(50) NULL,
    numero_parcela SMALLINT UNSIGNED NULL,
    total_parcelas SMALLINT UNSIGNED NULL,
    id_setor_fk INT UNSIGNED NOT NULL,
    id_categoria_fk INT UNSIGNED NOT NULL,
    id_admin_fk INT UNSIGNED NOT NULL,

    CONSTRAINT uq_conta_grupo_parcela
        UNIQUE (codigo_parcelamento, numero_parcela),

    CONSTRAINT fk_conta_setor
        FOREIGN KEY (id_setor_fk)
        REFERENCES setores(id_setor),

    CONSTRAINT fk_conta_categoria
        FOREIGN KEY (id_categoria_fk)
        REFERENCES categorias(id_categoria),

    CONSTRAINT fk_conta_admin
        FOREIGN KEY (id_admin_fk)
        REFERENCES administradores(id_admin),

    INDEX idx_conta_status_vencimento (status, vencimento)
) ENGINE=InnoDB;

-- =============================================================
-- 6. COMPROMISSOS
-- Dinheiro que a empresa ainda precisa pagar.
-- =============================================================
CREATE TABLE compromissos (
    id_compromisso INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data DATE NOT NULL,
    vencimento DATE NOT NULL,
    metodo_pagamento ENUM(
        'PIX',
        'DINHEIRO',
        'CARTAO_CREDITO',
        'CARTAO_DEBITO',
        'BOLETO',
        'TRANSFERENCIA',
        'OUTRO'
    ) NOT NULL,
    status ENUM('PENDENTE', 'PAGO', 'ATRASADO')
        NOT NULL DEFAULT 'PENDENTE',
    id_setor_fk INT UNSIGNED NOT NULL,
    id_categoria_fk INT UNSIGNED NOT NULL,
    id_admin_fk INT UNSIGNED NOT NULL,

    CONSTRAINT fk_compromisso_setor
        FOREIGN KEY (id_setor_fk)
        REFERENCES setores(id_setor),

    CONSTRAINT fk_compromisso_categoria
        FOREIGN KEY (id_categoria_fk)
        REFERENCES categorias(id_categoria),

    CONSTRAINT fk_compromisso_admin
        FOREIGN KEY (id_admin_fk)
        REFERENCES administradores(id_admin),

    INDEX idx_compromisso_status_vencimento (status, vencimento)
) ENGINE=InnoDB;

-- =============================================================
-- 7. MOVIMENTACOES
-- Historico financeiro central.
-- Uma movimentacao ESTORNO aponta para a movimentacao desfeita.
-- =============================================================
CREATE TABLE movimentacoes (
    id_movimentacao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM(
        'RECEITA',
        'DESPESA',
        'DISTRIBUICAO',
        'REALOCACAO',
        'ESTORNO'
    ) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    descricao VARCHAR(255) NOT NULL,
    status ENUM('ATIVA', 'ESTORNADA') NOT NULL DEFAULT 'ATIVA',
    id_admin_fk INT UNSIGNED NOT NULL,
    id_movimentacao_origem_fk INT UNSIGNED NULL,

    CONSTRAINT uq_movimentacao_origem
        UNIQUE (id_movimentacao_origem_fk),

    CONSTRAINT fk_movimentacao_admin
        FOREIGN KEY (id_admin_fk)
        REFERENCES administradores(id_admin),

    CONSTRAINT fk_movimentacao_origem
        FOREIGN KEY (id_movimentacao_origem_fk)
        REFERENCES movimentacoes(id_movimentacao),

    INDEX idx_movimentacao_status_data (status, criado_em)
) ENGINE=InnoDB;

-- =============================================================
-- 8. RECEITAS
-- Dinheiro que realmente entrou na empresa.
-- =============================================================
CREATE TABLE receitas (
    id_receita INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data DATE NOT NULL,
    vencimento DATE NOT NULL,
    metodo_pagamento ENUM(
        'PIX',
        'DINHEIRO',
        'CARTAO_CREDITO',
        'CARTAO_DEBITO',
        'BOLETO',
        'TRANSFERENCIA',
        'OUTRO'
    ) NOT NULL,
    status ENUM('ATIVA', 'ESTORNADA') NOT NULL DEFAULT 'ATIVA',
    id_setor_fk INT UNSIGNED NOT NULL,
    id_categoria_fk INT UNSIGNED NOT NULL,
    id_admin_fk INT UNSIGNED NOT NULL,
    id_conta_receber_fk INT UNSIGNED NULL,
    id_movimentacao_fk INT UNSIGNED NOT NULL,

    CONSTRAINT uq_receita_movimentacao
        UNIQUE (id_movimentacao_fk),

    CONSTRAINT fk_receita_setor
        FOREIGN KEY (id_setor_fk)
        REFERENCES setores(id_setor),

    CONSTRAINT fk_receita_categoria
        FOREIGN KEY (id_categoria_fk)
        REFERENCES categorias(id_categoria),

    CONSTRAINT fk_receita_admin
        FOREIGN KEY (id_admin_fk)
        REFERENCES administradores(id_admin),

    CONSTRAINT fk_receita_conta
        FOREIGN KEY (id_conta_receber_fk)
        REFERENCES contas_receber(id_conta_receber),

    CONSTRAINT fk_receita_movimentacao
        FOREIGN KEY (id_movimentacao_fk)
        REFERENCES movimentacoes(id_movimentacao)
) ENGINE=InnoDB;

-- =============================================================
-- 9. DESPESAS
-- Dinheiro que realmente saiu de um setor.
-- Uma despesa ligada a compromisso continua sendo tipo DESPESA.
-- =============================================================
CREATE TABLE despesas (
    id_despesa INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data DATE NOT NULL,
    vencimento DATE NOT NULL,
    metodo_pagamento ENUM(
        'PIX',
        'DINHEIRO',
        'CARTAO_CREDITO',
        'CARTAO_DEBITO',
        'BOLETO',
        'TRANSFERENCIA',
        'OUTRO'
    ) NOT NULL,
    status ENUM('ATIVA', 'ESTORNADA') NOT NULL DEFAULT 'ATIVA',
    id_setor_fk INT UNSIGNED NOT NULL,
    id_categoria_fk INT UNSIGNED NOT NULL,
    id_admin_fk INT UNSIGNED NOT NULL,
    id_compromisso_fk INT UNSIGNED NULL,
    id_movimentacao_fk INT UNSIGNED NOT NULL,

    CONSTRAINT uq_despesa_movimentacao
        UNIQUE (id_movimentacao_fk),

    CONSTRAINT fk_despesa_setor
        FOREIGN KEY (id_setor_fk)
        REFERENCES setores(id_setor),

    CONSTRAINT fk_despesa_categoria
        FOREIGN KEY (id_categoria_fk)
        REFERENCES categorias(id_categoria),

    CONSTRAINT fk_despesa_admin
        FOREIGN KEY (id_admin_fk)
        REFERENCES administradores(id_admin),

    CONSTRAINT fk_despesa_compromisso
        FOREIGN KEY (id_compromisso_fk)
        REFERENCES compromissos(id_compromisso),

    CONSTRAINT fk_despesa_movimentacao
        FOREIGN KEY (id_movimentacao_fk)
        REFERENCES movimentacoes(id_movimentacao)
) ENGINE=InnoDB;

-- =============================================================
-- 10. TRANSFERENCIAS
-- DISTRIBUICAO: Saldo Geral -> Setor.
-- REALOCACAO: Setor -> Setor.
-- =============================================================
CREATE TABLE transferencias (
    id_transferencia INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('DISTRIBUICAO', 'REALOCACAO') NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data DATE NOT NULL,
    status ENUM('ATIVA', 'ESTORNADA') NOT NULL DEFAULT 'ATIVA',
    id_setor_origem_fk INT UNSIGNED NULL,
    id_setor_destino_fk INT UNSIGNED NULL,
    id_admin_fk INT UNSIGNED NOT NULL,
    id_movimentacao_fk INT UNSIGNED NOT NULL,

    CONSTRAINT uq_transferencia_movimentacao
        UNIQUE (id_movimentacao_fk),

    CONSTRAINT fk_transferencia_origem
        FOREIGN KEY (id_setor_origem_fk)
        REFERENCES setores(id_setor),

    CONSTRAINT fk_transferencia_destino
        FOREIGN KEY (id_setor_destino_fk)
        REFERENCES setores(id_setor),

    CONSTRAINT fk_transferencia_admin
        FOREIGN KEY (id_admin_fk)
        REFERENCES administradores(id_admin),

    CONSTRAINT fk_transferencia_movimentacao
        FOREIGN KEY (id_movimentacao_fk)
        REFERENCES movimentacoes(id_movimentacao)
) ENGINE=InnoDB;

-- =============================================================
-- CATEGORIAS INICIAIS
-- =============================================================
INSERT INTO categorias (nome, tipo, ativo) VALUES
    ('Vendas', 'RECEITA', TRUE),
    ('Serviços', 'RECEITA', TRUE),
    ('Outras Receitas', 'RECEITA', TRUE),
    ('Salários', 'DESPESA', TRUE),
    ('Aluguel', 'DESPESA', TRUE),
    ('Água', 'DESPESA', TRUE),
    ('Energia', 'DESPESA', TRUE),
    ('Internet', 'DESPESA', TRUE),
    ('Fornecedores', 'DESPESA', TRUE),
    ('Impostos', 'DESPESA', TRUE),
    ('Outras Despesas', 'DESPESA', TRUE);

-- =============================================================
-- REGRAS QUE FICAM NO PHP
-- =============================================================
-- Executar cada operacao financeira dentro de uma transacao.
-- Bloquear registros e saldos envolvidos com SELECT ... FOR UPDATE.
-- Validar valor positivo, saldo suficiente, categoria e status.
-- Manter operacao, movimentacao e saldo com o mesmo valor.
-- Nao editar operacao financeira efetivada; estornar e criar outra.
-- Marcar itens vencidos como ATRASADO.
-- Usar sempre saldo_geral.id_saldo_geral = 1.
-- Consultar Modelagem_Banco_My_Cash_v4.md para os fluxos completos.




USE my_cash;

-- =========================================================================
-- TUTORIAL DE TESTE: FLUXO DE VIDA REAL DO MY CASH (VERSÃO 4)
-- =========================================================================

-- -------------------------------------------------------------------------
-- PASSO 1: CRIAR O ACESSO DO ADMINISTRADOR
-- -------------------------------------------------------------------------
-- O sistema precisa de alguém para operar. Vamos criar o utilizador padrão.

INSERT INTO administradores (nome, email, senha, ativo)
VALUES (
    'admin', 
    'admin@gmail.com', 
    '$2y$10$DFfSCSmNPZXQRkPGC0tlb.GxnDJlOFDDGJR0W5mvKObbg4T2Y2BYi', 
    TRUE
);

-- Guardamos o ID deste administrador na memória do MySQL para usar nos próximos passos.
SET @id_admin = LAST_INSERT_ID();


-- -------------------------------------------------------------------------
-- PASSO 2: ABRIR OS CENTROS DE CUSTO (SETORES)
-- -------------------------------------------------------------------------
-- A empresa precisa de áreas para alocar o dinheiro.
-- O banco de dados define automaticamente o 'saldo_atual' como 0.00.
INSERT INTO setores (nome, descricao, saldo_atual, ativo) VALUES
('Comercial', 'Departamento de vendas e fecho de negócios', 0.00, TRUE),
('Infraestrutura', 'Departamento de TI e manutenção', 0.00, TRUE);

-- Guardamos os IDs dos setores para as operações seguintes.
SET @id_setor_comercial = 1;
SET @id_setor_infra = 2;


-- -------------------------------------------------------------------------
-- PASSO 3: INJETAR O DINHEIRO INICIAL NA EMPRESA (R$ 10.000,00)
-- -------------------------------------------------------------------------
-- A empresa começou a usar o sistema com R$ 10.000 em caixa.
-- Regra do sistema: Todo o dinheiro entra primeiro como Receita.

-- 3.1. Primeiro, criamos o registo de auditoria (Histórico).
INSERT INTO movimentacoes (tipo, valor, descricao, status, id_admin_fk)
VALUES ('RECEITA', 10000.00, 'Aporte Inicial de Caixa da Empresa', 'ATIVA', @id_admin);
SET @id_mov_aporte = LAST_INSERT_ID();

-- 3.2. Depois, criamos o recibo da Receita (Categoria 3 = Outras Receitas).
INSERT INTO receitas (descricao, valor, data, vencimento, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_movimentacao_fk)
VALUES ('Aporte Inicial de Caixa da Empresa', 10000.00, CURDATE(), CURDATE(), 'OUTRO', 'ATIVA', @id_setor_comercial, 3, @id_admin, @id_mov_aporte);

-- 3.3. Finalmente, colocamos o dinheiro no cofre principal (Saldo Geral).
UPDATE saldo_geral SET saldo_atual = saldo_atual + 10000.00 WHERE id_saldo_geral = 1;


-- -------------------------------------------------------------------------
-- PASSO 4: FINANCIAR UM SETOR (DISTRIBUIÇÃO DE R$ 4.000,00)
-- -------------------------------------------------------------------------
-- O setor Comercial precisa de dinheiro para operar. Vamos tirar do Saldo Geral.

-- 4.1. Registamos a operação no histórico.
INSERT INTO movimentacoes (tipo, valor, descricao, status, id_admin_fk)
VALUES ('DISTRIBUICAO', 4000.00, 'Orçamento libertado para o Comercial', 'ATIVA', @id_admin);
SET @id_mov_dist = LAST_INSERT_ID();

-- 4.2. Criamos o comprovativo de transferência (Origem é NULL porque vem do Caixa).
INSERT INTO transferencias (tipo, valor, data, status, id_setor_origem_fk, id_setor_destino_fk, id_admin_fk, id_movimentacao_fk)
VALUES ('DISTRIBUICAO', 4000.00, CURDATE(), 'ATIVA', NULL, @id_setor_comercial, @id_admin, @id_mov_dist);

-- 4.3. Tiramos do Saldo Geral (-4000) e entregamos ao Setor Comercial (+4000).
UPDATE saldo_geral SET saldo_atual = saldo_atual - 4000.00 WHERE id_saldo_geral = 1;
UPDATE setores SET saldo_atual = saldo_atual + 4000.00 WHERE id_setor = @id_setor_comercial;


-- -------------------------------------------------------------------------
-- PASSO 5: O SETOR COMERCIAL GASTA DINHEIRO (DESPESA DE R$ 500,00)
-- -------------------------------------------------------------------------
-- O Comercial (que agora tem R$ 4.000) vai comprar material de escritório.

-- 5.1. Registamos o gasto no histórico.
INSERT INTO movimentacoes (tipo, valor, descricao, status, id_admin_fk)
VALUES ('DESPESA', 500.00, 'Compra de material de escritório', 'ATIVA', @id_admin);
SET @id_mov_desp = LAST_INSERT_ID();

-- 5.2. Criamos a despesa efetivada (Categoria 9 = Fornecedores).
INSERT INTO despesas (descricao, valor, data, vencimento, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_movimentacao_fk)
VALUES ('Compra de material de escritório', 500.00, CURDATE(), CURDATE(), 'PIX', 'ATIVA', @id_setor_comercial, 9, @id_admin, @id_mov_desp);

-- 5.3. Deduzimos os R$ 500,00 diretamente do bolso do Comercial (Passa a ter R$ 3.500).
UPDATE setores SET saldo_atual = saldo_atual - 500.00 WHERE id_setor = @id_setor_comercial;


-- -------------------------------------------------------------------------
-- PASSO 6: PREVISÃO DE ENTRADA (CONTA A RECEBER)
-- -------------------------------------------------------------------------
-- A empresa fez uma venda de R$ 1.500,00, mas o cliente só paga daqui a 15 dias.
-- Como é PENDENTE, não entra nas 'receitas' nem no 'saldo_geral' ainda!
INSERT INTO contas_receber (descricao, valor, data, vencimento, metodo_pagamento, status, codigo_parcelamento, numero_parcela, total_parcelas, id_setor_fk, id_categoria_fk, id_admin_fk)
VALUES ('Venda de Licenças a Prazo', 1500.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'BOLETO', 'PENDENTE', NULL, NULL, NULL, @id_setor_comercial, 1, @id_admin);


-- -------------------------------------------------------------------------
-- PASSO 7: PREVISÃO DE SAÍDA (COMPROMISSO A PAGAR)
-- -------------------------------------------------------------------------
-- Chegou uma fatura de Internet para a Infraestrutura de R$ 300,00 a vencer em 5 dias.
-- Nota: A Infraestrutura tem R$ 0,00 de saldo. 
-- Quando for testar o sistema em PHP e clicar em "Pagar", o sistema deverá gerar 
-- o erro "Saldo insuficiente", obrigando-o a transferir dinheiro para lá primeiro!
INSERT INTO compromissos (descricao, valor, data, vencimento, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk)
VALUES ('Fatura de Internet e Servidores', 300.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'BOLETO', 'PENDENTE', @id_setor_infra, 8, @id_admin);

-- =========================================================================
-- FIM DO TUTORIAL DE POVOAMENTO
-- Pode entrar no sistema e verificar como o Dashboard reage a estes dados!
-- =========================================================================































