<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// --- CONFIGURAÇÃO DE LOCALE E DATA ---
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
date_default_timezone_set('America/Sao_Paulo');

// --- FILTROS & PAGINAÇÃO ---
$itens_por_pagina = 7;
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_SANITIZE_NUMBER_INT) ?? 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Filtros de Data (Padrão: Mês Atual)
$data_inicio = filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_SPECIAL_CHARS) ?? date('Y-m-01');
$data_fim = filter_input(INPUT_GET, 'data_fim', FILTER_SANITIZE_SPECIAL_CHARS) ?? date('Y-m-t');
$busca = filter_input(INPUT_GET, 'busca', FILTER_SANITIZE_SPECIAL_CHARS);

// --- CONSULTAS SQL ---

// 1. Resumo
$sqlResumo = "SELECT 
    SUM(CASE WHEN tipo = 'receita' AND status = 'pago' THEN valor ELSE 0 END) as entradas, 
    SUM(CASE WHEN tipo = 'despesa' AND status = 'pago' THEN valor ELSE 0 END) as saidas, 
    SUM(CASE WHEN tipo = 'despesa' AND status = 'pendente' THEN valor ELSE 0 END) as contas_a_pagar 
    FROM transacoes WHERE data_transacao BETWEEN :inicio AND :fim";
if ($busca) {
    $sqlResumo .= " AND descricao LIKE :busca";
}
$stmt = $pdo->prepare($sqlResumo);
$stmt->bindValue(':inicio', $data_inicio);
$stmt->bindValue(':fim', $data_fim);
if ($busca) {
    $stmt->bindValue(':busca', '%' . $busca . '%');
}
$stmt->execute();
$resumo = $stmt->fetch();
$saldo = $resumo['entradas'] - $resumo['saidas'];

// 2. Contagem
$sqlCount = "SELECT COUNT(*) as total FROM transacoes WHERE data_transacao BETWEEN :inicio AND :fim";
if ($busca) {
    $sqlCount .= " AND descricao LIKE :busca";
}
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->bindValue(':inicio', $data_inicio);
$stmtCount->bindValue(':fim', $data_fim);
if ($busca) {
    $stmtCount->bindValue(':busca', '%' . $busca . '%');
}
$stmtCount->execute();
$total_transacoes = $stmtCount->fetch()['total'];
$total_paginas = ceil($total_transacoes / $itens_por_pagina);

// 3. Lista
$sqlTransacoes = "SELECT t.*, c.nome as cat_nome, c.cor_hex 
                  FROM transacoes t 
                  JOIN categorias c ON t.categoria_id = c.id 
                  WHERE t.data_transacao BETWEEN :inicio AND :fim";
if ($busca) {
    $sqlTransacoes .= " AND t.descricao LIKE :busca";
}
$sqlTransacoes .= " ORDER BY t.data_transacao DESC, t.id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sqlTransacoes);
$stmt->bindValue(':inicio', $data_inicio);
$stmt->bindValue(':fim', $data_fim);
if ($busca) {
    $stmt->bindValue(':busca', '%' . $busca . '%');
}
$stmt->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$transacoes = $stmt->fetchAll();

// 4. Categorias
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY tipo ASC, nome ASC")->fetchAll();
$categoriasJson = [];
foreach ($categorias as $c) {
    $categoriasJson[] = ['id' => $c['id'], 'text' => $c['nome'], 'tipo' => $c['tipo']];
}

// 5. Gráfico
$sqlChart = "SELECT c.nome, SUM(t.valor) as total, c.cor_hex 
             FROM transacoes t 
             JOIN categorias c ON t.categoria_id = c.id 
             WHERE t.data_transacao BETWEEN :inicio AND :fim 
             GROUP BY c.id";
$stmt = $pdo->prepare($sqlChart);
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$chartData = $stmt->fetchAll();

$totalGrafico = 0;
foreach ($chartData as $item) $totalGrafico += $item['total'];
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Finance Pro</title>

    <link rel="icon" type="image/png" href="assets/img/favicon.png">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/style.css">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/index.js"></script>
    <script src="https://npmcdn.com/flatpickr@4.6.13/dist/l10n/pt.js"></script>

    <style>
        /* =========================================
           ESTILO DO BOTÃO DE DATA (CLEAN INDUSTRIAL)
           ========================================= */
        .date-trigger-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #1e293b;
            /* Fundo sólido do painel */
            border: 1px solid #334155;
            /* Borda cinza azulada */
            border-radius: 8px;
            /* Cantos levemente arredondados */
            padding: 0 16px;
            height: 46px;
            min-width: 220px;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .date-trigger-btn:hover {
            border-color: #60a5fa;
            /* Azul no hover */
        }

        .trigger-left {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
        }

        .trigger-icon {
            color: #94a3b8;
            font-size: 16px;
        }

        .trigger-input {
            background: transparent;
            border: none;
            outline: none;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 14px;
            width: 100%;
            cursor: pointer;
            text-transform: capitalize;
        }

        .trigger-arrow {
            color: #64748b;
            font-size: 12px;
        }

        /* =========================================
           CALENDÁRIO GEOMÉTRICO (SEM ERROS DE LAYOUT)
           ========================================= */
        .flatpickr-calendar {
            background: #111827 !important;
            /* Quase preto */
            border: 1px solid #374151 !important;
            border-radius: 12px !important;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5) !important;
            width: 180px !important;
            /* Largura fixa segura */
            padding: 15px !important;
            margin-top: 5px !important;
        }

        .flatpickr-calendar::before,
        .flatpickr-calendar::after {
            display: none !important;
        }

        /* HEADER */
        .flatpickr-months {
            border-bottom: 1px solid #094fbf !important;
            padding-bottom: 10px !important;
            margin-bottom: 10px !important;
            background: transparent !important;
        }

        .flatpickr-current-month {
            position: static !important;
            width: auto !important;
            padding: 0 !important;
        }

        .flatpickr-current-month input.cur-year {
            font-weight: 700 !important;
            font-size: 16px !important;
            color: #fff !important;
        }

        .numInputWrapper:hover {
            background: transparent !important;
        }

        /* SETAS */
        .flatpickr-prev-month,
        .flatpickr-next-month {
            position: static !important;
            height: 28px !important;
            width: 28px !important;
            border-radius: 6px !important;
            background: #1f2937 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .flatpickr-prev-month:hover,
        .flatpickr-next-month:hover {
            background: #374151 !important;
        }

        .flatpickr-prev-month svg,
        .flatpickr-next-month svg {
            width: 10px !important;
            height: 10px !important;
            fill: #cbd5e1 !important;
        }

        /* O GRID REPARADO */
        .flatpickr-monthSelect-months {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr) !important;
            /* 3 Colunas Exatas */
            gap: 10px !important;
            /* Espaço uniforme */
            width: 100% !important;
            justify-content: center !important;
        }

        /* O MÊS (RETÂNGULO LIMPO) */
        .flatpickr-monthSelect-month {
            background-color: transparent !important;
            border: 1px solid transparent !important;
            border-radius: 8px !important;
            /* Retângulo levemente arredondado */
            color: #9ca3af !important;
            /* Cinza */
            height: 40px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            transition: all 0.2s ease !important;
            box-shadow: none !important;
            margin: 0 !important;
            text-transform: capitalize !important;
        }

        /* Hover */
        .flatpickr-monthSelect-month:hover {
            /*background-color: #ffffff !important;*/
            color: #fff !important;
            border-color: #374151 !important;
        }

        /* SELECIONADO (AZUL SÓLIDO - SEM GLOW ESTRANHO) */
        .flatpickr-monthSelect-month.selected {
            background-color: #2563eb !important;
            /* Azul Sólido */
            color: #fff !important;
            font-weight: 600 !important;
            border-color: #2563eb !important;
            box-shadow: none !important;
        }

        /* --- RESTO DO CSS --- */
        .export-dropdown {
            position: relative;
            display: inline-block;
        }

        .btn-export {
            display: flex;
            align-items: center;
            gap: 10px;
            height: 46px;
            padding: 0 18px;
            background-color: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text-color);
            cursor: pointer;
            transition: 0.3s;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
        }

        .export-menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 10px);
            background-color: #1e293b;
            min-width: 200px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow: hidden;
        }

        .export-menu a {
            color: #e2e8f0;
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            transition: 0.2s;
        }

        .export-menu a:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(5, 8, 16, 0.9);
            backdrop-filter: blur(5px);
            z-index: 3000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: 0.3s;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: var(--bg-modal);
            width: 100%;
            max-width: 450px;
            border-radius: 16px;
            padding: 0;
            transform: translateY(30px);
            transition: 0.3s;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-overlay.active .modal {
            transform: translateY(0);
        }

        .modal-body {
            overflow-y: auto;
            padding: 30px;
        }

        .chart-panel {
            background: var(--bg-panel);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.03);
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 650px;
            overflow: hidden;
        }

        .chart-content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 10px;
            overflow: hidden;
        }

        .chart-wrapper {
            height: 260px !important;
            min-height: 260px;
            flex-shrink: 0;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        canvas#myChart {
            max-height: 100% !important;
            width: auto !important;
        }

        .chart-details-list {
            flex-grow: 1;
            overflow-y: auto;
            padding-right: 5px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 15px;
        }

        .chart-detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            transition: 0.2s;
            flex-shrink: 0;
        }

        .chart-detail-item:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(3px);
        }

        .custom-scroll::-webkit-scrollbar {
            width: 5px;
        }

        .custom-scroll::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .custom-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
        }

        .ts-dropdown {
            z-index: 99999 !important;
        }

        .ts-dropdown .ts-dropdown-content {
            max-height: 200px !important;
            overflow-y: auto !important;
        }

        /* --- CORREÇÃO: REMOVER FUNDOS PRETOS --- */

        /* 1. Remove o fundo preto do campo de data (Lá no topo) */
        .date-trigger-btn input,
        .flatpickr-input,
        input.flatpickr-mobile {
            background-color: transparent !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        /* 2. Remove o fundo preto atrás do Ano (2026) dentro do calendário */
        .flatpickr-current-month input.cur-year,
        .flatpickr-current-month .numInputWrapper {
            background-color: transparent !important;
            background: transparent !important;
            box-shadow: none !important;
            border: none !important;
        }

        /* Garante que o texto fique branco */
        .flatpickr-current-month input.cur-year {
            color: #fff !important;
        }
    </style>
</head>

<body>

    <div id="toast-container"></div>

    <div class="app-container">
        <div class="sidebar-overlay" id="myOverlay" onclick="closeSidebar()"></div>

        <aside class="sidebar" id="mySidebar">
            <div class="logo-container">
                <div class="logo-icon"><i class="fas fa-wallet"></i></div>
                <div class="logo-text">
                    <h2>Finance<span>Pro</span></h2><small>Gestão Inteligente</small>
                </div>
            </div>
            <nav class="sidebar-menu">
                <p class="menu-label">Menu Principal</p>
                <a href="index.php" class="menu-item active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
                <a href="relatorios.php" class="menu-item"><i class="fas fa-chart-pie"></i> <span>Relatórios</span></a>
                <a href="metas.php" class="menu-item"><i class="fas fa-bullseye"></i> <span>Metas</span></a>
                <p class="menu-label">Sistema</p>
                <a href="configuracoes.php" class="menu-item"><i class="fas fa-cog"></i> <span>Configurações</span></a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-card">
                    <div class="avatar">H</div>
                    <div class="user-info"><strong>Hiann Oliveira</strong><span>Admin</span></div><a href="#" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>
        </aside>

        <main>
            <header class="dashboard-header">
                <div class="header-top">
                    <div class="header-title-area">
                        <button type="button" class="btn-menu" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                        <div class="header-title">
                            <h1>Visão Geral</h1>
                            <p>Bem-vindo de volta, Hiann.</p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <form method="GET" action="index.php" class="search-form" style="margin: 0;">
                            <input type="hidden" name="data_inicio" value="<?= $data_inicio ?>">
                            <input type="hidden" name="data_fim" value="<?= $data_fim ?>">
                            <div class="search-input-container"><i class="fas fa-search"></i><input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar..." autocomplete="off"></div>
                        </form>

                        <div class="date-trigger-btn" id="pickerTrigger">
                            <div class="trigger-left">
                                <i class="fas fa-calendar-alt trigger-icon"></i>
                                <input type="text" id="datePicker" value="<?= $data_inicio ?>" readonly class="trigger-input" placeholder="Selecione...">
                            </div>
                            <i class="fas fa-chevron-down trigger-arrow"></i>
                        </div>

                        <div class="export-dropdown">
                            <button class="btn-export" id="btnExportar">
                                <i class="fas fa-file-export" style="color: var(--primary-color);"></i>
                                <span class="hide-mobile">Exportar</span>
                                <i class="fas fa-chevron-down" style="font-size: 10px; margin-left: 5px; opacity: 0.5;"></i>
                            </button>
                            <div id="menuExportar" class="export-menu">
                                <a href="actions/export.php?data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>"><i class="fas fa-file-excel" style="color: #10b981;"></i> <span>Planilha Excel</span></a>
                                <a href="actions/print.php?data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>" target="_blank"><i class="fas fa-file-pdf" style="color: #ef4444;"></i> <span>Relatório PDF</span></a>
                            </div>
                        </div>

                        <button class="btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> <span class="hide-mobile">Nova Transação</span></button>
                    </div>
                </div>
            </header>

            <section class="cards-grid">
                <div class="card">
                    <div class="card-icon green"><i class="fas fa-arrow-up"></i></div>
                    <div class="card-info">
                        <h3>Entradas</h3>
                        <p id="total-entradas"><?= formatarMoeda($resumo['entradas']) ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon red"><i class="fas fa-arrow-down"></i></div>
                    <div class="card-info">
                        <h3>Saídas</h3>
                        <p id="total-saidas"><?= formatarMoeda($resumo['saidas']) ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon blue"><i class="fas fa-wallet"></i></div>
                    <div class="card-info">
                        <h3>Saldo</h3>
                        <p id="total-saldo"><?= formatarMoeda($saldo) ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="card-info">
                        <h3>Pendente</h3>
                        <p id="total-pendente" class="text-warning"><?= formatarMoeda($resumo['contas_a_pagar']) ?></p>
                    </div>
                </div>
            </section>

            <section class="content-grid">
                <div class="transactions-panel">
                    <div class="panel-header">
                        <h3>Transações Recentes</h3> <small style="color: var(--text-muted)"><?= $total_transacoes ?> registros</small>
                    </div>
                    <?php if (count($transacoes) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Descrição</th>
                                    <th>Categoria</th>
                                    <th>Data</th>
                                    <th>Valor</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transacoes as $t):

                                    $hoje = date('Y-m-d');

                                    // 1. Verifica se a data da conta é menor que hoje
                                    $data_passou = ($t['data_transacao'] < $hoje);

                                    // 2. Verifica se o status atual é pendente
                                    $is_pendente = ($t['status'] === 'pendente');
                                ?>
                                    <tr>
                                        <td><span class="status-badge <?= $t['status'] ?>" style="cursor: pointer;" onclick="mudarStatusConta(<?= $t['id'] ?>, this)"><?= getStatusLabel($t['status']) ?></span></td>
                                        <td class="desc-cell">
                                            <div class="icon-cat" style="background: <?= $t['cor_hex'] ?>20; color: <?= $t['cor_hex'] ?>"><i class="fas fa-receipt"></i></div> <?= htmlspecialchars($t['descricao']) ?>
                                        </td>
                                        <td><?= $t['cat_nome'] ?></td>

                                        <td>
                                            <?= date('d/m/Y', strtotime($t['data_transacao'])) ?>

                                            <?php if ($data_passou): ?>
                                                <br>
                                                <span class="alerta-atraso" style="color: #ef4444; font-size: 11px; font-weight: 600; display: <?= $is_pendente ? 'inline-flex' : 'none' ?>; align-items: center; gap: 4px; margin-top: 2px;">
                                                    <i class="fas fa-exclamation-circle"></i> Atrasada
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="amount <?= $t['tipo'] ?>"><?= $t['tipo'] == 'despesa' ? '-' : '+' ?> <?= formatarMoeda($t['valor']) ?></td>
                                        <td style="display: flex; gap: 8px;">
                                            <?php
                                            $is_grupo = !empty($t['hash_pedido']);
                                            $num_parcela = isset($t['parcela_atual']) ? (int)$t['parcela_atual'] : 0;
                                            $pode_deletar = (!$is_grupo) || ($is_grupo && $num_parcela === 1);
                                            ?>

                                            <button class="btn-action edit" onclick='editarTransacao(<?= json_encode($t) ?>)'><i class="fas fa-pen"></i></button>

                                            <?php if ($pode_deletar): ?>
                                                <a href="#" onclick="confirmarExclusao(<?= $t['id'] ?>)" class="btn-action delete"><i class="fas fa-trash"></i></a>
                                            <?php else: ?>
                                                <span class="btn-action" style="opacity: 0.3; cursor: not-allowed;" title="Vá até a Parcela 1 para excluir este grupo"><i class="fas fa-trash"></i></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($total_paginas > 1): ?>
                            <div class="pagination">
                                <?php $urlParams = "&data_inicio=$data_inicio&data_fim=$data_fim&busca=$busca"; ?>
                                <?php if ($pagina_atual > 1): ?><a href="?pagina=<?= $pagina_atual - 1 ?><?= $urlParams ?>" class="page-link"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                                <span class="page-info">Página <?= $pagina_atual ?> de <?= $total_paginas ?></span>
                                <?php if ($pagina_atual < $total_paginas): ?><a href="?pagina=<?= $pagina_atual + 1 ?><?= $urlParams ?>" class="page-link"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?><p class="empty-state">Nenhuma transação encontrada.</p><?php endif; ?>
                </div>

                <div class="chart-panel">
                    <div class="panel-header" style="margin-bottom: 10px; border-bottom: none;">
                        <h3>Movimentação por Categoria</h3>
                    </div>
                    <div class="chart-content-wrapper">
                        <div class="chart-wrapper"><canvas id="myChart"></canvas></div>
                        <div class="chart-details-list custom-scroll">
                            <?php
                            usort($chartData, function ($a, $b) {
                                return $b['total'] - $a['total'];
                            });
                            $totalGeralChart = array_sum(array_column($chartData, 'total'));
                            foreach ($chartData as $cat):
                                $porcentagem = $totalGeralChart > 0 ? ($cat['total'] / $totalGeralChart) * 100 : 0;
                            ?>
                                <div class="chart-detail-item">
                                    <div class="detail-left">
                                        <span class="detail-dot" style="background-color: <?= $cat['cor_hex'] ?>;"></span>
                                        <span class="detail-name"><?= $cat['nome'] ?></span>
                                    </div>
                                    <div class="detail-right">
                                        <span class="detail-percent"><?= number_format($porcentagem, 1) ?>%</span>
                                        <span class="detail-value"><?= formatarMoeda($cat['total']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($chartData)): ?><div style="height: 100%; display: flex; align-items: center; justify-content: center;">
                                    <p style="color: #64748b; font-size: 12px;">Sem dados.</p>
                                </div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div class="modal-overlay" id="transactionModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle">Nova Transação</h3>
                <div class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></div>
            </div>
            <form action="actions/save.php" method="POST" id="formTransacao">
                <input type="hidden" name="id" id="inputId">
                <input type="hidden" name="url_inicio" value="<?= $data_inicio ?>">
                <input type="hidden" name="url_fim" value="<?= $data_fim ?>">
                <div class="transaction-type-wrapper">
                    <input type="radio" name="tipo" value="despesa" id="typeDespesa" checked onclick="updateModalTheme(); updateCategoryOptions('despesa');">
                    <label class="type-label label-despesa" for="typeDespesa"><i class="fas fa-arrow-down"></i> Saída</label>
                    <input type="radio" name="tipo" value="receita" id="typeReceita" onclick="updateModalTheme(); updateCategoryOptions('receita');">
                    <label class="type-label label-receita" for="typeReceita"><i class="fas fa-arrow-up"></i> Entrada</label>
                </div>
                <div class="modal-body">
                    <div class="big-amount-wrapper">
                        <label>Valor Total</label>
                        <div class="big-input-container">
                            <span class="currency-symbol">R$</span>
                            <input type="number" step="0.01" name="valor" id="inputValor" class="big-input" placeholder="0,00" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label class="input-label">Descrição</label>
                        <input type="text" name="descricao" id="inputDesc" class="styled-input" placeholder="Ex: Mercado, Assinatura..." required autocomplete="off">
                    </div>
                    <div class="input-group">
                        <label class="input-label">Recorrência</label>
                        <div style="display: flex; gap: 10px;">
                            <select name="repeticao" id="inputRepeticao" class="styled-input" style="flex: 2;">
                                <option value="unica">Única (Uma vez)</option>
                                <option value="parcelada">Parcelada (Divide Valor)</option>
                                <option value="fixa">Fixa (Repete Valor)</option>
                            </select>
                            <input type="number" name="parcelas" id="inputParcelas" class="styled-input" placeholder="Vezes" min="2" max="360" value="2" style="flex: 1; display: none;">
                        </div>
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 15px;">
                        <div class="input-group">
                            <label class="input-label">Data Início</label>
                            <input type="date" name="data" id="inputData" class="styled-input" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="input-group">
                            <label class="input-label">Categoria</label>
                            <div class="ts-clean-wrapper">
                                <select name="categoria" id="inputCategoria" class="ts-clean" required placeholder="Selecione..."></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <label class="switch-wrapper" for="inputStatus">
                            <input type="checkbox" id="inputStatus" name="status_pago" checked>
                            <div class="ios-switch"></div>
                            <span class="switch-label">Pago / Recebido</span>
                        </label>
                        <button type="submit" class="btn-save" id="btnSave">Confirmar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        const chartDataPHP = <?= json_encode($chartData) ?>;
        const allCategories = <?= json_encode($categoriasJson) ?>;
        let tomSelectInstance = null;

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('status') === 'success') {
            document.addEventListener("DOMContentLoaded", () => showToast("Salvo com sucesso!", "success"));
        }

        document.addEventListener("DOMContentLoaded", function() {
            if (document.getElementById('inputCategoria')) {
                tomSelectInstance = new TomSelect("#inputCategoria", {
                    create: false,
                    sortField: {
                        field: "text",
                        direction: "asc"
                    },
                    placeholder: "Selecione...",
                    maxOptions: null
                });
                updateCategoryOptions('despesa');
            }

            // --- JS DO CALENDÁRIO: Shorthand TRUE (Crucial para o grid perfeito) ---
            if (document.getElementById('datePicker')) {
                flatpickr("#datePicker", {
                    plugins: [new monthSelectPlugin({
                        shorthand: true,
                        dateFormat: "Y-m-d",
                        altFormat: "F Y",
                        theme: "dark"
                    })],
                    locale: "pt",
                    altInput: true,
                    altFormat: "F Y",
                    disableMobile: "true",
                    defaultDate: "<?= $data_inicio ?>",
                    onChange: function(dates) {
                        if (dates[0]) {
                            let y = dates[0].getFullYear(),
                                m = dates[0].getMonth() + 1;
                            let start = `${y}-${String(m).padStart(2, '0')}-01`;
                            let end = `${y}-${String(m).padStart(2, '0')}-${new Date(y, m, 0).getDate()}`;
                            window.location.href = `?data_inicio=${start}&data_fim=${end}`;
                        }
                    }
                });
            }

            const btn = document.getElementById('btnExportar'),
                menu = document.getElementById('menuExportar');
            if (btn && menu) {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                });
                document.addEventListener('click', () => menu.style.display = 'none');
            }
            const selRep = document.getElementById('inputRepeticao'),
                inpParc = document.getElementById('inputParcelas');
            if (selRep && inpParc) {
                selRep.addEventListener('change', function() {
                    inpParc.style.display = (this.value === 'parcelada' || this.value === 'fixa') ? 'block' : 'none';
                    if (this.value === 'unica') inpParc.value = '';
                });
            }
        });

        window.toggleSidebar = function() {
            document.getElementById('mySidebar').classList.toggle('active');
            document.getElementById('myOverlay').classList.toggle('active');
        }
        window.closeSidebar = function() {
            document.getElementById('mySidebar').classList.remove('active');
            document.getElementById('myOverlay').classList.remove('active');
        }
        window.openModal = function() {
            document.getElementById('transactionModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        window.closeModal = function() {
            document.getElementById('transactionModal').classList.remove('active');
            document.body.style.overflow = 'auto';
            document.getElementById('formTransacao').reset();
            document.getElementById('modalTitle').innerText = 'Nova Transação';
            document.getElementById('inputId').value = '';
            document.getElementById('typeDespesa').checked = true;
            updateModalTheme();
            updateCategoryOptions('despesa');
            if (tomSelectInstance) tomSelectInstance.clear();
        }
        window.updateCategoryOptions = function(tipo) {
            if (!tomSelectInstance) return;
            tomSelectInstance.clear();
            tomSelectInstance.clearOptions();
            allCategories.filter(c => c.tipo === tipo).forEach(c => tomSelectInstance.addOption({
                value: c.id,
                text: c.text
            }));
            tomSelectInstance.refreshOptions(false);
        }
        window.updateModalTheme = function() {
            const isRec = document.getElementById('typeReceita').checked,
                btn = document.getElementById('btnSave');
            btn.classList.toggle('income-mode', isRec);
            btn.classList.toggle('expense-mode', !isRec);
        }
        window.editarTransacao = function(t) {
            openModal();
            document.getElementById('modalTitle').innerText = 'Editar Transação';
            document.getElementById('inputId').value = t.id;
            document.getElementById('inputValor').value = t.valor;
            document.getElementById('inputDesc').value = t.descricao;
            document.getElementById('inputData').value = t.data_transacao.split(' ')[0];
            document.getElementById(t.tipo === 'receita' ? 'typeReceita' : 'typeDespesa').checked = true;
            updateModalTheme();
            updateCategoryOptions(t.tipo);
            setTimeout(() => {
                if (tomSelectInstance) tomSelectInstance.setValue(t.categoria_id);
            }, 50);
            document.getElementById('inputStatus').checked = (t.status === 'pago');
        }
        window.confirmarExclusao = function(id) {
            if (confirm('Excluir?')) {
                window.location.href = `actions/delete.php?id=${id}&data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>`;
            }
        }
        window.toggleStatus = function(id, element) {
            // 1. Guarda o estado atual caso precise reverter (backup)
            const originalClass = element.className;
            const originalText = element.innerText;

            // 2. Lógica Visual Instantânea (Optimistic UI)
            const isAtualmentePago = element.classList.contains('pago');

            // Define o novo visual
            if (isAtualmentePago) {
                element.className = 'status-badge pendente';
                element.innerText = 'Pendente';
            } else {
                element.className = 'status-badge pago';
                element.innerText = 'Pago';
            }

            // 3. Envia para o servidor em segundo plano (Sem recarregar a tela)
            fetch('actions/toggle_status.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        // Se der erro no servidor, desfaz a mudança visual
                        element.className = originalClass;
                        element.innerText = originalText;
                        alert('Erro ao atualizar status.');
                    } else {
                        // Sucesso! (Opcional: Mostrar um toast pequeno)
                        // Se quiser atualizar os totais lá em cima sem recarregar,
                        // seria necessário um código extra, mas para o botão isso basta.
                    }
                })
                .catch(error => {
                    // Erro de conexão
                    console.error('Erro:', error);
                    element.className = originalClass;
                    element.innerText = originalText;
                });
        }

        new Chart(document.getElementById('myChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: chartDataPHP.map(i => i.nome),
                datasets: [{
                    data: chartDataPHP.map(i => i.total),
                    backgroundColor: chartDataPHP.map(i => i.cor_hex),
                    borderWidth: 0,
                    hoverOffset: 10,
                    borderRadius: 5,
                    cutout: '75%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: 20
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(30, 41, 59, 0.95)',
                        padding: 12,
                        cornerRadius: 8,
                        bodyFont: {
                            family: 'Poppins'
                        },
                        callbacks: {
                            label: (c) => " " + c.raw.toLocaleString('pt-BR', {
                                style: 'currency',
                                currency: 'BRL'
                            })
                        }
                    }
                }
            },
            plugins: [{
                id: 'textCenter',
                beforeDraw: function(chart) {
                    var w = chart.width,
                        h = chart.height,
                        ctx = chart.ctx;
                    ctx.restore();
                    var fs = (h / 140).toFixed(2);
                    ctx.font = "bold " + fs + "em Poppins";
                    ctx.textBaseline = "middle";
                    ctx.fillStyle = "#fff";
                    var text = (<?= $totalGrafico ?>).toLocaleString('pt-BR', {
                        style: 'currency',
                        currency: 'BRL'
                    });
                    var tx = Math.round((w - ctx.measureText(text).width) / 2),
                        ty = h / 2;
                    ctx.fillText(text, tx, ty);
                    ctx.font = "normal " + (fs * 0.4) + "em Poppins";
                    ctx.fillStyle = "#94a3b8";
                    var lbl = "Movimentado",
                        lx = Math.round((w - ctx.measureText(lbl).width) / 2);
                    ctx.fillText(lbl, lx, ty - 20);
                    ctx.save();
                }
            }]
        });
    </script>
    <script src="assets/js/script.js?v=<?= time() ?>"></script>

    <script>
async function mudarStatusConta(id, element) {
    try {
        const textoOriginal = element.innerText;
        element.innerText = '...';
        element.style.opacity = '0.5';

        // 1. Pega as datas da URL
        const urlParams = new URLSearchParams(window.location.search);
        const hoje = new Date();
        const inicioPadrao = new Date(hoje.getFullYear(), hoje.getMonth(), 1).toISOString().split('T')[0];
        const fimPadrao = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0).toISOString().split('T')[0];

        const dataInicio = urlParams.get('data_inicio') || inicioPadrao;
        const dataFim = urlParams.get('data_fim') || fimPadrao;

        // 2. Chama o PHP
        const response = await fetch('actions/toggle_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, data_inicio: dataInicio, data_fim: dataFim })
        });

        // 3. Leitura bruta (Se o PHP quebrar, o erro vai aparecer aqui)
        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            alert("ERRO CRÍTICO NO PHP! O servidor respondeu:\n\n" + text);
            element.innerText = textoOriginal;
            element.style.opacity = '1';
            return;
        }

        // 4. Se deu tudo certo, faz a mágica na tela
        if (result.success) {
            // Atualiza o botão
            element.className = `status-badge ${result.novo_status}`;
            element.innerText = result.novo_status === 'pago' ? 'PAGO' : 'PENDENTE';
            element.style.opacity = '1';

            // Atualiza o aviso de atraso
            let linhaTabela = element.closest('tr');
            if (linhaTabela) {
                let avisoAtraso = linhaTabela.querySelector('.alerta-atraso');
                if (avisoAtraso) {
                    avisoAtraso.style.display = result.novo_status === 'pago' ? 'none' : 'inline-flex';
                }
            }

            // Atualiza os totais lá em cima
            const fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
            document.getElementById('total-entradas').innerText = fmt.format(result.totais.entradas);
            document.getElementById('total-saidas').innerText = fmt.format(result.totais.saidas);
            
            const elSaldo = document.getElementById('total-saldo');
            elSaldo.innerText = fmt.format(result.totais.saldo);
            elSaldo.style.color = result.totais.saldo >= 0 ? '#10b981' : '#ef4444';

            document.getElementById('total-pendente').innerText = fmt.format(result.totais.pendente);

        } else {
            alert("O PHP não permitiu a alteração: " + result.message);
            element.innerText = textoOriginal;
            element.style.opacity = '1';
        }

    } catch (error) {
        alert("Ocorreu um erro de conexão com o servidor.");
        console.error(error);
        element.innerText = 'ERRO';
        element.style.opacity = '1';
    }
}
</script>

</body>

</html>