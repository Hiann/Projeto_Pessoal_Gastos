<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// --- CONFIGURAÇÃO DE DATAS ---
$data_filtro = filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_SPECIAL_CHARS) ?? date('Y-m-01');
$inicio_mes = date('Y-m-01', strtotime($data_filtro));
$fim_mes    = date('Y-m-t', strtotime($data_filtro));
$inicio_semestre = date('Y-m-01', strtotime("$inicio_mes -5 months"));

// --- CONSULTAS SQL (Mantidas idênticas para garantir a funcionalidade) ---

// 1. CARDS
$sqlResumo = "SELECT 
    SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) as entradas,
    SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as saidas
    FROM transacoes 
    WHERE data_transacao BETWEEN :inicio AND :fim";
$stmt = $pdo->prepare($sqlResumo);
$stmt->execute([':inicio' => $inicio_mes, ':fim' => $fim_mes]);
$resumo = $stmt->fetch();
$balanco = $resumo['entradas'] - $resumo['saidas'];
$taxa_economia = ($resumo['entradas'] > 0) ? ($balanco / $resumo['entradas']) * 100 : 0;

// 2. CATEGORIAS
$sqlCat = "SELECT c.nome, SUM(t.valor) as total, c.cor_hex 
           FROM transacoes t 
           JOIN categorias c ON t.categoria_id = c.id 
           WHERE t.data_transacao BETWEEN :inicio AND :fim 
           AND t.tipo = 'despesa' 
           GROUP BY c.id 
           ORDER BY total DESC";
$stmt = $pdo->prepare($sqlCat);
$stmt->execute([':inicio' => $inicio_mes, ':fim' => $fim_mes]);
$catsData = $stmt->fetchAll();

// Maior Gasto/Entrada
$sqlMaiorGasto = "SELECT descricao, valor, c.nome as cat_nome 
                  FROM transacoes t JOIN categorias c ON t.categoria_id = c.id
                  WHERE t.data_transacao BETWEEN :inicio AND :fim AND t.tipo = 'despesa' 
                  ORDER BY valor DESC LIMIT 1";
$stmt = $pdo->prepare($sqlMaiorGasto);
$stmt->execute([':inicio' => $inicio_mes, ':fim' => $fim_mes]);
$maiorGasto = $stmt->fetch();

$sqlMaiorEntrada = "SELECT descricao, valor, c.nome as cat_nome 
                    FROM transacoes t JOIN categorias c ON t.categoria_id = c.id
                    WHERE t.data_transacao BETWEEN :inicio AND :fim AND t.tipo = 'receita' 
                    ORDER BY valor DESC LIMIT 1";
$stmt = $pdo->prepare($sqlMaiorEntrada);
$stmt->execute([':inicio' => $inicio_mes, ':fim' => $fim_mes]);
$maiorEntrada = $stmt->fetch();

// 3. EVOLUÇÃO
$sqlEvo = "SELECT 
            DATE_FORMAT(data_transacao, '%Y-%m') as mes_ano,
            SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) as entradas,
            SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as saidas
           FROM transacoes 
           WHERE data_transacao BETWEEN :inicio AND :fim
           GROUP BY mes_ano ORDER BY mes_ano ASC";
$stmt = $pdo->prepare($sqlEvo);
$stmt->execute([':inicio' => $inicio_semestre, ':fim' => $fim_mes]);
$evoData = $stmt->fetchAll();

$labelsEvo = []; $dataEntradas = []; $dataSaidas = [];
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
foreach($evoData as $d) {
    $mesNome = strftime('%b', strtotime($d['mes_ano'] . '-01')); // Apenas as 3 letras (Fev, Mar)
    $labelsEvo[] = ucfirst($mesNome);
    $dataEntradas[] = $d['entradas'];
    $dataSaidas[] = $d['saidas'];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios | Finance Pro</title>
    
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/style.css">
    
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/index.js"></script>
    <script src="https://npmcdn.com/flatpickr@4.6.13/dist/l10n/pt.js"></script>

    <style>
        /* CSS ESPECIFICO PARA DEIXAR MAIS BONITO */
        :root {
            --card-bg-gradient: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
            --chart-panel-bg: #1e293b;
        }
        
        body { font-family: 'Outfit', sans-serif; } /* Fonte mais moderna que Poppins */

        /* Melhoria nos Cards do Topo */
        .cards-grid .card {
            background: var(--card-bg-gradient);
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            border-radius: 16px;
            position: relative;
            overflow: hidden;
        }
        
        /* Detalhe colorido no fundo do card */
        .cards-grid .card::after {
            content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 3px;
        }
        .cards-grid .card:nth-child(1)::after { background: #10b981; } /* Verde */
        .cards-grid .card:nth-child(2)::after { background: #ef4444; } /* Vermelho */
        .cards-grid .card:nth-child(3)::after { background: #3b82f6; } /* Azul */
        .cards-grid .card:nth-child(4)::after { background: #f59e0b; } /* Laranja */

        .card-info small {
            font-size: 0.75rem; letter-spacing: 1px; opacity: 0.7; font-weight: 500;
        }
        .card-info h3 {
            font-size: 1.8rem; font-weight: 700; margin-top: 5px;
        }

        /* Melhoria nos Painéis de Gráfico */
        .chart-panel {
            background: var(--card-bg-gradient);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .chart-panel h3 {
            font-size: 1.1rem; margin-bottom: 25px; color: #fff; opacity: 0.9;
            display: flex; align-items: center; gap: 10px;
        }
        .chart-panel h3::before {
            content: ''; display: block; width: 4px; height: 16px; 
            background: #3b82f6; border-radius: 2px;
        }

        /* Scrollbar Personalizada para a lista */
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,0.02); }
        .custom-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        /* Estilo dos Mini Cards (Maior Gasto/Entrada) */
        .mini-stat-card {
            background: rgba(255,255,255,0.03); 
            border: 1px solid rgba(255,255,255,0.05);
            padding: 15px; border-radius: 12px; flex: 1;
            transition: transform 0.2s;
        }
        .mini-stat-card:hover { transform: translateY(-2px); background: rgba(255,255,255,0.05); }
    </style>
</head>
<body>

<div class="app-container">
    <div class="sidebar-overlay" id="myOverlay" onclick="closeSidebar()"></div>
    
    <aside class="sidebar" id="mySidebar">
        <div class="logo-container"><div class="logo-icon"><i class="fas fa-wallet"></i></div><div class="logo-text"><h2>Finance<span>Pro</span></h2><small>Gestão Inteligente</small></div></div>
        <nav class="sidebar-menu">
            <p class="menu-label">Menu Principal</p>
            <a href="index.php" class="menu-item"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
            <a href="relatorios.php" class="menu-item active"><i class="fas fa-chart-pie"></i> <span>Relatórios</span></a>
            <a href="metas.php" class="menu-item"><i class="fas fa-bullseye"></i> <span>Metas</span></a>
            <p class="menu-label">Sistema</p>
            <a href="configuracoes.php" class="menu-item"><i class="fas fa-cog"></i> <span>Configurações</span></a>
        </nav>
        <div class="sidebar-footer"><div class="user-card"><div class="avatar">H</div><div class="user-info"><strong>Hiann Oliveira</strong><span>Admin</span></div><a href="#" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a></div></div>
    </aside>

    <main>
        <header class="dashboard-header">
            <div class="header-top">
                <div class="header-title-area">
                    <button type="button" class="btn-menu" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                    <div class="header-title"><h1>Relatórios</h1><p>Análise financeira detalhada.</p></div>
                </div>
                <div class="header-actions">
                    <div class="month-picker-container" id="pickerTrigger">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="text" id="datePicker" value="<?= $data_filtro ?>" readonly placeholder="Mês">
                        <i class="fas fa-chevron-down" style="font-size: 12px; margin-left: auto; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </header>

        <section class="cards-grid">
            <div class="card">
                <div class="card-icon green" style="background: rgba(16, 185, 129, 0.15); color: #10b981;"><i class="fas fa-arrow-up"></i></div>
                <div class="card-info">
                    <small>ENTRADAS (MÊS)</small>
                    <h3><?= formatarMoeda($resumo['entradas']) ?></h3>
                </div>
            </div>
            <div class="card">
                <div class="card-icon red" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;"><i class="fas fa-arrow-down"></i></div>
                <div class="card-info">
                    <small>SAÍDAS (MÊS)</small>
                    <h3><?= formatarMoeda($resumo['saidas']) ?></h3>
                </div>
            </div>
            <div class="card">
                <div class="card-icon blue" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;"><i class="fas fa-wallet"></i></div>
                <div class="card-info">
                    <small>BALANÇO</small>
                    <h3><?= formatarMoeda($balanco) ?></h3>
                </div>
            </div>
            <div class="card">
                <div class="card-icon orange" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;"><i class="fas fa-piggy-bank"></i></div>
                <div class="card-info">
                    <small>ECONOMIA</small>
                    <h3><?= number_format($taxa_economia, 1, ',', '.') ?>%</h3>
                </div>
            </div>
        </section>

        <section class="content-grid" style="grid-template-columns: 1.5fr 1fr; gap: 25px;">
            
            <div class="chart-panel">
                <h3>Evolução Semestral</h3>
                <div style="height: 320px; width: 100%;">
                    <canvas id="evolutionChart"></canvas>
                </div>
            </div>

            <div class="chart-panel" style="display: flex; flex-direction: column;">
                <h3>Top Categorias</h3>
                
                <?php if(empty($catsData)): ?>
                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.5;">
                        <i class="fas fa-chart-pie" style="font-size: 50px; margin-bottom: 15px;"></i>
                        <p>Sem gastos este mês.</p>
                    </div>
                <?php else: ?>
                    
                    <div style="display: flex; gap: 15px; margin-bottom: 25px;">
                        <div class="mini-stat-card">
                            <div style="font-size: 10px; color: #10b981; font-weight: bold; margin-bottom: 5px;">MAIOR ENTRADA</div>
                            <div style="font-size: 15px; font-weight: bold; color: #fff;"><?= $maiorEntrada ? formatarMoeda($maiorEntrada['valor']) : '---' ?></div>
                            <div style="font-size: 11px; color: #888; margin-top: 2px; text-overflow: ellipsis; white-space: nowrap; overflow: hidden;"><?= $maiorEntrada['descricao'] ?? '-' ?></div>
                        </div>
                        <div class="mini-stat-card">
                            <div style="font-size: 10px; color: #ef4444; font-weight: bold; margin-bottom: 5px;">MAIOR GASTO</div>
                            <div style="font-size: 15px; font-weight: bold; color: #fff;"><?= $maiorGasto ? formatarMoeda($maiorGasto['valor']) : '---' ?></div>
                            <div style="font-size: 11px; color: #888; margin-top: 2px; text-overflow: ellipsis; white-space: nowrap; overflow: hidden;"><?= $maiorGasto['descricao'] ?? '-' ?></div>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 20px; flex: 1;">
                        <div style="height: 180px; position: relative;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        
                        <div class="custom-scroll" style="flex: 1; overflow-y: auto; padding-right: 5px; max-height: 150px;">
                            <?php foreach($catsData as $cat): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.03);">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 30px; height: 30px; border-radius: 8px; background-color: <?= $cat['cor_hex'] ?>20; display: flex; align-items: center; justify-content: center; color: <?= $cat['cor_hex'] ?>;">
                                        <i class="fas fa-tag" style="font-size: 12px;"></i>
                                    </div>
                                    <span style="font-size: 13px; font-weight: 500;"><?= $cat['nome'] ?></span>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 600; font-size: 13px;"><?= formatarMoeda($cat['total']) ?></div>
                                    <?php $perc = ($resumo['saidas'] > 0) ? ($cat['total'] / $resumo['saidas']) * 100 : 0; ?>
                                    <div style="font-size: 10px; color: #666;"><?= number_format($perc, 1) ?>%</div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<script>
    // Config do Calendário (Mês de Ano)
    flatpickr("#datePicker", {
        plugins: [ new monthSelectPlugin({ shorthand: false, dateFormat: "Y-m-d", altFormat: "F \\d\\e Y", theme: "dark" }) ],
        locale: "pt", altInput: true, defaultDate: "<?= $data_filtro ?>",
        onChange: function(d, s) { window.location.href = "?data_inicio=" + s; }
    });

    // Função para criar Degradê no Canvas
    function createGradient(ctx, colorStart, colorEnd) {
        const gradient = ctx.createLinearGradient(0, 300, 0, 0);
        gradient.addColorStop(0, colorStart);
        gradient.addColorStop(1, colorEnd);
        return gradient;
    }

    // --- GRÁFICO 1: EVOLUÇÃO ---
    const ctxEvo = document.getElementById('evolutionChart').getContext('2d');
    
    // Criando Degradês para as barras
    const gradGreen = createGradient(ctxEvo, 'rgba(16, 185, 129, 0.6)', '#10b981');
    const gradRed = createGradient(ctxEvo, 'rgba(239, 68, 68, 0.6)', '#ef4444');

    new Chart(ctxEvo, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labelsEvo) ?>,
            datasets: [
                {
                    label: 'Entradas',
                    data: <?= json_encode($dataEntradas) ?>,
                    backgroundColor: gradGreen,
                    borderRadius: 6, // Arredondado
                    barThickness: 20, // Mais fino
                    borderSkipped: false
                },
                {
                    label: 'Saídas',
                    data: <?= json_encode($dataSaidas) ?>,
                    backgroundColor: gradRed,
                    borderRadius: 6,
                    barThickness: 20,
                    borderSkipped: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { align: 'end', labels: { color: '#94a3b8', usePointStyle: true, boxWidth: 6 } }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: 'rgba(255,255,255,0.03)', drawBorder: false },
                    ticks: { color: '#64748b', font: { size: 11 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b', font: { size: 11 } }
                }
            }
        }
    });

    <?php if(!empty($catsData)): ?>
    // --- GRÁFICO 2: CATEGORIAS ---
    const ctxCat = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctxCat, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($catsData, 'nome')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($catsData, 'total')) ?>,
                backgroundColor: <?= json_encode(array_column($catsData, 'cor_hex')) ?>,
                borderWidth: 0,
                borderRadius: 5, // Bordas suaves nos segmentos
                cutout: '80%' // Rosca mais fina (elegante)
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        },
        plugins: [{
            id: 'centerText',
            beforeDraw: function(chart) {
                var width = chart.width, height = chart.height, ctx = chart.ctx;
                ctx.restore();
                ctx.font = "bold 16px Outfit";
                ctx.textBaseline = "middle";
                ctx.fillStyle = "#fff";
                var text = "<?= formatarMoeda($resumo['saidas']) ?>",
                    textX = Math.round((width - ctx.measureText(text).width) / 2),
                    textY = height / 2;
                ctx.fillText(text, textX, textY);
                ctx.font = "10px Outfit"; ctx.fillStyle = "#64748b";
                var label = "TOTAL";
                ctx.fillText(label, Math.round((width - ctx.measureText(label).width) / 2), textY - 15);
                ctx.save();
            }
        }]
    });
    <?php endif; ?>
</script>

<script src="assets/js/script.js?v=<?= time() ?>"></script>
</body>
</html>