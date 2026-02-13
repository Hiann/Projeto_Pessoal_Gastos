<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 1. CONFIGURAÇÃO DE DATA (MÊS SELECIONADO)
$data_filtro = filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_SPECIAL_CHARS) ?? date('Y-m-01');
$mes_atual = (int)date('m', strtotime($data_filtro));
$ano_atual = (int)date('Y', strtotime($data_filtro));

// --- CORREÇÃO DE TRADUÇÃO (Mapeamento Manual) ---
$meses_pt = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
    7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
$nome_mes_atual = $meses_pt[$mes_atual]; // Ex: Fevereiro
$nome_mes_curto = substr($nome_mes_atual, 0, 3); // Ex: Fev
// ------------------------------------------------

// Cálculo do mês anterior para a função de importar
$timestamp_anterior = strtotime("-1 month", strtotime($data_filtro));
$mes_anterior = (int)date('m', $timestamp_anterior);
$ano_anterior = (int)date('Y', $timestamp_anterior);

// --- AÇÕES DO FORMULÁRIO ---

// 2. SALVAR OU EDITAR META (ESPECÍFICA DO MÊS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'save') {
    $categoria_id = filter_input(INPUT_POST, 'categoria_id', FILTER_SANITIZE_NUMBER_INT);
    $valor_limite = filter_input(INPUT_POST, 'valor_limite', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $meta_id = filter_input(INPUT_POST, 'meta_id', FILTER_SANITIZE_NUMBER_INT);

    if ($categoria_id && $valor_limite) {
        if ($meta_id) {
            // Edita apenas a meta selecionada (ID único)
            $stmt = $pdo->prepare("UPDATE metas SET valor_limite = :valor WHERE id = :id");
            $stmt->execute([':valor' => $valor_limite, ':id' => $meta_id]);
        } else {
            // Verifica se JÁ EXISTE meta para essa categoria NESTE MÊS/ANO
            $check = $pdo->prepare("SELECT id FROM metas WHERE categoria_id = :cat AND mes = :mes AND ano = :ano");
            $check->execute([':cat' => $categoria_id, ':mes' => $mes_atual, ':ano' => $ano_atual]);
            
            if ($existing = $check->fetch()) {
                // Se existe, atualiza
                $stmt = $pdo->prepare("UPDATE metas SET valor_limite = :val WHERE id = :id");
                $stmt->execute([':val' => $valor_limite, ':id' => $existing['id']]);
            } else {
                // Se não, cria nova com Mês e Ano
                $stmt = $pdo->prepare("INSERT INTO metas (categoria_id, valor_limite, mes, ano) VALUES (:cat, :val, :mes, :ano)");
                $stmt->execute([
                    ':cat' => $categoria_id, 
                    ':val' => $valor_limite,
                    ':mes' => $mes_atual,
                    ':ano' => $ano_atual
                ]);
            }
        }
        header("Location: metas.php?data_inicio=$data_filtro&status=saved");
        exit;
    }
}

// 3. IMPORTAR METAS DO MÊS ANTERIOR (FUNCIONALIDADE SÊNIOR)
if (isset($_POST['action']) && $_POST['action'] == 'import') {
    // Busca metas do mês passado
    $sqlOld = "SELECT categoria_id, valor_limite FROM metas WHERE mes = :mes_ant AND ano = :ano_ant";
    $stmtOld = $pdo->prepare($sqlOld);
    $stmtOld->execute([':mes_ant' => $mes_anterior, ':ano_ant' => $ano_anterior]);
    $metasAntigas = $stmtOld->fetchAll();

    if (count($metasAntigas) > 0) {
        $stmtInsert = $pdo->prepare("INSERT INTO metas (categoria_id, valor_limite, mes, ano) VALUES (:cat, :val, :mes, :ano)");
        foreach ($metasAntigas as $m) {
            // Verifica se já não criei para não duplicar
            $check = $pdo->prepare("SELECT id FROM metas WHERE categoria_id = ? AND mes = ? AND ano = ?");
            $check->execute([$m['categoria_id'], $mes_atual, $ano_atual]);
            if (!$check->fetch()) {
                $stmtInsert->execute([
                    ':cat' => $m['categoria_id'],
                    ':val' => $m['valor_limite'],
                    ':mes' => $mes_atual,
                    ':ano' => $ano_atual
                ]);
            }
        }
        header("Location: metas.php?data_inicio=$data_filtro&status=imported");
    } else {
        header("Location: metas.php?data_inicio=$data_filtro&status=no_data");
    }
    exit;
}

// 4. EXCLUSÃO
if (isset($_GET['delete'])) {
    $id = filter_input(INPUT_GET, 'delete', FILTER_SANITIZE_NUMBER_INT);
    $pdo->prepare("DELETE FROM metas WHERE id = ?")->execute([$id]);
    header("Location: metas.php?data_inicio=$data_filtro");
    exit;
}

// --- CONSULTAS ---

// Categorias
$categorias = $pdo->query("SELECT * FROM categorias WHERE tipo = 'despesa' ORDER BY nome ASC")->fetchAll();

// Metas do Mês Atual
$sqlMetas = "SELECT m.*, c.nome as cat_nome, c.cor_hex,
            (SELECT COALESCE(SUM(t.valor), 0) FROM transacoes t 
             WHERE t.categoria_id = m.categoria_id 
             AND t.tipo = 'despesa' 
             AND MONTH(t.data_transacao) = :mes 
             AND YEAR(t.data_transacao) = :ano) as gasto_atual
            FROM metas m 
            JOIN categorias c ON m.categoria_id = c.id
            WHERE m.mes = :mes AND m.ano = :ano"; // FILTRO DE MÊS APLICADO

$stmt = $pdo->prepare($sqlMetas);
$stmt->execute([':mes' => $mes_atual, ':ano' => $ano_atual]);
$metas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metas | Finance Pro</title>
    
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/style.css">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/index.js"></script>
    <script src="https://npmcdn.com/flatpickr@4.6.13/dist/l10n/pt.js"></script>

    <style>
        body { font-family: 'Outfit', sans-serif; }
        
        .metas-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
            align-items: start;
        }

        .create-panel {
            background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 30px;
            position: sticky;
            top: 20px;
        }
        
        .create-panel h3 { font-size: 1.2rem; margin-bottom: 20px; color: #fff; display: flex; align-items: center; gap: 10px; }
        .create-panel p { color: #94a3b8; font-size: 0.9rem; margin-bottom: 25px; line-height: 1.5; }

        .metas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .meta-card {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .meta-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            border-color: rgba(255,255,255,0.1);
        }

        .meta-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .meta-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: bold; color: #fff;
        }
        .meta-info { flex: 1; margin-left: 12px; }
        .meta-title { font-weight: 600; color: #fff; font-size: 1rem; }
        .meta-status { font-size: 0.75rem; font-weight: 600; padding: 4px 8px; border-radius: 6px; text-transform: uppercase; }
        
        .status-ok { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .status-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .progress-container { margin: 15px 0; }
        .progress-labels { display: flex; justify-content: space-between; font-size: 0.8rem; color: #94a3b8; margin-bottom: 5px; }
        .progress-bar-bg { height: 8px; background: rgba(255,255,255,0.05); border-radius: 4px; overflow: hidden; }
        .progress-bar-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }

        .meta-values { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 15px; }
        .val-current { font-size: 1.2rem; font-weight: 700; color: #fff; }
        .val-target { font-size: 0.85rem; color: #64748b; }

        .meta-actions { display: flex; gap: 8px; }
        .btn-icon {
            width: 32px; height: 32px; border-radius: 8px; border: none;
            background: rgba(255,255,255,0.05); color: #94a3b8;
            cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center;
        }
        .btn-icon:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .btn-icon.delete:hover { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        /* Estado Vazio */
        .empty-metas {
            grid-column: 1 / -1; 
            text-align: center; 
            padding: 50px; 
            color: #64748b; 
            background: #1e293b; 
            border-radius: 16px;
            border: 1px dashed rgba(255,255,255,0.1);
        }
        .btn-import {
            margin-top: 15px;
            background: rgba(59, 130, 246, 0.1); 
            color: #3b82f6; 
            border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-import:hover { background: rgba(59, 130, 246, 0.2); }

        @media (max-width: 900px) {
            .metas-container { grid-template-columns: 1fr; }
            .create-panel { position: static; margin-bottom: 30px; }
        }
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
            <a href="relatorios.php" class="menu-item"><i class="fas fa-chart-pie"></i> <span>Relatórios</span></a>
            <a href="metas.php" class="menu-item active"><i class="fas fa-bullseye"></i> <span>Metas</span></a>
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
                    <div class="header-title"><h1>Minhas Metas</h1><p>Planejamento para <strong><?= $nome_mes_atual ?> de <?= $ano_atual ?></strong>.</p></div>
                </div>
                <div class="header-actions">
                    <div class="month-picker-container">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="text" id="datePicker" value="<?= $data_filtro ?>" readonly>
                        <i class="fas fa-chevron-down" style="font-size: 12px; margin-left: auto; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </header>

        <div class="metas-container">
            
            <div class="create-panel">
                <h3 id="formTitle"><i class="fas fa-flag"></i> Definir Meta (<?= $nome_mes_curto ?>)</h3>
                <p>Esta meta valerá apenas para o mês selecionado no topo.</p>
                
                <form action="metas.php?data_inicio=<?= $data_filtro ?>" method="POST">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="meta_id" id="metaId">
                    
                    <div class="input-group">
                        <label class="input-label">Categoria Alvo</label>
                        <div class="ts-clean-wrapper">
                            <select name="categoria_id" id="categoriaSelect" required class="ts-clean" placeholder="Buscar categoria...">
                                <option value="">Selecione...</option>
                                <?php foreach($categorias as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['nome'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <label class="input-label">Limite Mensal (R$)</label>
                        <input type="number" step="0.01" name="valor_limite" id="valorLimite" class="styled-input" placeholder="0,00" required>
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;" id="btnSubmit">
                        <i class="fas fa-save"></i> Salvar Meta
                    </button>
                    
                    <button type="button" id="btnCancel" style="width: 100%; margin-top: 10px; background: transparent; border: 1px solid #475569; color: #cbd5e1; display: none;" class="btn-primary" onclick="resetForm()">
                        Cancelar Edição
                    </button>
                </form>
            </div>

            <div class="metas-grid">
                <?php if(count($metas) > 0): ?>
                    <?php foreach($metas as $m): 
                        $porcentagem = ($m['valor_limite'] > 0) ? ($m['gasto_atual'] / $m['valor_limite']) * 100 : 0;
                        $statusClass = 'status-ok';
                        $statusText = 'No Controle';
                        $barColor = '#10b981';

                        if($porcentagem >= 75 && $porcentagem < 100) {
                            $statusClass = 'status-warning';
                            $statusText = 'Atenção';
                            $barColor = '#f59e0b';
                        } elseif($porcentagem >= 100) {
                            $statusClass = 'status-danger';
                            $statusText = 'Estourado';
                            $barColor = '#ef4444';
                        }
                        
                        $letra = mb_substr($m['cat_nome'], 0, 1);
                    ?>
                    <div class="meta-card">
                        <div class="meta-header">
                            <div class="meta-icon" style="background-color: <?= $m['cor_hex'] ?>;"><?= $letra ?></div>
                            <div class="meta-info">
                                <div class="meta-title"><?= $m['cat_nome'] ?></div>
                                <div style="font-size: 11px; color: #64748b;">Mês: <?= $nome_mes_curto ?>/<?= $ano_atual ?></div>
                            </div>
                            <span class="meta-status <?= $statusClass ?>"><?= $statusText ?></span>
                        </div>

                        <div class="progress-container">
                            <div class="progress-labels">
                                <span>Progresso</span>
                                <span><?= number_format($porcentagem, 1) ?>%</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?= min($porcentagem, 100) ?>%; background-color: <?= $barColor ?>;"></div>
                            </div>
                        </div>

                        <div class="meta-values">
                            <div>
                                <div class="val-current"><?= formatarMoeda($m['gasto_atual']) ?></div>
                                <div class="val-target">de <?= formatarMoeda($m['valor_limite']) ?></div>
                            </div>
                            <div class="meta-actions">
                                <button type="button" class="btn-icon" onclick='editarMeta(<?= json_encode($m) ?>)' title="Editar">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <a href="metas.php?delete=<?= $m['id'] ?>&data_inicio=<?= $data_filtro ?>" class="btn-icon delete" onclick="return confirm('Excluir esta meta?')" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-metas">
                        <i class="fas fa-ghost" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Nenhuma meta definida para <strong><?= $nome_mes_atual ?> de <?= $ano_atual ?></strong>.</p>
                        
                        <form action="metas.php?data_inicio=<?= $data_filtro ?>" method="POST">
                            <input type="hidden" name="action" value="import">
                            <button type="submit" class="btn-import">
                                <i class="fas fa-copy"></i> Copiar Metas do Mês Anterior
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<script>
    var tomSelectCat = new TomSelect("#categoriaSelect", {
        create: false,
        sortField: { field: "text", direction: "asc" }
    });

    flatpickr("#datePicker", {
        plugins: [ new monthSelectPlugin({ shorthand: false, dateFormat: "Y-m-d", altFormat: "F \\d\\e Y", theme: "dark" }) ],
        locale: "pt", altInput: true, defaultDate: "<?= $data_filtro ?>",
        onChange: function(d, s) { window.location.href = "?data_inicio=" + s; }
    });

    function editarMeta(meta) {
        document.getElementById('metaId').value = meta.id;
        document.getElementById('valorLimite').value = meta.valor_limite;
        tomSelectCat.setValue(meta.categoria_id);
        
        document.getElementById('formTitle').innerHTML = '<i class="fas fa-pen"></i> Editar Meta (<?= $nome_mes_curto ?>)';
        document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-check"></i> Atualizar Meta';
        document.getElementById('btnCancel').style.display = 'block';

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('metaId').value = '';
        document.getElementById('valorLimite').value = '';
        tomSelectCat.clear();
        
        document.getElementById('formTitle').innerHTML = '<i class="fas fa-flag"></i> Definir Meta (<?= $nome_mes_curto ?>)';
        document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Salvar Meta';
        document.getElementById('btnCancel').style.display = 'none';
    }
</script>

<script src="assets/js/script.js?v=<?= time() ?>"></script>
</body>
</html>