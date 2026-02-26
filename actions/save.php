<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Recebe os dados básicos
$id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$tipo = filter_input(INPUT_POST, 'tipo');
$url_inicio = filter_input(INPUT_POST, 'url_inicio', FILTER_SANITIZE_SPECIAL_CHARS);
$url_fim = filter_input(INPUT_POST, 'url_fim', FILTER_SANITIZE_SPECIAL_CHARS);
$valor_total = filter_input(INPUT_POST, 'valor', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$descricao = filter_input(INPUT_POST, 'descricao');
$categoria_id = filter_input(INPUT_POST, 'categoria');
$data_inicial = filter_input(INPUT_POST, 'data'); // Data da parcela que o usuário está editando
$status = isset($_POST['status_pago']) ? 'pago' : 'pendente';
$repeticao = filter_input(INPUT_POST, 'repeticao');
$qtd_parcelas = filter_input(INPUT_POST, 'parcelas', FILTER_SANITIZE_NUMBER_INT);

// Força 1 parcela se for transação única
if ($repeticao === 'unica' || empty($qtd_parcelas)) {
    $qtd_parcelas = 1;
}

if ($id) {
    // ==========================================
    // MODO EDIÇÃO
    // ==========================================
    
    // Busca os dados da transação atual
    $stmt = $pdo->prepare("SELECT hash_pedido, parcela_atual, parcelas_totais FROM transacoes WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    $parcela_editada = $current['parcela_atual'] ?? 1;
    $total_parcelas = $current['parcelas_totais'] ?? 1;

    if ($current && !empty($current['hash_pedido'])) {
        // --- É UM GRUPO (PARCELADA) ---
        
        // 1. Calcula qual seria a data da Parcela 1 (Data Base) recuando os meses
        $meses_para_voltar = $parcela_editada - 1;
        $data_base = date('Y-m-d', strtotime("-$meses_para_voltar month", strtotime($data_inicial)));

        // Remove o texto "(X/Y)" da descrição para não duplicar
        $desc_base = preg_replace('/\s\(\d+\/\d+\)$/', '', $descricao);

        // 2. Busca TODAS as parcelas deste grupo
        $stmtAll = $pdo->prepare("SELECT id, parcela_atual FROM transacoes WHERE hash_pedido = ?");
        $stmtAll->execute([$current['hash_pedido']]);
        $todas_parcelas = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        // 3. Atualiza TODAS as parcelas recalculando a data exata de cada uma
        foreach ($todas_parcelas as $pf) {
            $meses_frente = $pf['parcela_atual'] - 1;
            $nova_data_parcela = date('Y-m-d', strtotime("+$meses_frente month", strtotime($data_base)));
            $nova_desc = $desc_base . " (" . $pf['parcela_atual'] . "/" . $total_parcelas . ")";
            
            // Sincroniza Tipo, Valor, Descrição, Categoria e a Data Calculada
            $upd = $pdo->prepare("UPDATE transacoes SET tipo=?, valor=?, descricao=?, categoria_id=?, data_transacao=? WHERE id=?");
            $upd->execute([$tipo, $valor_total, $nova_desc, $categoria_id, $nova_data_parcela, $pf['id']]);
        }

        // 4. Atualiza o Status (Pago/Pendente) APENAS na parcela que o usuário editou na tela
        $updStatus = $pdo->prepare("UPDATE transacoes SET status=? WHERE id=?");
        $updStatus->execute([$status, $id]);

    } else {
        // --- É TRANSAÇÃO ÚNICA ---
        $sql = "UPDATE transacoes SET tipo=?, valor=?, descricao=?, categoria_id=?, data_transacao=?, status=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tipo, $valor_total, $descricao, $categoria_id, $data_inicial, $status, $id]);
    }

} else {
    // ==========================================
    // MODO NOVA TRANSAÇÃO
    // ==========================================
    
    $valor_parcela = $valor_total;
    if ($repeticao === 'parcelada' && $qtd_parcelas > 0) {
        $valor_parcela = $valor_total / $qtd_parcelas;
    }

    $hash_pedido = ($repeticao !== 'unica') ? uniqid('tr_') : null;

    for ($i = 0; $i < $qtd_parcelas; $i++) {
        $data_vencimento = date('Y-m-d', strtotime("+$i month", strtotime($data_inicial)));
        $parcela_atual = $i + 1;

        $descricao_final = $descricao;
        if ($repeticao !== 'unica') {
            $descricao_final .= " ($parcela_atual/$qtd_parcelas)";
        }

        $sql = "INSERT INTO transacoes (tipo, valor, descricao, categoria_id, data_transacao, status, hash_pedido, parcela_atual, parcelas_totais) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tipo, $valor_parcela, $descricao_final, $categoria_id, $data_vencimento, $status, $hash_pedido, $parcela_atual, $qtd_parcelas]);
    }
}
// Substitua o final do arquivo por isso:
$url_retorno = '../index.php?status=success';

// Se existirem datas de filtro, anexa elas no redirecionamento
if ($url_inicio && $url_fim) {
    $url_retorno .= "&data_inicio={$url_inicio}&data_fim={$url_fim}";
}
header('Location: ' . $url_retorno);
exit;