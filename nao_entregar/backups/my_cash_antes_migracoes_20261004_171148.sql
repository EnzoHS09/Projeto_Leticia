-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: my_cash
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `administradores`
--

DROP TABLE IF EXISTS `administradores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `administradores` (
  `id_admin` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  PRIMARY KEY (`id_admin`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `administradores`
--

LOCK TABLES `administradores` WRITE;
/*!40000 ALTER TABLE `administradores` DISABLE KEYS */;
INSERT INTO `administradores` VALUES (1,'Enzo','enzo@gmail.com','$2y$10$msYkx7YAIIH5uUaMAchugOnLrdWHvmYnElY5AONUv4tu1a.BVa6rW');
/*!40000 ALTER TABLE `administradores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias` (
  `id_categoria` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `tipo` enum('RECEITA','DESPESA') NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_categoria`),
  UNIQUE KEY `uq_categoria_nome_tipo` (`nome`,`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (1,'Vendas','RECEITA',1),(2,'Servicos','RECEITA',1),(3,'Outras Receitas','RECEITA',1),(4,'Salarios','DESPESA',1),(5,'Aluguel','DESPESA',1),(6,'Agua','DESPESA',1),(7,'Energia','DESPESA',1),(8,'Internet','DESPESA',1),(9,'Fornecedores','DESPESA',1),(10,'Impostos','DESPESA',1),(11,'Outras Despesas','DESPESA',1);
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compromissos`
--

DROP TABLE IF EXISTS `compromissos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compromissos` (
  `id_compromisso` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `vencimento` date NOT NULL,
  `status` enum('PENDENTE','PAGO','ATRASADO') NOT NULL DEFAULT 'PENDENTE',
  `id_setor` int(10) unsigned NOT NULL,
  `id_categoria` int(10) unsigned NOT NULL,
  `id_admin` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_compromisso`),
  KEY `fk_compromisso_setor` (`id_setor`),
  KEY `fk_compromisso_categoria` (`id_categoria`),
  KEY `fk_compromisso_admin` (`id_admin`),
  KEY `idx_compromissos_status_vencimento` (`status`,`vencimento`),
  CONSTRAINT `fk_compromisso_admin` FOREIGN KEY (`id_admin`) REFERENCES `administradores` (`id_admin`) ON UPDATE CASCADE,
  CONSTRAINT `fk_compromisso_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON UPDATE CASCADE,
  CONSTRAINT `fk_compromisso_setor` FOREIGN KEY (`id_setor`) REFERENCES `setores` (`id_setor`) ON UPDATE CASCADE,
  CONSTRAINT `chk_compromisso_valor_positivo` CHECK (`valor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compromissos`
--

LOCK TABLES `compromissos` WRITE;
/*!40000 ALTER TABLE `compromissos` DISABLE KEYS */;
/*!40000 ALTER TABLE `compromissos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contas_receber`
--

DROP TABLE IF EXISTS `contas_receber`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contas_receber` (
  `id_conta_receber` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `vencimento` date NOT NULL,
  `status` enum('PENDENTE','RECEBIDO','ATRASADO') NOT NULL DEFAULT 'PENDENTE',
  `metodo_pagamento` varchar(30) NOT NULL,
  `numero_parcela` smallint(5) unsigned DEFAULT NULL,
  `total_parcelas` smallint(5) unsigned DEFAULT NULL,
  `data_recebimento` date DEFAULT NULL,
  `id_setor` int(10) unsigned NOT NULL,
  `id_categoria` int(10) unsigned NOT NULL,
  `id_admin` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_conta_receber`),
  KEY `fk_conta_receber_setor` (`id_setor`),
  KEY `fk_conta_receber_categoria` (`id_categoria`),
  KEY `fk_conta_receber_admin` (`id_admin`),
  KEY `idx_contas_receber_status_vencimento` (`status`,`vencimento`),
  CONSTRAINT `fk_conta_receber_admin` FOREIGN KEY (`id_admin`) REFERENCES `administradores` (`id_admin`) ON UPDATE CASCADE,
  CONSTRAINT `fk_conta_receber_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON UPDATE CASCADE,
  CONSTRAINT `fk_conta_receber_setor` FOREIGN KEY (`id_setor`) REFERENCES `setores` (`id_setor`) ON UPDATE CASCADE,
  CONSTRAINT `chk_conta_receber_valor_positivo` CHECK (`valor` > 0),
  CONSTRAINT `chk_conta_receber_parcelas` CHECK (`numero_parcela` is null and `total_parcelas` is null or `numero_parcela` is not null and `total_parcelas` is not null and `total_parcelas` >= 1 and `numero_parcela` >= 1 and `numero_parcela` <= `total_parcelas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contas_receber`
--

LOCK TABLES `contas_receber` WRITE;
/*!40000 ALTER TABLE `contas_receber` DISABLE KEYS */;
/*!40000 ALTER TABLE `contas_receber` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `despesas`
--

DROP TABLE IF EXISTS `despesas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `despesas` (
  `id_despesa` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `data` date NOT NULL,
  `metodo_pagamento` varchar(30) NOT NULL,
  `id_setor` int(10) unsigned NOT NULL,
  `id_categoria` int(10) unsigned NOT NULL,
  `id_admin` int(10) unsigned NOT NULL,
  `id_compromisso` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_despesa`),
  UNIQUE KEY `uq_despesa_compromisso` (`id_compromisso`),
  KEY `fk_despesa_setor` (`id_setor`),
  KEY `fk_despesa_categoria` (`id_categoria`),
  KEY `fk_despesa_admin` (`id_admin`),
  CONSTRAINT `fk_despesa_admin` FOREIGN KEY (`id_admin`) REFERENCES `administradores` (`id_admin`) ON UPDATE CASCADE,
  CONSTRAINT `fk_despesa_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON UPDATE CASCADE,
  CONSTRAINT `fk_despesa_compromisso` FOREIGN KEY (`id_compromisso`) REFERENCES `compromissos` (`id_compromisso`) ON UPDATE CASCADE,
  CONSTRAINT `fk_despesa_setor` FOREIGN KEY (`id_setor`) REFERENCES `setores` (`id_setor`) ON UPDATE CASCADE,
  CONSTRAINT `chk_despesa_valor_positivo` CHECK (`valor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `despesas`
--

LOCK TABLES `despesas` WRITE;
/*!40000 ALTER TABLE `despesas` DISABLE KEYS */;
/*!40000 ALTER TABLE `despesas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimentacoes`
--

DROP TABLE IF EXISTS `movimentacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimentacoes` (
  `id_movimentacao` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(30) NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `data` datetime NOT NULL DEFAULT current_timestamp(),
  `descricao` varchar(255) NOT NULL,
  `id_admin` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_movimentacao`),
  KEY `fk_movimentacao_admin` (`id_admin`),
  KEY `idx_movimentacoes_data` (`data`),
  KEY `idx_movimentacoes_tipo` (`tipo`),
  CONSTRAINT `fk_movimentacao_admin` FOREIGN KEY (`id_admin`) REFERENCES `administradores` (`id_admin`) ON UPDATE CASCADE,
  CONSTRAINT `chk_movimentacao_valor_positivo` CHECK (`valor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimentacoes`
--

LOCK TABLES `movimentacoes` WRITE;
/*!40000 ALTER TABLE `movimentacoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `movimentacoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receitas`
--

DROP TABLE IF EXISTS `receitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receitas` (
  `id_receita` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `data` date NOT NULL,
  `metodo_pagamento` varchar(30) NOT NULL,
  `id_setor` int(10) unsigned NOT NULL,
  `id_categoria` int(10) unsigned NOT NULL,
  `id_admin` int(10) unsigned NOT NULL,
  `id_conta_receber` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_receita`),
  UNIQUE KEY `uq_receita_conta_receber` (`id_conta_receber`),
  KEY `fk_receita_setor` (`id_setor`),
  KEY `fk_receita_categoria` (`id_categoria`),
  KEY `fk_receita_admin` (`id_admin`),
  CONSTRAINT `fk_receita_admin` FOREIGN KEY (`id_admin`) REFERENCES `administradores` (`id_admin`) ON UPDATE CASCADE,
  CONSTRAINT `fk_receita_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON UPDATE CASCADE,
  CONSTRAINT `fk_receita_conta_receber` FOREIGN KEY (`id_conta_receber`) REFERENCES `contas_receber` (`id_conta_receber`) ON UPDATE CASCADE,
  CONSTRAINT `fk_receita_setor` FOREIGN KEY (`id_setor`) REFERENCES `setores` (`id_setor`) ON UPDATE CASCADE,
  CONSTRAINT `chk_receita_valor_positivo` CHECK (`valor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receitas`
--

LOCK TABLES `receitas` WRITE;
/*!40000 ALTER TABLE `receitas` DISABLE KEYS */;
/*!40000 ALTER TABLE `receitas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saldo_geral`
--

DROP TABLE IF EXISTS `saldo_geral`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saldo_geral` (
  `id_saldo_geral` tinyint(3) unsigned NOT NULL,
  `saldo_atual` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_saldo_geral`),
  CONSTRAINT `chk_saldo_geral_id_unico` CHECK (`id_saldo_geral` = 1),
  CONSTRAINT `chk_saldo_geral_nao_negativo` CHECK (`saldo_atual` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saldo_geral`
--

LOCK TABLES `saldo_geral` WRITE;
/*!40000 ALTER TABLE `saldo_geral` DISABLE KEYS */;
INSERT INTO `saldo_geral` VALUES (1,0.00);
/*!40000 ALTER TABLE `saldo_geral` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `setores`
--

DROP TABLE IF EXISTS `setores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `setores` (
  `id_setor` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `saldo_atual` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_setor`),
  CONSTRAINT `chk_setores_saldo_nao_negativo` CHECK (`saldo_atual` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `setores`
--

LOCK TABLES `setores` WRITE;
/*!40000 ALTER TABLE `setores` DISABLE KEYS */;
/*!40000 ALTER TABLE `setores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transferencias`
--

DROP TABLE IF EXISTS `transferencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transferencias` (
  `id_transferencia` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('DISTRIBUICAO','REALOCACAO') NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `data` date NOT NULL,
  `id_setor_origem` int(10) unsigned DEFAULT NULL,
  `id_setor_destino` int(10) unsigned NOT NULL,
  `id_admin` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_transferencia`),
  KEY `fk_transferencia_origem` (`id_setor_origem`),
  KEY `fk_transferencia_destino` (`id_setor_destino`),
  KEY `fk_transferencia_admin` (`id_admin`),
  CONSTRAINT `fk_transferencia_admin` FOREIGN KEY (`id_admin`) REFERENCES `administradores` (`id_admin`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transferencia_destino` FOREIGN KEY (`id_setor_destino`) REFERENCES `setores` (`id_setor`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transferencia_origem` FOREIGN KEY (`id_setor_origem`) REFERENCES `setores` (`id_setor`) ON UPDATE CASCADE,
  CONSTRAINT `chk_transferencia_valor_positivo` CHECK (`valor` > 0),
  CONSTRAINT `chk_transferencia_origem` CHECK (`tipo` = 'DISTRIBUICAO' and `id_setor_origem` is null or `tipo` = 'REALOCACAO' and `id_setor_origem` is not null and `id_setor_origem` <> `id_setor_destino`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transferencias`
--

LOCK TABLES `transferencias` WRITE;
/*!40000 ALTER TABLE `transferencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `transferencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'my_cash'
--

--
-- Dumping routines for database 'my_cash'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-04 17:11:48
