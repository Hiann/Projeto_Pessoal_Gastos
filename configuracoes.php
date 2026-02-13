<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 1. ADICIONAR NOVA CATEGORIA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $tipo = filter_input(INPUT_POST, 'tipo', FILTER_SANITIZE_SPECIAL_CHARS);
    $cor  = filter_input(INPUT_POST, 'cor_final', FILTER_SANITIZE_SPECIAL_CHARS);

    if ($nome && $tipo && $cor) {
        // Previne duplicidade de cor/nome se necessário, ou apenas insere
        $stmt = $pdo->prepare("INSERT INTO categorias (nome, tipo, cor_hex) VALUES (:nome, :tipo, :cor)");
        if ($stmt->execute([':nome' => $nome, ':tipo' => $tipo, ':cor' => $cor])) {
            header("Location: configuracoes.php?status=success");
            exit;
        }
    }
}

// 2. EXCLUIR CATEGORIA
if (isset($_GET['delete'])) {
    $id = filter_input(INPUT_GET, 'delete', FILTER_SANITIZE_NUMBER_INT);
    
    // Verifica uso
    $check = $pdo->prepare("SELECT COUNT(*) FROM transacoes WHERE categoria_id = ?");
    $check->execute([$id]);
    
    if ($check->fetchColumn() > 0) {
        header("Location: configuracoes.php?status=error_used");
    } else {
        $pdo->prepare("DELETE FROM categorias WHERE id = ?")->execute([$id]);
        header("Location: configuracoes.php?status=deleted");
    }
    exit;
}

// 3. BUSCAR CATEGORIAS
$sqlCat = "SELECT * FROM categorias ORDER BY tipo DESC, nome ASC"; 
$categorias = $pdo->query($sqlCat)->fetchAll();

// Paleta Fixa
$paleta = [
    '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', 
    '#ec4899', '#6366f1', '#14b8a6', '#f97316', '#64748b'
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações | Finance Pro</title>
    
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">

    <style>
        body { font-family: 'Outfit', sans-serif; }

        .config-container {
            display: grid; grid-template-columns: 350px 1fr; gap: 30px; align-items: start;
        }

        /* Painel Esquerdo */
        .form-panel {
            background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid rgba(255,255,255,0.05); border-radius: 20px;
            padding: 30px; position: sticky; top: 20px;
        }
        .form-panel h3 { margin-bottom: 20px; color: #fff; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; }
        .form-panel p { color: #94a3b8; font-size: 0.9rem; margin-bottom: 25px; line-height: 1.5; }

        /* Painel Direito */
        .list-panel {
            background: #1e293b; border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px; padding: 30px; min-height: 500px;
        }
        .section-title {
            color: #64748b; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
            margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px;
            display: flex; align-items: center; gap: 8px;
        }

        /* --- NOVO SELETOR DE CORES --- */
        .color-grid {
            display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-top: 10px;
        }
        
        .color-btn {
            width: 45px; height: 45px; border-radius: 50%; cursor: pointer;
            border: 2px solid rgba(255,255,255,0.1); transition: all 0.2s; position: relative;
        }
        .color-btn:hover { transform: scale(1.1); }

        /* Classe para o item selecionado */
        .color-btn.active {
            border-color: #fff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
            transform: scale(1.1);
        }
        .color-btn.active::after {
            content: '\f00c'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
            color: #fff; position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%); font-size: 16px; text-shadow: 0 1px 2px rgba(0,0,0,0.5);
        }

        /* Botão Rainbow (Personalizado) */
        .rainbow-btn {
            background: conic-gradient(red, orange, yellow, lime, blue, magenta, red);
        }

        /* Preview do Código */
        .hex-box {
            margin-top: 15px; background: rgba(0,0,0,0.3); padding: 12px; border-radius: 10px;
            display: flex; justify-content: space-between; align-items: center;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .hex-text { font-family: monospace; font-size: 1.1rem; color: #fff; letter-spacing: 1px; }
        .hex-dot { width: 24px; height: 24px; border-radius: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.3); }

        /* Lista de Categorias */
        .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .cat-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px; padding: 15px; display: flex; justify-content: space-between; align-items: center;
            transition: all 0.2s;
        }
        .cat-card:hover { background: rgba(255,255,255,0.06); transform: translateY(-2px); border-color: rgba(255,255,255,0.1); }
        .cat-info { display: flex; align-items: center; gap: 12px; }
        .cat-dot { width: 12px; height: 12px; border-radius: 50%; box-shadow: 0 0 10px rgba(0,0,0,0.3); }
        .cat-name { color: #fff; font-weight: 500; font-size: 0.95rem; }
        .btn-delete-cat {
            color: #64748b; background: transparent; border: none; cursor: pointer;
            width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .btn-delete-cat:hover { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        @media (max-width: 900px) {
            .config-container { grid-template-columns: 1fr; }
            .form-panel { position: static; margin-bottom: 30px; }
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
            <a href="metas.php" class="menu-item"><i class="fas fa-bullseye"></i> <span>Metas</span></a>
            <p class="menu-label">Sistema</p>
            <a href="configuracoes.php" class="menu-item active"><i class="fas fa-cog"></i> <span>Configurações</span></a>
        </nav>
        <div class="sidebar-footer"><div class="user-card"><div class="avatar">H</div><div class="user-info"><strong>Hiann Oliveira</strong><span>Admin</span></div><a href="#" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a></div></div>
    </aside>

    <main>
        <header class="dashboard-header">
            <div class="header-top">
                <div class="header-title-area">
                    <button type="button" class="btn-menu" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                    <div class="header-title"><h1>Configurações</h1><p>Gerencie as categorias do sistema.</p></div>
                </div>
            </div>
        </header>

        <div class="config-container">
            
            <div class="form-panel">
                <h3><i class="fas fa-plus-circle" style="color: #3b82f6;"></i> Nova Categoria</h3>
                <p>Personalize as cores para organizar seu financeiro.</p>
                
                <form action="configuracoes.php" method="POST" id="formCategoria">
                    <input type="hidden" name="action" value="create">
                    
                    <input type="hidden" name="cor_final" id="corFinal" value="<?= $paleta[0] ?>">
                    
                    <input type="color" id="nativePicker" style="visibility: hidden; position: absolute;" onchange="applyCustomColor(this.value)">

                    <div class="input-group">
                        <label class="input-label">Nome da Categoria</label>
                        <input type="text" name="nome" class="styled-input" placeholder="Ex: Mercado, Uber..." required autocomplete="off">
                    </div>

                    <div class="input-group">
                        <label class="input-label">Tipo</label>
                        <select name="tipo" class="styled-input" required>
                            <option value="despesa">Despesa (Saída)</option>
                            <option value="receita">Receita (Entrada)</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label class="input-label">Cor de Identificação</label>
                        
                        <div class="color-grid">
                            <?php foreach($paleta as $index => $cor): ?>
                                <div class="color-btn <?= $index === 0 ? 'active' : '' ?>" 
                                     style="background-color: <?= $cor ?>;" 
                                     onclick="selectPreset('<?= $cor ?>', this)">
                                </div>
                            <?php endforeach; ?>

                            <div class="color-btn rainbow-btn" 
                                 id="btnCustom"
                                 onclick="openColorPicker()" 
                                 title="Escolher cor personalizada">
                            </div>
                        </div>

                        <div class="hex-box">
                            <span style="font-size: 10px; color: #aaa; text-transform: uppercase;">Selecionado</span>
                            <span class="hex-text" id="hexText"><?= strtoupper($paleta[0]) ?></span>
                            <div class="hex-dot" id="hexDot" style="background-color: <?= $paleta[0] ?>;"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; margin-top: 20px;">
                        <i class="fas fa-check"></i> Criar Categoria
                    </button>
                </form>
            </div>

            <div class="list-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <h3 style="margin:0; font-size:1.3rem;">Categorias Ativas</h3>
                    <span style="font-size: 0.8rem; color: #64748b;"><?= count($categorias) ?> cadastradas</span>
                </div>

                <?php 
                $tipoAtual = '';
                foreach($categorias as $c): 
                    if ($c['tipo'] != $tipoAtual) {
                        if ($tipoAtual != '') echo '</div>';
                        $tipoAtual = $c['tipo'];
                        $iconeSecao = ($tipoAtual == 'receita') ? '<i class="fas fa-arrow-up" style="color:#10b981;"></i> RECEITAS' : '<i class="fas fa-arrow-down" style="color:#ef4444;"></i> DESPESAS';
                        echo "<div class='section-title'>$iconeSecao</div>";
                        echo "<div class='cat-grid'>";
                    }
                ?>
                    <div class="cat-card">
                        <div class="cat-info">
                            <div class="cat-dot" style="background-color: <?= $c['cor_hex'] ?>;"></div>
                            <span class="cat-name"><?= htmlspecialchars($c['nome']) ?></span>
                        </div>
                        <a href="configuracoes.php?delete=<?= $c['id'] ?>" class="btn-delete-cat" onclick="return confirm('Apagar esta categoria?')" title="Excluir">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
                </div> <?php if(count($categorias) == 0): ?>
                    <div style="text-align: center; color: #64748b; padding: 50px;">
                        <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Nenhuma categoria cadastrada.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<div id="toast-container"></div>
<script src="assets/js/script.js?v=<?= time() ?>"></script>
<script>
    // Elementos
    const corFinalInput = document.getElementById('corFinal');
    const hexText = document.getElementById('hexText');
    const hexDot = document.getElementById('hexDot');
    const nativePicker = document.getElementById('nativePicker');
    const btnCustom = document.getElementById('btnCustom');
    const allBtns = document.querySelectorAll('.color-btn');

    // 1. Função quando clica numa cor pronta
    function selectPreset(cor, element) {
        // Remove active de todos
        allBtns.forEach(btn => btn.classList.remove('active'));
        // Adiciona active no clicado
        element.classList.add('active');
        
        // Reseta o botão rainbow para o gradiente original
        btnCustom.style.background = "conic-gradient(red, orange, yellow, lime, blue, magenta, red)";
        
        updateValues(cor);
    }

    // 2. Função para abrir o seletor nativo
    function openColorPicker() {
        nativePicker.click(); // Simula o clique no input hidden
    }

    // 3. Função quando escolhe uma cor no seletor nativo
    function applyCustomColor(cor) {
        // Remove active dos presets
        allBtns.forEach(btn => btn.classList.remove('active'));
        
        // Ativa o botão custom e muda a cor dele para a escolhida
        btnCustom.classList.add('active');
        btnCustom.style.background = cor; 
        
        updateValues(cor);
    }

    // Atualiza input hidden e preview visual
    function updateValues(cor) {
        corFinalInput.value = cor;
        hexText.innerText = cor.toUpperCase();
        hexDot.style.backgroundColor = cor;
    }

    // Toasts
    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.get('status') === 'success') showToast("Categoria criada!", "success");
    if(urlParams.get('status') === 'deleted') showToast("Categoria removida.", "success");
    if(urlParams.get('status') === 'error_used') showToast("Erro: Categoria em uso!", "error");
</script>

</body>
</html>