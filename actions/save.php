<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Recebe os dados
$id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$tipo = filter_input(INPUT_POST, 'tipo');
$valor_total = filter_input(INPUT_POST, 'valor', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$descricao = filter_input(INPUT_POST, 'descricao');
$categoria_id = filter_input(INPUT_POST, 'categoria');
$data_inicial = filter_input(INPUT_POST, 'data');
$status = isset($_POST['status_pago']) ? 'pago' : 'pendente';
$repeticao = filter_input(INPUT_POST, 'repeticao'); // unica, parcelada, fixa
$qtd_parcelas = filter_input(INPUT_POST, 'parcelas', FILTER_SANITIZE_NUMBER_INT);

// --- CORREÇÃO DO BUG DE DUPLICAÇÃO ---

// Se for "Única", forçamos parcelas = 1 para evitar loop errado
if ($repeticao === 'unica' || empty($qtd_parcelas)) {
    $qtd_parcelas = 1;
}

// Se for edição (ID existe), atualiza apenas um registro
if ($id) {
    $sql = "UPDATE transacoes SET tipo=?, valor=?, descricao=?, categoria_id=?, data_transacao=?, status=? WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tipo, $valor_total, $descricao, $categoria_id, $data_inicial, $status, $id]);
} 
// Se for nova transação (Insert)
else {
    // Calcula o valor da parcela (se for parcelada, divide. Se for fixa ou única, é o valor cheio)
    $valor_parcela = $valor_total;
    if ($repeticao === 'parcelada' && $qtd_parcelas > 0) {
        $valor_parcela = $valor_total / $qtd_parcelas;
    }

    // LOOP DE INSERÇÃO
    for ($i = 0; $i < $qtd_parcelas; $i++) {
        
        // Cálculo da Data:
        // Se for a primeira (i=0), usa a data original.
        // Se for as próximas, soma meses.
        $data_vencimento = date('Y-m-d', strtotime("+$i month", strtotime($data_inicial)));

        // Descrição da parcela (ex: "Compra 1/5") apenas se não for única
        $descricao_final = $descricao;
        if ($repeticao !== 'unica') {
            $num = $i + 1;
            $descricao_final .= " ($num/$qtd_parcelas)";
        }

        $sql = "INSERT INTO transacoes (tipo, valor, descricao, categoria_id, data_transacao, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tipo, $valor_parcela, $descricao_final, $categoria_id, $data_vencimento, $status]);
    }
}

// Redireciona de volta
header('Location: ../index.php?status=success');
exit;