-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 13/02/2026 às 17:23
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `meus_gastos`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `tipo` enum('receita','despesa') NOT NULL,
  `cor_hex` varchar(7) DEFAULT '#333333'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categorias`
--

INSERT INTO `categorias` (`id`, `nome`, `tipo`, `cor_hex`) VALUES
(1, 'Salário', 'receita', '#2ecc71'),
(2, 'Freelance', 'receita', '#27ae60'),
(3, 'Aluguel', 'despesa', '#e74c3c'),
(4, 'Lazer', 'despesa', '#9b59b6'),
(5, 'Mercado', 'despesa', '#f1c40f'),
(6, 'Transporte', 'despesa', '#e67e22'),
(7, 'Dívida', 'despesa', '#c0392b'),
(8, 'Saúde', 'despesa', '#ff7675'),
(9, 'Educação', 'despesa', '#6c5ce7'),
(10, 'Assinaturas', 'despesa', '#e84393'),
(11, 'Restaurante', 'despesa', '#e17055'),
(12, 'Investimentos', 'despesa', '#00b894'),
(13, 'Vestuário', 'despesa', '#fab1a0'),
(14, 'Manutenção Casa', 'despesa', '#636e72'),
(15, 'Pet', 'despesa', '#a29bfe'),
(16, 'Academia', 'despesa', '#0984e3'),
(17, 'Vendas', 'receita', '#fdcb6e'),
(18, 'Dividendos', 'receita', '#55efc4'),
(19, 'Reembolso', 'receita', '#74b9ff'),
(20, 'Programação', 'receita', '#8b5cf6'),
(21, 'Suplementos', 'despesa', '#1dfa00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `metas`
--

CREATE TABLE `metas` (
  `id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `valor_limite` decimal(10,2) NOT NULL,
  `status` enum('ativa','concluida') DEFAULT 'ativa',
  `mes` int(11) NOT NULL DEFAULT 0,
  `ano` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `metas`
--

INSERT INTO `metas` (`id`, `categoria_id`, `valor_limite`, `status`, `mes`, `ano`) VALUES
(5, 3, 700.00, 'ativa', 2, 2026),
(7, 3, 700.00, 'ativa', 3, 2026);

-- --------------------------------------------------------

--
-- Estrutura para tabela `transacoes`
--

CREATE TABLE `transacoes` (
  `id` int(11) NOT NULL,
  `descricao` varchar(100) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `tipo` enum('receita','despesa') NOT NULL,
  `data_transacao` date NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `status` enum('pago','pendente') DEFAULT 'pago',
  `observacao` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `parcela_atual` int(11) DEFAULT 1,
  `parcelas_totais` int(11) DEFAULT 1,
  `grupo_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `transacoes`
--

INSERT INTO `transacoes` (`id`, `descricao`, `valor`, `tipo`, `data_transacao`, `categoria_id`, `status`, `observacao`, `criado_em`, `parcela_atual`, `parcelas_totais`, `grupo_id`) VALUES
(4, 'Serviço', 300.00, 'receita', '2026-02-14', 1, 'pendente', NULL, '2026-02-10 22:31:02', 1, 1, NULL),
(7, 'Cama', 69.99, 'despesa', '2026-02-14', 7, 'pendente', NULL, '2026-02-11 03:12:43', 1, 1, NULL),
(8, 'Renner', 56.00, 'despesa', '2026-02-24', 7, 'pendente', NULL, '2026-02-11 03:13:35', 1, 1, NULL),
(16, 'Viagem', 50.00, 'receita', '2026-02-19', 6, 'pendente', NULL, '2026-02-11 17:58:52', 1, 1, NULL),
(26, 'NuBank (1/5)', 103.40, 'despesa', '2026-02-11', 7, 'pendente', NULL, '2026-02-11 22:59:45', 1, 5, 'trans_698d09e16f429'),
(27, 'NuBank (2/5)', 103.40, 'despesa', '2026-03-11', 7, 'pendente', NULL, '2026-02-11 22:59:45', 2, 5, 'trans_698d09e16f429'),
(28, 'NuBank (3/5)', 103.40, 'despesa', '2026-04-11', 7, 'pendente', NULL, '2026-02-11 22:59:45', 3, 5, 'trans_698d09e16f429'),
(29, 'NuBank (4/5)', 103.40, 'despesa', '2026-05-11', 7, 'pendente', NULL, '2026-02-11 22:59:45', 4, 5, 'trans_698d09e16f429'),
(30, 'NuBank (5/5)', 103.40, 'despesa', '2026-06-11', 7, 'pendente', NULL, '2026-02-11 22:59:45', 5, 5, 'trans_698d09e16f429'),
(53, 'Serviço', 140.00, 'receita', '2026-02-20', 1, 'pendente', NULL, '2026-02-12 22:18:56', 1, 2, 'trans_698e51d034ee7'),
(54, 'Serviço', 140.00, 'receita', '2026-03-20', 1, 'pendente', NULL, '2026-02-12 22:18:56', 2, 2, 'trans_698e51d034ee7'),
(55, 'Inter (1/5)', 100.00, 'despesa', '2026-02-12', 7, 'pendente', NULL, '2026-02-12 22:20:05', 1, 5, 'trans_698e5215a2c7c'),
(56, 'Inter (2/5)', 100.00, 'despesa', '2026-03-12', 7, 'pendente', NULL, '2026-02-12 22:20:05', 2, 5, 'trans_698e5215a2c7c'),
(57, 'Inter (3/5)', 100.00, 'despesa', '2026-04-12', 7, 'pendente', NULL, '2026-02-12 22:20:05', 3, 5, 'trans_698e5215a2c7c'),
(58, 'Inter (4/5)', 100.00, 'despesa', '2026-05-12', 7, 'pendente', NULL, '2026-02-12 22:20:05', 4, 5, 'trans_698e5215a2c7c'),
(59, 'Inter (5/5)', 100.00, 'despesa', '2026-06-12', 7, 'pendente', NULL, '2026-02-12 22:20:05', 5, 5, 'trans_698e5215a2c7c'),
(63, 'a', 1.00, 'despesa', '2026-03-12', 21, 'pendente', NULL, '2026-02-12 22:30:18', 2, 2, 'trans_698e547a6b741'),
(64, 'Fone de ouvido', 900.00, 'despesa', '2026-02-13', 4, 'pendente', NULL, '2026-02-13 02:18:37', 1, 2, 'trans_698e89fd28b91'),
(65, 'Fone de ouvido', 900.00, 'despesa', '2026-03-13', 4, 'pendente', NULL, '2026-02-13 02:18:37', 2, 2, 'trans_698e89fd28b91'),
(66, 'Renner (1/4)', 112.50, 'despesa', '2026-02-13', 13, 'pendente', NULL, '2026-02-13 02:25:51', 1, 4, 'trans_698e8baf4ba47'),
(67, 'Renner (2/4)', 112.50, 'despesa', '2026-03-13', 13, 'pendente', NULL, '2026-02-13 02:25:51', 2, 4, 'trans_698e8baf4ba47'),
(68, 'Renner (3/4)', 112.50, 'despesa', '2026-04-13', 13, 'pendente', NULL, '2026-02-13 02:25:51', 3, 4, 'trans_698e8baf4ba47'),
(69, 'Renner (4/4)', 112.50, 'despesa', '2026-05-13', 13, 'pendente', NULL, '2026-02-13 02:25:51', 4, 4, 'trans_698e8baf4ba47'),
(70, 'd', 60.00, 'despesa', '2026-02-13', 19, 'pendente', NULL, '2026-02-13 02:59:36', 1, 2, 'trans_698e9398d24a1'),
(71, 'd', 60.00, 'despesa', '2026-03-13', 19, 'pendente', NULL, '2026-02-13 02:59:36', 2, 2, 'trans_698e9398d24a1'),
(72, 'Teste', 700.00, 'receita', '2026-02-13', 20, 'pago', NULL, '2026-02-13 03:02:45', 1, 2, 'trans_698e9455b3c2c'),
(73, 'Teste', 700.00, 'receita', '2026-03-13', 20, 'pendente', NULL, '2026-02-13 03:02:45', 2, 2, 'trans_698e9455b3c2c'),
(74, 'h (1/2)', 250.00, 'receita', '2026-02-13', 18, 'pago', NULL, '2026-02-13 03:05:54', 1, 2, 'trans_698e95123bbd9'),
(75, 'h (2/2)', 250.00, 'receita', '2026-03-13', 18, 'pendente', NULL, '2026-02-13 03:05:54', 2, 2, 'trans_698e95123bbd9'),
(76, 'ds (1/2)', 7.50, 'despesa', '2026-02-13', 14, 'pendente', NULL, '2026-02-13 16:07:36', 1, 2, 'trans_698f4c48124a2'),
(77, 'ds (2/2)', 7.50, 'despesa', '2026-03-13', 14, 'pendente', NULL, '2026-02-13 16:07:36', 2, 2, 'trans_698f4c48124a2');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `metas`
--
ALTER TABLE `metas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoria_id` (`categoria_id`);

--
-- Índices de tabela `transacoes`
--
ALTER TABLE `transacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoria_id` (`categoria_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de tabela `metas`
--
ALTER TABLE `metas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `transacoes`
--
ALTER TABLE `transacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `metas`
--
ALTER TABLE `metas`
  ADD CONSTRAINT `metas_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `transacoes`
--
ALTER TABLE `transacoes`
  ADD CONSTRAINT `transacoes_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
